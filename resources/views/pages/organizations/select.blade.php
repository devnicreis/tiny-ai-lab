@extends('layouts.app')

{{--
    Destino do middleware `organization` quando o usuário pertence a mais de uma
    organização e nenhuma está ativa (SAD 20.5). Escolher por ele decidiria de
    qual carteira sairia o crédito da próxima execução.

    A lista e a troca são as do SwitchOrganizationModal, em modo `inline`: uma
    só implementação para o modal do Header e para esta página (Sessão 3.2).
--}}

@section('content')
    <div class="mx-auto max-w-xl py-16">
        <livewire:switch-organization-modal inline />
    </div>
@endsection
