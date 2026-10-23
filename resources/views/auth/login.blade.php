@extends('layouts.guest')

{{--
    Tela de login web (Sessão 3.1). Troca o Firebase ID Token por uma sessão
    Laravel convencional — navegação de browser não envia
    `Authorization: Bearer`, então as demais rotas web nunca dependem do
    token diretamente (SAD 5). Texto em inglês por ser tela de cliente final
    (CLAUDE.md regra 25).
--}}

@section('content')
    <div class="text-center">
        <h1 class="font-serif text-2xl text-[#FAFAFA]">Sign in to Arkion</h1>
        <p class="mt-2 text-sm text-[#A8A29E]">Use your Google account to continue.</p>
    </div>

    <button
        type="button"
        id="google-signin-btn"
        x-data
        @click="
            $el.disabled = true;
            window.signInWithGoogle()
                .then((redirect) => { window.location.href = redirect; })
                .catch((error) => {
                    $el.disabled = false;
                    alert(error.message ?? 'Sign-in failed.');
                });
        "
        class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg border border-[#292524] bg-app-card px-4 py-2.5 text-sm font-medium text-[#FAFAFA] hover:bg-[#44403C] disabled:opacity-50"
    >
        <svg class="h-4 w-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="0.3" fill="none">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.35 11.1h-9.17v2.98h5.28c-.23 1.44-1.6 4.23-5.28 4.23-3.18 0-5.78-2.63-5.78-5.87s2.6-5.87 5.78-5.87c1.81 0 3.02.77 3.72 1.44l2.53-2.44C16.86 3.8 14.72 2.8 12.18 2.8 6.98 2.8 2.78 7 2.78 12.19s4.2 9.39 9.4 9.39c5.43 0 9.03-3.82 9.03-9.2 0-.62-.07-1.09-.16-1.28Z" />
        </svg>
        <span>Sign in with Google</span>
    </button>
@endsection
