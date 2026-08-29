<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Arkion' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen items-center justify-center bg-app-bg font-sans antialiased text-[#FAFAFA]">
    <div class="w-full max-w-md rounded-xl border border-[#292524] bg-app-card p-8 shadow-lg">
        <div class="mb-6 flex items-center justify-center gap-2">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-500 text-sm font-bold text-white">T</div>
            <span class="text-lg font-semibold text-[#FAFAFA]">Tiny AI Lab</span>
        </div>

        {{ $slot ?? '' }}
    </div>

    @livewireScripts
</body>
</html>
