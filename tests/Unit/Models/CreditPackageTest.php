<?php

namespace Tests\Unit\Models;

use App\Models\CreditPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O catálogo de pacotes avulsos é configuração comercial administrável
 * (SAD 12.9): os defaults vêm do schema, nunca do código da aplicação.
 */
class CreditPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_package_defaults_to_brl_and_active(): void
    {
        $package = CreditPackage::create([
            'name' => 'Starter Pack',
            'credits' => 100,
            'price_cents' => 1900,
        ]);

        $package->refresh();

        $this->assertSame('BRL', $package->currency);
        $this->assertTrue($package->is_active);
    }

    /**
     * Um pacote fora do catálogo é desativado, jamais excluído: compras
     * históricas precisam manter a referência (SAD 12.9).
     */
    public function test_active_scope_returns_only_packages_on_the_catalog(): void
    {
        $ativo = CreditPackage::create([
            'name' => 'Starter Pack',
            'credits' => 100,
            'price_cents' => 1900,
        ]);

        $retirado = CreditPackage::create([
            'name' => 'Legacy Pack',
            'credits' => 50,
            'price_cents' => 900,
            'is_active' => false,
        ]);

        $this->assertSame([$ativo->id], CreditPackage::active()->pluck('id')->all());
        $this->assertDatabaseHas('credit_packages', ['id' => $retirado->id]);
    }
}
