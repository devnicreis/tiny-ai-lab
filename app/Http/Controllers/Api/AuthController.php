<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\SyncFirebaseUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthenticateFirebaseRequest;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly SyncFirebaseUserAction $syncUser,
    ) {}

    /**
     * Autentica o usuário a partir de um Firebase ID Token e sincroniza o
     * registro local do domínio.
     */
    public function firebase(AuthenticateFirebaseRequest $request): JsonResponse
    {
        try {
            $claims = app(FirebaseService::class)
                ->verifyIdToken($request->string('token')->toString());

            $user = $this->syncUser->handle($claims);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($this->toPayload($user));
    }

    /**
     * Retorna o usuário autenticado pelo middleware `auth.firebase`.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->toPayload($user));
    }

    /**
     * Os dois saldos viajam separados: crédito que expira e crédito que não
     * expira são informação de domínio distinta e nunca podem ser somados em um
     * único campo de payload (SAD 12.2, CLAUDE.md regra 27).
     *
     * @return array{user: array{id: int, email: string, name: string, plan: string, plan_credits_balance: int, purchased_credits_balance: int}}
     */
    private function toPayload(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'plan' => $user->plan,
                'plan_credits_balance' => $user->plan_credits_balance,
                'purchased_credits_balance' => $user->purchased_credits_balance,
            ],
        ];
    }
}
