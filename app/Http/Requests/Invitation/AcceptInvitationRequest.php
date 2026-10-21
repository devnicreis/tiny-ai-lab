<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    /**
     * Qualquer usuário autenticado pode tentar aceitar um convite: ainda não é
     * membro da organização, então não há Policy de organização a consultar.
     * A prova real — e-mail verificado e igual ao do convite — é do
     * `OrganizationMembershipService`, dentro da transação.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * O token vem da rota, não do corpo.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['token' => $this->route('token')]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'O token do convite é obrigatório.',
        ];
    }
}
