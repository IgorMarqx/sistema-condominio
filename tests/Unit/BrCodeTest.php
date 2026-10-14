<?php

namespace Tests\Unit;

use App\Services\Pix\BrCode;
use PHPUnit\Framework\TestCase;

class BrCodeTest extends TestCase
{
    public function test_crc16_ccitt_do_vetor_padrao(): void
    {
        // CRC-16/CCITT-FALSE de "123456789" é 29B1.
        $this->assertSame('29B1', BrCode::crc16('123456789'));
    }

    public function test_payload_tem_valor_txid_e_crc_valido(): void
    {
        $codigo = BrCode::gerar('caixa@condominio.exemplo', 'Condomínio Edifício Exemplo', 'João Pessoa', 10422, 'COND202610AP302');

        $this->assertStringStartsWith('000201', $codigo);
        $this->assertStringContainsString('br.gov.bcb.pix', $codigo);
        $this->assertStringContainsString('5406104.22', $codigo);
        $this->assertStringContainsString('COND202610AP302', $codigo);
        $this->assertStringContainsString('CONDOMINIO EDIFICIO EXEM', $codigo);
        $this->assertSame(BrCode::crc16(substr($codigo, 0, -4)), substr($codigo, -4));
    }
}
