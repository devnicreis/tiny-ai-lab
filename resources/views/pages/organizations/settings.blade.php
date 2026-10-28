@extends('layouts.app')

{{--
    Destino de "Manage all organizations" no seletor de organização
    (Sessão 3.2). A gestão de organizações — membros, papéis, convites,
    transferência de posse — ainda não tem tela (SAD 20.8); esta página diz
    exatamente isso, sem simular controles que não funcionam.
--}}

@section('content')
    <div class="mx-auto max-w-xl py-16 text-center">
        <h1 class="font-serif text-3xl tracking-tight text-gray-900 dark:text-[#f4f4f6]">Organizations</h1>

        <p class="mt-4 text-[13px] leading-relaxed text-gray-500 dark:text-[#9e6136]">
            Managing organizations is not available yet. To change the organization
            you are working in, use Switch Organization in the brand menu.
        </p>
    </div>
@endsection
