<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * O dashboard é o shell autenticado (Sessão 3.2): sem sessão, a raiz leva
     * ao login em vez de renderizar uma Sidebar sem organização nem usuário.
     */
    public function test_the_application_root_requires_authentication(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
