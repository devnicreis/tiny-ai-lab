<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Migration de dados — backfill do Owner (ADR-010, Consequências; SAD
     * 20.4 e 20.8).
     *
     * Toda organização passa a ter exatamente um Owner. Para cada organização
     * sem Owner, EXATAMENTE UM membro é promovido: o Admin com menor
     * `joined_at` (desempate por `id`). Os demais Admins continuam Admins.
     *
     * Como o backfill de `2026_09_14_030300`, é exceção prevista à regra do
     * Apêndice K.7: sem ela, o índice único parcial da migration seguinte
     * nasceria sobre organizações sem Owner, estado irrecuperável pelo
     * próprio cliente (SAD 20.4).
     *
     * Organização sem nenhum Admin: promove o membro mais antigo, pelo mesmo
     * critério, e registra o id da organização no log. Organização sem nenhum
     * membro não tem quem promover e também é registrada.
     *
     * Idempotente: organização que já tem Owner não é tocada.
     */
    public function up(): void
    {
        DB::table('organizations')->chunkById(200, function ($organizations) {
            foreach ($organizations as $organization) {
                $this->promoteOwnerOf($organization->id);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Todo Owner volta a ser Admin — inclusive os criados pela aplicação
     * depois desta migration, já que o papel `owner` deixa de existir no
     * schema quando `widen_role_in_organization_user_table` é desfeita. Os
     * `down()` das migrations de setembro continuam encontrando o Admin
     * (SAD 20.8).
     */
    public function down(): void
    {
        DB::table('organization_user')
            ->where('role', 'owner')
            ->update(['role' => 'admin', 'updated_at' => now()]);
    }

    /**
     * Promove o Owner de uma organização, se ela ainda não tiver um.
     */
    private function promoteOwnerOf(int $organizationId): void
    {
        $memberships = DB::table('organization_user')
            ->where('organization_id', $organizationId);

        if ((clone $memberships)->where('role', 'owner')->exists()) {
            return;
        }

        $candidateId = $this->oldest((clone $memberships)->where('role', 'admin'));

        if ($candidateId === null) {
            $candidateId = $this->oldest(clone $memberships);

            if ($candidateId === null) {
                Log::warning('promote_single_admin_to_owner: organization has no members; no Owner promoted.', [
                    'organization_id' => $organizationId,
                ]);

                return;
            }

            Log::warning('promote_single_admin_to_owner: organization has no Admin; oldest member promoted to Owner.', [
                'organization_id' => $organizationId,
                'organization_user_id' => $candidateId,
            ]);
        }

        DB::table('organization_user')
            ->where('id', $candidateId)
            ->update(['role' => 'owner', 'updated_at' => now()]);
    }

    /**
     * Id da participação mais antiga: menor `joined_at`, desempate por `id`.
     */
    private function oldest($query): ?int
    {
        return $query
            ->orderBy('joined_at')
            ->orderBy('id')
            ->value('id');
    }
};
