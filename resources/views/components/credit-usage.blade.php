{{--
    Indicador de consumo do plano (mockups sidebar_v3.html e header_3.0.html).

    A barra é o consumo do plano da organização no mês, contra o teto mensal
    de `plan_limits` — nunca a soma das carteiras (CLAUDE.md regra 27). Sem
    teto, só o número; sem plano, "No active plan". Ao passar o mouse, o CTA
    por papel (SAD 12.12). Comprar e assinar ainda não existem: o CTA fica
    desabilitado, com "Coming soon".

    `sidebar`: dentro do #credits-container, que já é o `group` do hover.
    `profile`: bloco do menu do perfil, que é o próprio `group`.
--}}
@props(['summary', 'variant' => 'sidebar'])

@php
    $isSidebar = $variant === 'sidebar';
    $ctaAttributes = $summary->ctaIsAction() ? 'aria-disabled="true" title="Coming soon"' : '';
@endphp

<div @class([
        'w-full flex flex-col',
        'sidebar-text-flex transition-opacity' => $isSidebar,
        'group cursor-pointer' => ! $isSidebar,
    ])
    @unless ($isSidebar) title="{{ $summary->walletsLabel() }}" @endunless>
  @if ($summary->hasLimit())
  <div class="w-full h-1.5 bg-gray-200 dark:bg-[#6b4e3a]/30 rounded-t-md overflow-hidden flex items-center shrink-0 transition-colors group-hover:bg-gray-300 dark:group-hover:bg-[#554f49]">
    <div class="h-full bg-[#d2691e] transition-all duration-700 ease-out group-hover:brightness-110" style="width: {{ $summary->usagePercent() }}%;"></div>
  </div>
  @endif
  <div @class([
          'w-full bg-gray-100 dark:bg-[#f4f4f6] px-2 text-center shrink-0 transition-colors',
          'rounded-b-md' => $summary->hasLimit(),
          'rounded-md' => ! $summary->hasLimit(),
          'py-1' => $isSidebar,
          'py-1.5' => ! $isSidebar,
      ])>
    <span @class([
            'text-gray-700 dark:text-[#27272a] text-[11px] font-bold group-hover:hidden transition-opacity',
            'block' => ! $isSidebar,
        ])>{{ $summary->usageLabel() }}</span>
    <span {!! $ctaAttributes !!} @class([
            'text-[#d2691e] text-[11px] font-bold hidden transition-opacity',
            'group-hover:inline-block' => $isSidebar,
            'group-hover:block' => ! $isSidebar,
        ])>{{ $summary->ctaLabel() }}</span>
  </div>
</div>
