@extends('layouts.app')

{{--
    Destino do middleware `organization` quando o usuário pertence a mais de uma
    organização e nenhuma está ativa (SAD 20.5). Escolher por ele decidiria de
    qual carteira sairia o crédito da próxima execução.

    O seletor pertence ao fluxo de organizações e ainda não existe; esta tela é
    o destino real do redirecionamento enquanto isso. Texto em inglês por ser
    tela de cliente final (CLAUDE.md regra 25).
--}}

@section('content')
    <div class="mx-auto max-w-xl py-16 text-center">
        <h1 class="font-serif text-3xl">Select an organization</h1>

        <p class="mt-4 text-sm text-[#A8A29E]">
            You belong to more than one organization. Choose which one to work in —
            it defines the brands you see and the credits you spend.
        </p>
    </div>
@endsection
