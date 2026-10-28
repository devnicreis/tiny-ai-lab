{{--
    Lista de organizações do SwitchOrganizationModal, compartilhada pelo modal
    e pela página /organizations/select (mockup switch-organization-modal.html).

    Os dados vêm de `SwitchOrganizationModal::organizations()`; a troca é
    `switchTo()`, que delega a validação a `OrganizationContext::set()`.
--}}
      <!-- Body: Organization List -->
      <div class="p-3 space-y-1">
        @forelse ($this->organizations as $organization)
          @if ($organization['current'])
        <!-- Active Org -->
        <button type="button" wire:key="organization-{{ $organization['id'] }}" @if (! $inline) @click="open = false" @endif aria-current="true" class="w-full text-left group flex items-center justify-between p-3 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-[#6b4e3a]/10 dark:hover:bg-[#6b4e3a]/20 border border-gray-200 dark:border-[#6b4e3a]/30 transition-colors focus:outline-none">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gray-200 text-gray-800 dark:bg-[#d2691e]/20 dark:text-[#d2691e] flex items-center justify-center font-bold text-sm shrink-0">
              {{ $organization['initials'] }}
            </div>
            <div class="flex flex-col">
              <span class="text-sm font-semibold text-gray-900 dark:text-[#f4f4f6]">{{ $organization['name'] }}</span>
              <span class="text-[11px] text-gray-500 dark:text-[#f4f4f6]/60 mt-0.5">{{ $organization['role'] }} • {{ $organization['brands'] }} {{ Str::plural('brand', $organization['brands']) }}</span>
            </div>
          </div>
          <div class="shrink-0 flex items-center justify-center">
            <span class="text-[10px] font-medium tracking-wide bg-gray-200 dark:bg-[#d2691e]/20 text-gray-600 dark:text-[#d2691e] px-2 py-0.5 rounded-full uppercase">Current</span>
          </div>
        </button>
          @else
        <button type="button" wire:key="organization-{{ $organization['id'] }}" wire:click="switchTo({{ $organization['id'] }})" wire:loading.attr="disabled" class="w-full text-left group flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-[#6b4e3a]/20 border border-transparent transition-colors focus:outline-none">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gray-100 text-gray-600 dark:bg-[#121214] dark:text-[#f4f4f6]/80 flex items-center justify-center font-semibold text-sm shrink-0 border border-gray-200 dark:border-[#292524]">
              {{ $organization['initials'] }}
            </div>
            <div class="flex flex-col">
              <span class="text-sm font-medium text-gray-900 dark:text-[#f4f4f6]">{{ $organization['name'] }}</span>
              <span class="text-[11px] text-gray-500 dark:text-[#f4f4f6]/60 mt-0.5">{{ $organization['role'] }} • {{ $organization['brands'] }} {{ Str::plural('brand', $organization['brands']) }}</span>
            </div>
          </div>
          <div class="shrink-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pr-1">
            <svg class="w-4 h-4 text-gray-400 dark:text-[#f4f4f6]/60 group-hover:text-[#d2691e] dark:group-hover:text-[#d2691e] transition-colors" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </div>
        </button>
          @endif
        @empty
        <p class="p-3 text-sm text-gray-500 dark:text-[#f4f4f6]/60">You don't belong to any organization yet.</p>
        @endforelse
      </div>

      <!-- Footer Actions -->
      <div class="p-4 border-t border-gray-200 dark:border-[#6b4e3a]/30 flex items-center justify-between rounded-b-2xl bg-gray-50/50 dark:bg-[#121214]/50">
        <a href="{{ route('organizations.settings') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-[#f4f4f6]/80 hover:text-[#d2691e] dark:hover:text-[#d2691e] transition-colors group">
          Manage all organizations
          <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
        <a href="{{ route('organizations.create') }}" class="inline-flex items-center text-xs font-semibold text-gray-500 dark:text-[#f4f4f6]/60 hover:text-[#d2691e] dark:hover:text-[#d2691e] transition-colors">
          + Create new organization
        </a>
      </div>
