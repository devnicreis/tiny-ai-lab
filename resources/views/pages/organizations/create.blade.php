@extends('layouts.app')

{{--
    Destino do middleware `organization` quando o usuário não pertence a
    nenhuma organização (SAD 20.2), e do link "+ Create new organization" do
    seletor de organização.

    O formulário de criação pertence ao fluxo de organizações e ainda não
    existe; esta tela é o destino real do redirecionamento enquanto isso.
    Texto em inglês por ser tela de cliente final (CLAUDE.md regra 25).
--}}

@section('content')
    <div class="mx-auto max-w-xl py-16 text-center">
        <h1 class="font-serif text-3xl tracking-tight text-gray-900 dark:text-[#f4f4f6]">Create your organization</h1>

        <p class="mt-4 text-[13px] leading-relaxed text-gray-500 dark:text-[#9e6136]">
            Every brand lives inside an organization — the entity that holds your plan,
            your credits and your team. Creating organizations is not available yet.
        </p>
    </div>
@endsection
