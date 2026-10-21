<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\SyncFirebaseUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthenticateWebFirebaseRequest;
use App\Services\FirebaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ponte entre a identidade provada pelo Firebase e a sessão Laravel
 * convencional exigida pela navegação de browser (Sessão 3.1).
 *
 * Navegação de browser não envia `Authorization: Bearer`, então as rotas web
 * não podem depender de `auth.firebase`. O ID Token é validado aqui, uma
 * única vez, no momento do login; daí em diante a identidade da requisição
 * vem da sessão (`auth.web`), nunca do token.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly SyncFirebaseUserAction $syncUser,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Valida o Firebase ID Token, reutiliza `SyncFirebaseUserAction` — a
     * mesma Action da API, incluindo a criação automática da organização
     * Personal para usuários novos (SAD 20.3) — e estabelece a sessão.
     */
    public function firebase(AuthenticateWebFirebaseRequest $request): JsonResponse
    {
        try {
            $claims = app(FirebaseService::class)
                ->verifyIdToken($request->string('token')->toString());

            $user = $this->syncUser->handle($claims);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => route('dashboard'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
