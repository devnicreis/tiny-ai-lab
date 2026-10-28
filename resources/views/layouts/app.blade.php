<!DOCTYPE html>
{{-- Sem classe de tema: o Light é o padrão e o Dark é só a variante `dark:`
     (CLAUDE.md regra 20). A preferência salva, se houver, é aplicada pelo
     script abaixo antes da primeira pintura. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Arkion' }}</title>

    <script>
        try {
            if (window.localStorage.getItem('arkion.theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {
            // Sem acesso ao localStorage, o tema claro padrão permanece.
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Firebase ID Token corrente. Nunca renderizado pelo servidor: o token
         pertence ao cliente e é preenchido por resources/js/firebase.js
         (onIdTokenChanged), conforme a regra de nunca cachear/persistir
         tokens do Firebase. --}}
    <script>
        window.__FIREBASE_TOKEN__ = '';
        window.__FIREBASE_CONFIG__ = @json(config('firebase.public'));
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{-- Shell autenticado (SAD 10.8): Sidebar e Header persistentes, conteúdo
     da página no <main>. Mockups: sidebar_v3.html e header_3.0.html. --}}
<body class="bg-gray-50 dark:bg-[#121214] text-gray-500 dark:text-[#A8A29E] font-sans antialiased h-screen flex flex-col md:flex-row overflow-hidden transition-colors duration-300">

    <livewire:sidebar :current-route="Route::currentRouteName()" />

    <div class="flex min-w-0 flex-1 flex-col h-full">
        <livewire:header />

        <main class="flex-1 bg-gray-50 dark:bg-[#121214] p-4 md:p-8 overflow-y-auto z-0 transition-colors duration-300">
            {{-- O shell atende às duas formas de uso: `$slot` para quem o
                 consome como componente/layout Livewire e `@yield` para as
                 páginas de resources/views/pages que fazem @extends. --}}
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    <livewire:switch-organization-modal />
    <livewire:logout-modal />

    {{-- Flag desligada: o modal nem é montado (Sessão 3.2). --}}
    @if (config('arkion.features.referral'))
        <livewire:referral-modal />
    @endif

    @livewireScriptConfig
</body>
</html>
