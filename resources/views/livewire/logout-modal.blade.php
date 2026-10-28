{{--
    Confirmação de logout (SAD 10.13).

    Não há `logout-modal.html` entre os mockups vigentes: o cartão segue o
    mesmo desenho do switch-organization-modal.html (overlay, cartão, cabeçalho
    em Newsreader, rodapé de ações) e a mesma transição de opacidade e escala.

    Aberto pelo evento de janela `open-logout-modal` — da Sidebar
    (#logout-btn) e do menu do perfil. A saída é um formulário comum para
    POST /logout (SAD M.3); antes do envio, a sessão do Firebase no navegador
    também é encerrada.
--}}
<div>
  <div id="logout-modal"
       x-data="{ open: false, leaving: false }"
       @open-logout-modal.window="open = true"
       @keydown.escape.window="open = false"
       x-show="open" x-cloak
       x-transition:enter="transition-opacity duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
       x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
       class="fixed inset-0 z-[100] flex items-center justify-center transition-opacity duration-300"
       role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
    <div @click="open = false" class="absolute inset-0 bg-gray-900/40 dark:bg-[#121214]/80 backdrop-blur-sm transition-colors"></div>
    <div x-show="open"
         x-transition:enter="transition-all duration-300" x-transition:enter-start="scale-95" x-transition:enter-end="scale-100"
         x-transition:leave="transition-all duration-300" x-transition:leave-start="scale-100" x-transition:leave-end="scale-95"
         class="relative bg-white dark:bg-[#1c1c1c] border border-gray-200 dark:border-[#6b4e3a]/30 rounded-2xl shadow-xl w-[90%] max-w-[400px] transform transition-all duration-300 flex flex-col">

      <div class="p-5">
        <h3 id="logout-modal-title" class="font-newsreader text-2xl font-bold text-gray-900 dark:text-[#f4f4f6] tracking-tight">Log out</h3>
        <p class="text-[13px] text-gray-500 dark:text-[#f4f4f6]/60 mt-1 leading-relaxed">Are you sure you want to log out of Arkion?</p>
      </div>

      <form method="POST" action="{{ route('logout') }}"
            @submit.prevent="if (leaving) return; leaving = true; await window.signOutOfFirebase?.(); $el.submit()"
            class="p-4 border-t border-gray-200 dark:border-[#6b4e3a]/30 flex items-center justify-end gap-2 rounded-b-2xl bg-gray-50/50 dark:bg-[#121214]/50">
        @csrf
        <button type="button" @click="open = false" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-200 dark:border-[#6b4e3a]/30 bg-white dark:bg-[#1c1c1c] text-gray-700 dark:text-[#f4f4f6] hover:bg-[#6b4e3a]/20 dark:hover:bg-[#6b4e3a]/20 transition-colors focus:outline-none">
          Cancel
        </button>
        <button type="submit" :disabled="leaving" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#d2691e] text-white hover:brightness-90 transition focus:outline-none focus:ring-2 focus:ring-[#d2691e] disabled:opacity-50">
          <span x-show="! leaving">Log out</span>
          <span x-show="leaving" x-cloak>Logging out...</span>
        </button>
      </form>
    </div>
  </div>
</div>
