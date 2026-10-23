@extends('layouts.app')

{{--
    Destino do middleware `organization` quando o usuário não pertence a
    nenhuma organização (SAD 20.2): nada do domínio existe fora de uma.

    O formulário de criação pertence ao fluxo de organizações e ainda não
    existe; esta tela é o destino real do redirecionamento enquanto isso.
    Texto em inglês por ser tela de cliente final (CLAUDE.md regra 25).
--}}

@section('content')
    <div class="mx-auto max-w-xl py-16 text-center">
        <h1 class="font-serif text-3xl">Create your organization</h1>

        <p class="mt-4 text-sm text-[#A8A29E]">
            Every brand lives inside an organization — the entity that holds your plan,
            your credits and your team. Create one to continue.
        </p>
    </div>
@endsection
