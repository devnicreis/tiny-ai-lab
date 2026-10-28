{{--
    Seletor de organização (mockup .claude/docs/mockups/switch-organization-modal.html).

    Modal: aberto pelo evento de janela `open-switch-organization`, disparado
    pelo menu da marca no Header. Abrir e fechar são Alpine, com a transição de
    opacidade e escala (95 → 100) do mockup.

    `inline`: o mesmo cartão, sem overlay nem botão de fechar, como conteúdo da
    página /organizations/select.
--}}
<div>
  @if ($inline)
    <div class="relative bg-white dark:bg-[#1c1c1c] border border-gray-200 dark:border-[#6b4e3a]/30 rounded-2xl shadow-xl w-full max-w-[480px] mx-auto flex flex-col">
      <!-- Header -->
      <div class="p-5 border-b border-gray-200 dark:border-[#6b4e3a]/30 flex items-start justify-between">
        <div>
          <h3 class="font-newsreader text-2xl font-bold text-gray-900 dark:text-[#f4f4f6] tracking-tight">Switch Organization</h3>
          <p class="text-sm text-gray-500 dark:text-[#f4f4f6]/60 mt-1 leading-relaxed">Select an active organization to switch your workspace.</p>
        </div>
      </div>

      @include('livewire.partials.organization-list')
    </div>
  @else
  <!-- Switch Organization Modal -->
  <div id="switch-organization-modal"
       x-data="{ open: false }"
       @open-switch-organization.window="open = true"
       @keydown.escape.window="open = false"
       x-show="open" x-cloak
       x-transition:enter="transition-opacity duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
       x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
       class="fixed inset-0 z-[100] flex items-center justify-center transition-opacity duration-300"
       role="dialog" aria-modal="true" aria-labelledby="switch-organization-title">
    <div @click="open = false" class="absolute inset-0 bg-gray-900/40 dark:bg-[#121214]/80 backdrop-blur-sm modal-overlay transition-colors"></div>
    <div x-show="open"
         x-transition:enter="transition-all duration-300" x-transition:enter-start="scale-95" x-transition:enter-end="scale-100"
         x-transition:leave="transition-all duration-300" x-transition:leave-start="scale-100" x-transition:leave-end="scale-95"
         class="relative bg-white dark:bg-[#1c1c1c] border border-gray-200 dark:border-[#6b4e3a]/30 rounded-2xl shadow-xl w-[90%] max-w-[480px] transform transition-all duration-300 flex flex-col">

      <!-- Header -->
      <div class="p-5 border-b border-gray-200 dark:border-[#6b4e3a]/30 flex items-start justify-between">
        <div>
          <h3 id="switch-organization-title" class="font-newsreader text-2xl font-bold text-gray-900 dark:text-[#f4f4f6] tracking-tight">Switch Organization</h3>
          <p class="text-sm text-gray-500 dark:text-[#f4f4f6]/60 mt-1 leading-relaxed">Select an active organization to switch your workspace.</p>
        </div>
        <button id="close-modal-btn" type="button" @click="open = false" class="p-1.5 text-gray-400 hover:text-gray-700 dark:text-[#f4f4f6]/60 dark:hover:text-[#f4f4f6] bg-gray-50 hover:bg-gray-100 dark:bg-[#121214] dark:hover:bg-[#292524] rounded-lg transition-colors focus:outline-none shrink-0" aria-label="Close">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>

      @include('livewire.partials.organization-list')
    </div>
  </div>
  @endif
</div>
