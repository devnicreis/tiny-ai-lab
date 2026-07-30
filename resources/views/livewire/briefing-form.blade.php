{{-- Estrutura funcional (página esqueleto): exibe e submete os dados corretos.
     O refinamento visual do Design System vem em sessão posterior. --}}
<div class="max-w-2xl space-y-6">

    <div>
        <h1 class="text-lg font-semibold text-[#FAFAFA]">Novo Briefing</h1>
        <p class="text-sm text-[#A8A29E]">Descreva o pedido; o pipeline roda em segundo plano.</p>
    </div>

    @if ($upgradeMessage)
        <div class="rounded-lg border border-orange-500 bg-app-card p-4">
            <p class="text-sm text-[#FAFAFA]">{{ $upgradeMessage }}</p>
            <a href="#" class="mt-3 inline-flex rounded-lg bg-orange-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-[#E64D00]">
                Buy AI Credits
            </a>
        </div>
    @endif

    {{-- SAD M.3: submissão sempre por <form>, nunca por botão isolado. --}}
    <form wire:submit="submit" class="space-y-4">

        <div>
            <label for="project_id" class="mb-1 block text-sm font-medium text-[#FAFAFA]">Brand</label>
            <select id="project_id" wire:model.defer="project_id"
                    class="w-full rounded-lg border border-[#292524] bg-app-bg px-3 py-2 text-sm text-[#FAFAFA] focus:border-orange-500 focus:outline-none">
                <option value="">Selecione a Brand</option>
                @foreach ($this->brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->brand_name }}</option>
                @endforeach
            </select>
            @error('project_id')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="pipeline_type" class="mb-1 block text-sm font-medium text-[#FAFAFA]">Pipeline</label>
            <select id="pipeline_type" wire:model.defer="pipeline_type"
                    class="w-full rounded-lg border border-[#292524] bg-app-bg px-3 py-2 text-sm text-[#FAFAFA] focus:border-orange-500 focus:outline-none">
                @foreach (\App\Models\Execution::PIPELINE_TYPES as $pipeline)
                    <option value="{{ $pipeline }}">{{ $pipeline }}</option>
                @endforeach
            </select>
            @error('pipeline_type')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="objective" class="mb-1 block text-sm font-medium text-[#FAFAFA]">Objetivo</label>
            {{-- SAD M.2: `.defer` é o padrão de formulário completo — tudo sobe no submit. --}}
            <textarea id="objective" rows="4" wire:model.defer="objective"
                      class="w-full rounded-lg border border-[#292524] bg-app-bg px-3 py-2 text-sm text-[#FAFAFA] placeholder:text-[#A8A29E] focus:border-orange-500 focus:outline-none"
                      placeholder="O que este briefing precisa entregar?"></textarea>
            @error('objective')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="target_audience" class="mb-1 block text-sm font-medium text-[#FAFAFA]">Público-alvo</label>
            <textarea id="target_audience" rows="3" wire:model.defer="target_audience"
                      class="w-full rounded-lg border border-[#292524] bg-app-bg px-3 py-2 text-sm text-[#FAFAFA] placeholder:text-[#A8A29E] focus:border-orange-500 focus:outline-none"></textarea>
            @error('target_audience')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tone" class="mb-1 block text-sm font-medium text-[#FAFAFA]">Tom de voz</label>
                <input id="tone" type="text" wire:model.defer="tone"
                       class="w-full rounded-lg border border-[#292524] bg-app-bg px-3 py-2 text-sm text-[#FAFAFA] focus:border-orange-500 focus:outline-none">
                @error('tone')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="language" class="mb-1 block text-sm font-medium text-[#FAFAFA]">Idioma</label>
                <input id="language" type="text" wire:model.defer="language"
                       class="w-full rounded-lg border border-[#292524] bg-app-bg px-3 py-2 text-sm text-[#FAFAFA] focus:border-orange-500 focus:outline-none">
                @error('language')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="notes" class="mb-1 block text-sm font-medium text-[#FAFAFA]">Observações</label>
            <textarea id="notes" rows="3" wire:model.defer="notes"
                      class="w-full rounded-lg border border-[#292524] bg-app-bg px-3 py-2 text-sm text-[#FAFAFA] focus:border-orange-500 focus:outline-none"></textarea>
            @error('notes')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- SAD M.4: ação que consome créditos nunca fica sem loading state —
             é o que impede clique duplo e débito duplicado. --}}
        <button type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-[#E64D00] disabled:opacity-60">
            <span wire:loading.remove wire:target="submit">Executar pipeline</span>
            <span wire:loading wire:target="submit">Enviando...</span>
        </button>

    </form>
</div>
