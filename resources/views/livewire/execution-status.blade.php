{{-- O polling existe apenas como fallback de Reverb (SAD M.5) e desliga por
     completo quando o estado é final: nada mais vai mudar. --}}
<div @if (! $this->isTerminal()) wire:poll.5s="refreshStatus" @endif
     class="rounded-lg border border-[#292524] bg-app-card p-4">

    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-[11px] uppercase tracking-wide text-[#A8A29E]">Execução #{{ $executionId }}</p>
            <p class="text-sm text-[#FAFAFA]">{{ $pipelineType }}</p>
        </div>

        @php
            $badge = match ($status) {
                'queued' => 'border-[#292524] text-[#A8A29E]',
                'processing' => 'border-orange-500 text-orange-500',
                'completed' => 'border-green-500 text-green-500',
                'failed' => 'border-red-500 text-red-400',
                default => 'border-[#292524] text-[#A8A29E]',
            };
        @endphp

        <span class="rounded-full border px-3 py-1 text-xs font-medium {{ $badge }}">
            {{ $status }}
        </span>
    </div>

    {{-- Estados visuais: queued → processing → completed/failed. --}}
    <ol class="mt-4 flex items-center gap-2 text-xs text-[#A8A29E]">
        <li class="{{ $status === 'queued' ? 'text-[#FAFAFA]' : '' }}">Na fila</li>
        <li>→</li>
        <li class="{{ $status === 'processing' ? 'text-[#FAFAFA]' : '' }}">Processando</li>
        <li>→</li>
        <li class="{{ $this->isTerminal() ? 'text-[#FAFAFA]' : '' }}">
            {{ $status === 'failed' ? 'Falhou' : 'Concluído' }}
        </li>
    </ol>

    @if ($status === 'failed' && $errorMessage)
        <p class="mt-3 text-sm text-red-400">{{ $errorMessage }}</p>
    @endif
</div>
