<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Tiny AI Lab' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-app-bg text-[#FAFAFA]" x-data="{ mobileOpen: false }">

    <div id="mobile-overlay" x-show="mobileOpen" x-cloak @click="mobileOpen = false"
         class="fixed inset-0 z-30 bg-black/60 lg:hidden"></div>

    <div class="flex h-screen overflow-hidden">
        <aside id="sidebar"
               :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#1C1917] transition-transform duration-200 ease-in-out lg:static lg:translate-x-0">

            <div class="flex h-16 items-center gap-2 border-b border-[#292524] px-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500 text-sm font-bold text-white">T</div>
                <span class="text-[15px] font-semibold text-[#FAFAFA]">Tiny AI Lab</span>
            </div>

            <div id="client-dropdown-container" class="relative border-b border-[#292524] p-3" x-data="{ open: false }">
                <button id="client-dropdown-btn" @click="open = !open"
                        class="flex w-full items-center justify-between rounded-lg border border-[#292524] bg-app-card px-3 py-2 text-left text-sm text-[#FAFAFA] hover:bg-[#44403C]">
                    <span class="truncate">Selecionar Brand</span>
                    <svg class="h-4 w-4 shrink-0 text-[#A8A29E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute left-0 right-0 z-50 mt-2 rounded-lg border border-[#292524] bg-app-card p-1 shadow-lg">
                    <p class="px-2 py-1.5 text-[11px] text-[#A8A29E]">Nenhuma Brand disponível</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[#FAFAFA] hover:bg-[#44403C]">
                    <span>Dashboard</span>
                </a>
                <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                    <span>Briefing</span>
                </a>
                <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                    <span>Histórico</span>
                </a>
                <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                    <span>Planos</span>
                </a>
            </nav>

            <div class="border-t border-[#292524] p-3" x-data="{ helpOpen: false }">
                <div class="mb-2 rounded-lg border border-[#292524] bg-app-card px-3 py-2">
                    <p class="text-[11px] text-[#A8A29E]">Créditos disponíveis</p>
                    <p class="text-sm font-semibold text-[#FAFAFA]">--</p>
                </div>

                <button id="theme-toggle-btn"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                    <span>Alternar tema</span>
                </button>

                <div class="relative">
                    <button id="help-btn" @click="helpOpen = !helpOpen"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                        <span>Ajuda</span>
                    </button>
                    <div id="help-submenu" x-show="helpOpen" x-cloak @click.outside="helpOpen = false"
                         class="absolute bottom-full left-0 right-0 z-50 mb-2 rounded-lg border border-[#292524] bg-app-card p-1 shadow-lg">
                        <a href="#" class="block rounded-md px-3 py-2 text-sm text-[#FAFAFA] hover:bg-[#44403C]">Central de Ajuda</a>
                        <a href="#" class="block rounded-md px-3 py-2 text-sm text-[#FAFAFA] hover:bg-[#44403C]">Configurações</a>
                    </div>
                </div>

                <button id="logout-btn"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                    <span>Sair</span>
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 shrink-0 items-center gap-4 border-b border-[#292524] bg-app-card px-4">
                <button id="toggle-btn" @click="mobileOpen = !mobileOpen"
                        class="rounded-lg p-2 text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA] lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div id="search-container" class="flex flex-1 items-center">
                    <input type="text" placeholder="Buscar..."
                           class="w-full max-w-sm rounded-lg border border-[#292524] bg-app-bg px-3 py-1.5 text-sm text-[#FAFAFA] placeholder:text-[#A8A29E] focus:border-orange-500 focus:outline-none">
                </div>

                <button id="btn-ask-ai"
                        class="flex items-center gap-2 rounded-lg bg-orange-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-[#E64D00]">
                    <span>Ask AI</span>
                </button>

                <div id="notif-dropdown-container" class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="rounded-lg p-2 text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 z-50 mt-2 w-64 rounded-lg border border-[#292524] bg-app-card p-2 shadow-lg">
                        <p class="px-2 py-1.5 text-[11px] text-[#A8A29E]">Nenhuma notificação</p>
                    </div>
                </div>

                <div id="lang-dropdown-container" class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="rounded-lg px-2 py-1.5 text-sm text-[#A8A29E] hover:bg-[#44403C] hover:text-[#FAFAFA]">
                        PT-BR
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 z-50 mt-2 w-32 rounded-lg border border-[#292524] bg-app-card p-1 shadow-lg">
                        <a href="#" class="block rounded-md px-3 py-2 text-sm text-[#FAFAFA] hover:bg-[#44403C]">Português</a>
                        <a href="#" class="block rounded-md px-3 py-2 text-sm text-[#FAFAFA] hover:bg-[#44403C]">English</a>
                    </div>
                </div>

                <div id="profile-dropdown-container" class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-sm font-semibold text-white">
                        U
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 z-50 mt-2 w-48 rounded-lg border border-[#292524] bg-app-card p-1 shadow-lg">
                        <a href="#" class="block rounded-md px-3 py-2 text-sm text-[#FAFAFA] hover:bg-[#44403C]">Perfil</a>
                        <a href="#" class="block rounded-md px-3 py-2 text-sm text-[#FAFAFA] hover:bg-[#44403C]">Configurações</a>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-app-bg p-6">
                {{ $slot ?? '' }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
