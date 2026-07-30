@extends('layouts.app')

@section('content')
    <div class="max-w-4xl space-y-6">
        {{-- Só metadados: acompanha o estado via Reverb. --}}
        <livewire:execution-status :execution-id="$execution->id" />

        {{-- SAD M.7: `result` é JSONB grande e nunca entra na renderização
             inicial da página — carrega sob demanda, com placeholder. --}}
        <livewire:result-dashboard :execution-id="$execution->id" lazy />
    </div>
@endsection
