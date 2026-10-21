<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $membership,
    ) {}

    /**
     * Aceita o convite em nome do usuário autenticado.
     *
     * Fica fora do middleware `organization`: quem aceita um convite ainda não
     * é membro da organização de destino. Cada recusa — e-mail não verificado,
     * e-mail diferente, convite expirado, revogado ou já usado — é uma
     * exception semântica renderizada em `bootstrap/app.php`.
     */
    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $organization = $this->membership->acceptInvitation(
            $user,
            $request->string('token')->toString(),
        );

        $role = $user->roleIn($organization);

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $role?->value,
                'role_label' => $role?->label(),
            ],
        ]);
    }
}
