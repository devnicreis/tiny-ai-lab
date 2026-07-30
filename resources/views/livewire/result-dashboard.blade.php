{{-- Estrutura funcional: o objetivo aqui é apenas tornar os dados do `result`
     visíveis e navegáveis. O design vem em sessão posterior. --}}
<div class="space-y-4">

    @if ($this->execution->status !== 'completed')
        <div class="rounded-lg border border-[#292524] bg-app-card p-4">
            <p class="text-sm text-[#A8A29E]">
                O resultado aparece aqui quando a execução for concluída.
            </p>
        </div>
    @elseif ($this->sections === [])
        <div class="rounded-lg border border-[#292524] bg-app-card p-4">
            <p class="text-sm text-[#A8A29E]">A execução concluiu sem conteúdo no resultado.</p>
        </div>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach ($this->sections as $section)
                <button type="button" wire:click="selectSection('{{ $section }}')"
                        class="rounded-lg border px-3 py-1.5 text-sm {{ $this->currentSection === $section ? 'border-orange-500 text-orange-500' : 'border-[#292524] text-[#A8A29E] hover:text-[#FAFAFA]' }}">
                    {{ $section }}
                </button>
            @endforeach
        </div>

        <div class="rounded-lg border border-[#292524] bg-app-card p-4">
            <h2 class="mb-3 text-sm font-semibold text-[#FAFAFA]">{{ $this->currentSection }}</h2>

            @php $content = $this->result[$this->currentSection] ?? null; @endphp

            @if (is_scalar($content))
                <p class="whitespace-pre-line text-sm text-[#FAFAFA]">{{ $content }}</p>
            @else
                {{-- Sem contrato documentado por pipeline, o JSON é exibido cru:
                     estrutura visível hoje, componentes dedicados depois. --}}
                <pre class="overflow-x-auto text-xs text-[#A8A29E]">{{ json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </div>
    @endif
</div>
