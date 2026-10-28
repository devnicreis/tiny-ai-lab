{{--
    Modal de indicação (mockup .claude/docs/mockups/modal_referral.html), atrás
    de `config('arkion.features.referral')`, desligada por padrão.

    Sem backend e sem dado fictício: o mockup traz um link de exemplo
    (`ARK-ORG92`), um QR code simulado, mensagens de compartilhamento que
    prometem "30 bonus credits", contadores de indicação e uma imagem de
    mascote que não existe no projeto. Nada disso foi copiado. O link, o QR e
    o histórico mostram estado vazio, e os botões de compartilhar ficam
    desabilitados até existir um código de verdade (SAD 10.16).
--}}
<div>
  @if ($this->enabled)
  @php
      $comingSoon = 'aria-disabled="true" title="Coming soon"';
  @endphp
  <div id="referral-modal"
       x-data="{ open: false, tab: 'web' }"
       @open-referral-modal.window="open = true"
       @keydown.escape.window="open = false"
       x-show="open" x-cloak
       class="fixed inset-0 z-[100]"
       role="dialog" aria-modal="true" aria-labelledby="referral-modal-title">
    <!-- Backdrop -->
    <div id="referral-backdrop" x-show="open" @click="open = false"
         x-transition:enter="transition-opacity duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity cursor-pointer"></div>

    <!-- Modal Container -->
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
      <div id="referral-card" x-show="open"
           x-transition:enter="transition-all duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="transition-all duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
           class="bg-white dark:bg-[#121214] w-full max-w-lg rounded-3xl shadow-2xl border border-gray-200 dark:border-neutral-800 flex flex-col overflow-hidden transform transition-all duration-300 pointer-events-auto relative">

        <!-- Header -->
        <div class="relative pt-8 px-8 pb-6 text-center">
          <button id="referral-close-btn" type="button" @click="open = false" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 dark:bg-neutral-800 text-gray-500 dark:text-neutral-400 hover:text-gray-900 dark:hover:text-white transition-colors" aria-label="Close">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>

          <h2 id="referral-modal-title" class="font-newsreader text-2xl font-bold text-gray-900 dark:text-white mb-2">Save a friend from bad marketing</h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed max-w-sm mx-auto">Good strategists don't let colleagues waste hours on generic copy. Share Arkion and unlock bonus credits for both your friend and this organization.</p>
        </div>

        <!-- Tabs Nav -->
        <div class="flex border-b border-gray-200 dark:border-neutral-800 px-8">
          <button type="button" @click="tab = 'web'" :class="tab === 'web' ? 'font-semibold border-[#d2691e] text-[#d2691e]' : 'font-medium border-transparent text-gray-500 dark:text-neutral-500 hover:text-gray-900 dark:hover:text-white'" class="referral-tab flex-1 py-3 text-sm border-b-2 transition-colors">Web / Direct Link</button>
          <button type="button" @click="tab = 'mobile'" :class="tab === 'mobile' ? 'font-semibold border-[#d2691e] text-[#d2691e]' : 'font-medium border-transparent text-gray-500 dark:text-neutral-500 hover:text-gray-900 dark:hover:text-white'" class="referral-tab flex-1 py-3 text-sm border-b-2 transition-colors">Mobile (QR Code)</button>
        </div>

        <!-- Tab Content: Web -->
        <div id="tab-web" x-show="tab === 'web'" class="p-8 pb-6">
          <div class="flex gap-2 mb-6">
            <input type="text" id="referral-link" value="" readonly placeholder="Your referral link will appear here" class="flex-1 bg-gray-50 dark:bg-[#1a1a1c] border border-gray-200 dark:border-neutral-800 rounded-lg px-3 py-2 text-sm font-medium text-gray-900 dark:text-white focus:outline-none transition-colors">
            <button id="copy-link-btn" type="button" {!! $comingSoon !!} class="px-4 py-2 bg-[#121214] dark:bg-white text-white dark:text-[#121214] font-bold text-sm rounded-lg hover:bg-neutral-800 dark:hover:bg-gray-200 transition-colors whitespace-nowrap opacity-50 cursor-not-allowed">Copy Link</button>
          </div>

          <div class="grid grid-cols-4 gap-3">
            <a {!! $comingSoon !!} aria-label="Share on WhatsApp" class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl bg-[#d2691e]/10 text-[#25D366] hover:bg-[#d2691e]/20 transition-colors opacity-50 cursor-not-allowed">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
            </a>
            <a {!! $comingSoon !!} aria-label="Share on LinkedIn" class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl bg-[#0A66C2]/10 text-[#0A66C2] hover:bg-[#0A66C2]/20 transition-colors opacity-50 cursor-not-allowed">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </a>
            <a {!! $comingSoon !!} aria-label="Share on X" class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl bg-gray-100 dark:bg-neutral-800 text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-neutral-700 transition-colors opacity-50 cursor-not-allowed">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            <a {!! $comingSoon !!} aria-label="Share by email" class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 transition-colors opacity-50 cursor-not-allowed">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            </a>
          </div>
        </div>

        <!-- Tab Content: Mobile -->
        <div id="tab-mobile" x-show="tab === 'mobile'" x-cloak class="p-8 pb-6 flex flex-col items-center">
          <div class="p-3 bg-white border border-gray-200 dark:border-neutral-700 rounded-xl mb-3 shadow-sm inline-flex w-32 h-32 items-center justify-center box-content">
            <span class="text-xs text-gray-500 text-center">QR code unavailable</span>
          </div>
          <p class="text-xs text-gray-500 dark:text-neutral-500 text-center max-w-[200px]">Scan with your phone to open and share your invite link directly from your mobile apps.</p>
        </div>

        <!-- Footer -->
        <div class="px-8 py-4 bg-gray-50 dark:bg-[#1a1a1c] border-t border-gray-200 dark:border-neutral-800 flex items-center justify-between">
          <span class="text-xs font-semibold text-gray-600 dark:text-neutral-400">
            No referrals yet
          </span>
          <button type="button" {!! $comingSoon !!} class="text-xs font-bold text-gray-900 dark:text-white hover:text-[#d2691e] dark:hover:text-[#d2691e] flex items-center gap-1.5 transition-colors cursor-not-allowed">
            View Referral History
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
  @endif
</div>
