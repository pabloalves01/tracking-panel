<?php

namespace Tests\Unit\Rules;

use App\Rules\Cnpj;
use App\Rules\Cpf;
use PHPUnit\Framework\TestCase;

class DocumentRulesTest extends TestCase
{
    public function test_cnpj_aceita_mascara_e_recusa_digito_errado(): void
    {
        $this->assertSame('19131243000197', Cnpj::digits('19.131.243/0001-97'));
        $this->assertTrue(Cnpj::isValid('19131243000197'));
        $this->assertTrue(Cnpj::isValid('00000000000191'));
        $this->assertFalse(Cnpj::isValid('19131243000198'));
        $this->assertFalse(Cnpj::isValid('11111111111111'));
    }

    public function test_cpf_aceita_mascara_e_recusa_repetido(): void
    {
        $this->assertSame('52998224725', Cpf::digits('529.982.247-25'));
        $this->assertTrue(Cpf::isValid('52998224725'));
        $this->assertFalse(Cpf::isValid('52998224726'));
        $this->assertFalse(Cpf::isValid('11111111111'));
    }
}
