<?php

namespace App\Services\Auditoria;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Trilha de auditoria append-only e encadeada: hash = SHA-256(hash_anterior + conteúdo).
 * Qualquer alteração direta no banco quebra a cadeia a partir do registro alterado.
 */
class Auditoria
{
    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    public function registrar(
        string $acao,
        Model|string|null $entidade = null,
        ?array $antes = null,
        ?array $depois = null,
        ?Ator $ator = null,
    ): AuditLog {
        $ator ??= Ator::atual();

        [$nomeEntidade, $entidadeId] = $this->entidade($entidade);

        // Cria a transação se não houver uma: o FOR UPDATE da cabeça só vale até o commit.
        return DB::transaction(function () use ($acao, $nomeEntidade, $entidadeId, $antes, $depois, $ator): AuditLog {
            $cabeca = DB::table('audit_cabeca')->where('id', 1)->lockForUpdate()->first();

            $dados = [
                'ator_tipo' => $ator->tipo,
                'ator_id' => $ator->id,
                'ator_rotulo' => $ator->rotulo,
                'nome_informado' => $ator->nomeInformado,
                'acao' => $acao,
                'entidade' => $nomeEntidade,
                'entidade_id' => $entidadeId,
                'antes_json' => $antes === null ? null : self::json($antes),
                'depois_json' => $depois === null ? null : self::json($depois),
                'ip' => $ator->ip,
                'hash_anterior' => $cabeca?->ultimo_hash,
                'registrado_em' => Carbon::now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
            ];

            $dados['hash'] = self::calcularHash($dados);

            $registro = AuditLog::query()->create($dados);

            DB::table('audit_cabeca')->where('id', 1)->update([
                'ultimo_id' => $registro->id,
                'ultimo_hash' => $registro->hash,
            ]);

            return $registro;
        });
    }

    /**
     * Recalcula a cadeia inteira. Devolve o primeiro registro que não confere, ou null se íntegra.
     *
     * @return array{integra: bool, total: int, ultimo_hash: string|null, falha: array{id: int, motivo: string}|null}
     */
    public function verificar(): array
    {
        $anterior = null;
        $total = 0;
        $falha = null;

        AuditLog::query()->orderBy('id')->chunk(500, function ($registros) use (&$anterior, &$total, &$falha) {
            foreach ($registros as $registro) {
                $total++;

                if ($registro->hash_anterior !== $anterior) {
                    $falha = ['id' => $registro->id, 'motivo' => 'O elo com o registro anterior foi quebrado (registro apagado ou inserido fora da ordem).'];

                    return false;
                }

                if (self::calcularHash($registro->getAttributes()) !== $registro->hash) {
                    $falha = ['id' => $registro->id, 'motivo' => 'O conteúdo do registro foi alterado depois de gravado.'];

                    return false;
                }

                $anterior = $registro->hash;
            }

            return true;
        });

        $cabeca = DB::table('audit_cabeca')->where('id', 1)->value('ultimo_hash');

        if ($falha === null && $cabeca !== $anterior) {
            $falha = ['id' => 0, 'motivo' => 'O último registro da cadeia não é o esperado (registro final apagado).'];
        }

        return [
            'integra' => $falha === null,
            'total' => $total,
            'ultimo_hash' => $anterior,
            'falha' => $falha,
        ];
    }

    public function ultimoHash(): ?string
    {
        return DB::table('audit_cabeca')->where('id', 1)->value('ultimo_hash');
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function calcularHash(array $dados): string
    {
        $campos = ['ator_tipo', 'ator_id', 'ator_rotulo', 'nome_informado', 'acao', 'entidade', 'entidade_id', 'antes_json', 'depois_json', 'ip', 'registrado_em'];
        $conteudo = [];

        foreach ($campos as $campo) {
            $valor = $dados[$campo] ?? null;
            $conteudo[$campo] = $valor === null ? null : (string) $valor;
        }

        return hash('sha256', ($dados['hash_anterior'] ?? '').json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * JSON canônico: chaves ordenadas, para o mesmo conteúdo dar sempre o mesmo texto.
     *
     * @param  array<string, mixed>  $valor
     */
    private static function json(array $valor): string
    {
        return json_encode(self::ordenar($valor), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function ordenar(mixed $valor): mixed
    {
        if ($valor instanceof \BackedEnum) {
            return $valor->value;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (! is_array($valor)) {
            return $valor;
        }

        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return array_map(self::ordenar(...), $valor);
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function entidade(Model|string|null $entidade): array
    {
        if ($entidade instanceof Model) {
            return [$entidade->getTable(), (int) $entidade->getKey()];
        }

        return [$entidade, null];
    }
}
