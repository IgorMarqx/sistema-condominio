<?php

namespace App\Services\Ingestao\Extratores;

use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Support\Carbon;

/**
 * Utilitários de leitura do texto do `pdftotext -layout`: rótulo seguido de valor na mesma linha
 * ou na linha de baixo (o layout em colunas joga o valor para baixo do rótulo).
 */
trait LeitorTexto
{
    protected function normalizar(string $texto): string
    {
        // Não colapsa espaços: o alinhamento das colunas é o que liga rótulo e valor.
        return str_replace(["\r\n", "\r", "\u{00A0}", "\f", "\t"], ["\n", "\n", ' ', "\n", '    '], $texto);
    }

    /**
     * Layout em colunas: o rótulo fica num cabeçalho e o valor na linha de baixo, na mesma coluna.
     * Pega, nas duas linhas seguintes, o valor cuja posição mais se aproxima da do rótulo.
     *
     * @param  list<string>  $rotulos  regex sem delimitadores
     */
    protected function naColuna(string $texto, array $rotulos, string $valor): ?string
    {
        $linhas = explode("\n", $texto);

        foreach ($rotulos as $rotulo) {
            foreach ($linhas as $i => $linha) {
                if (! preg_match('/'.$rotulo.'/iu', $linha, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                $inicio = mb_strlen(substr($linha, 0, $m[0][1]));
                $centro = $inicio + mb_strlen($m[0][0]) / 2;

                for ($j = $i + 1; $j <= min($i + 2, count($linhas) - 1); $j++) {
                    if (! preg_match_all('/'.$valor.'/u', $linhas[$j], $achados, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }

                    $melhor = null;
                    $distancia = PHP_INT_MAX;

                    foreach ($achados[0] as [$trecho, $byte]) {
                        $pos = mb_strlen(substr($linhas[$j], 0, $byte));
                        $d = abs(($pos + mb_strlen($trecho) / 2) - $centro);

                        if ($d < $distancia) {
                            $distancia = $d;
                            $melhor = $trecho;
                        }
                    }

                    if ($melhor !== null && $distancia <= 30) {
                        return trim($melhor);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Mesma linha ("VENCIMENTO: 22/10/2026") ou coluna abaixo.
     *
     * @param  list<string>  $rotulos
     */
    protected function campo(string $texto, array $rotulos, string $valor): ?string
    {
        foreach ($rotulos as $rotulo) {
            if (preg_match('/'.$rotulo.'[ \t:.\-–]*(?:R\$\s*)?('.$valor.')/iu', $texto, $m)) {
                return trim($m[1]);
            }
        }

        return $this->naColuna($texto, $rotulos, $valor);
    }

    protected function data(?string $texto): ?Carbon
    {
        if ($texto === null || ! preg_match('/(\d{2})\/(\d{2})\/(\d{2,4})/', $texto, $m)) {
            return null;
        }

        $ano = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

        if (! checkdate((int) $m[2], (int) $m[1], $ano)) {
            return null;
        }

        return Carbon::create($ano, (int) $m[2], (int) $m[1])->startOfDay();
    }

    protected function dinheiro(?string $texto): ?int
    {
        if ($texto === null) {
            return null;
        }

        try {
            return Dinheiro::paraCentavos($texto);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    protected function mes(?string $texto): ?Carbon
    {
        return $texto === null ? null : Mes::deTexto($texto);
    }

    /**
     * Linha digitável: 47/48 dígitos, com ou sem pontos e espaços.
     */
    protected function linhaDigitavel(string $texto): ?string
    {
        if (preg_match('/\b(\d{11,12}[\s.-]?\d[\s.-]+\d{11,12}[\s.-]?\d[\s.-]+\d{11,12}[\s.-]?\d[\s.-]+\d{11,12}[\s.-]?\d)\b/', $texto, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]) ?? $m[1]);
        }

        if (preg_match('/\b(\d{5}\.?\d{5}\s+\d{5}\.?\d{6}\s+\d{5}\.?\d{6}\s+\d\s+\d{14})\b/', $texto, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]) ?? $m[1]);
        }

        return null;
    }

    protected function pix(string $texto): ?string
    {
        // O copia-e-cola começa com "000201" e termina no CRC "6304XXXX"; o pdftotext pode quebrar a linha.
        $corrido = preg_replace('/\s+/', '', $texto) ?? '';

        if (preg_match('/(000201\S{20,600}?6304[0-9A-F]{4})/', $corrido, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Itens da fatura: "DESCRIÇÃO ......... 123,45" — guarda o que parecer item de cobrança.
     *
     * @param  list<string>  $palavras  só linhas com estas palavras viram item
     * @return list<array{descricao: string, valor_centavos: int}>
     */
    protected function itens(string $bloco, array $palavras): array
    {
        $itens = [];

        foreach (explode("\n", $bloco) as $linha) {
            $linha = trim($linha);

            if (! preg_match('/^(.{3,80}?)\s+(-?\d{1,3}(?:\.\d{3})*,\d{2})\s*$/u', $linha, $m)) {
                continue;
            }

            // Tira quantidade e preço unitário que vêm antes do valor: "Consumo em kWh 512 0,62305".
            $descricao = trim(preg_replace('/(\s+[\d.,]+)+$/', '', $m[1]) ?? $m[1], " .:\t");

            foreach ($palavras as $palavra) {
                if (mb_stripos($descricao, $palavra) !== false) {
                    $itens[] = ['descricao' => $descricao, 'valor_centavos' => (int) Dinheiro::paraCentavos($m[2])];
                    break;
                }
            }
        }

        return $itens;
    }

    /**
     * Recorta o texto entre dois marcadores (o primeiro que existir de cada lista).
     *
     * @param  list<string>  $inicios
     * @param  list<string>  $fins
     */
    protected function bloco(string $texto, array $inicios, array $fins): ?string
    {
        foreach ($inicios as $inicio) {
            $pos = mb_stripos($texto, $inicio);

            if ($pos === false) {
                continue;
            }

            $resto = mb_substr($texto, $pos + mb_strlen($inicio));
            $fim = null;

            foreach ($fins as $marcador) {
                $p = mb_stripos($resto, $marcador);

                if ($p !== false && ($fim === null || $p < $fim)) {
                    $fim = $p;
                }
            }

            return $fim === null ? $resto : mb_substr($resto, 0, $fim);
        }

        return null;
    }
}
