<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Migration de dados — adoção das Marcas existentes pela organização
     * "Personal" do antigo `projects.user_id` (SAD 12.8, item 3 e 4).
     *
     * Esta é a única exceção prevista à regra do Apêndice K.7 de não popular
     * dados em migration: sem ela, `projects.organization_id` não pode se
     * tornar NOT NULL e toda Marca existente ficaria órfã, violando a regra
     * absoluta da Seção 20.2.
     *
     * Idempotente: um usuário que já participe de alguma organização não
     * recebe outra, e apenas Marcas com `organization_id` ainda nulo são
     * atualizadas. Rodar duas vezes produz o mesmo estado.
     *
     * Usuários soft deleted também são processados: as Marcas deles continuam
     * na tabela e precisam de uma organização dona antes do NOT NULL.
     */
    public function up(): void
    {
        // `projects.user_id` é o único vínculo que diz qual organização adota
        // cada Marca, e a migration seguinte o remove. Se ele já não existe, a
        // adoção das Marcas já aconteceu e só resta garantir a organização de
        // cada usuário.
        $canAdoptProjects = Schema::hasColumn('projects', 'user_id');

        DB::table('users')->chunkById(200, function ($users) use ($canAdoptProjects) {
            foreach ($users as $user) {
                $organizationId = $this->existingOrganizationId($user->id)
                    ?? $this->createPersonalOrganization($user);

                if (! $canAdoptProjects) {
                    continue;
                }

                DB::table('projects')
                    ->where('user_id', $user->id)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $organizationId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Desfaz apenas o que esta migration criou: organizações "Personal"
     * geradas pelo backfill, identificadas pelo par nome + prefixo de slug,
     * com exatamente um membro, que é Admin. Organizações criadas pela
     * aplicação depois do backfill não são tocadas.
     */
    public function down(): void
    {
        $candidates = DB::table('organizations')
            ->where('slug', 'like', 'personal-%')
            ->get(['id', 'name']);

        foreach ($candidates as $organization) {
            $members = DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->get(['user_id', 'role']);

            if ($members->count() !== 1 || $members->first()->role !== 'admin') {
                continue;
            }

            $ownerName = DB::table('users')
                ->where('id', $members->first()->user_id)
                ->value('name');

            if ($organization->name !== $ownerName.' Personal') {
                continue;
            }

            DB::table('projects')
                ->where('organization_id', $organization->id)
                ->update(['organization_id' => null]);

            DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->delete();

            DB::table('organizations')
                ->where('id', $organization->id)
                ->delete();
        }
    }

    /**
     * Primeira organização de que o usuário já participa, se houver.
     */
    private function existingOrganizationId(int $userId): ?int
    {
        return DB::table('organization_user')
            ->where('user_id', $userId)
            ->orderBy('organization_id')
            ->value('organization_id');
    }

    /**
     * Cria a organização "Personal" do usuário e o registra como Admin dela.
     */
    private function createPersonalOrganization(object $user): int
    {
        $now = now();
        $joinedAt = $user->created_at ?? $now;

        $organizationId = DB::table('organizations')->insertGetId([
            'name' => Str::limit($user->name.' Personal', 100, ''),
            'slug' => $this->uniquePersonalSlug(),
            'plan' => $this->translatePlan($user->plan ?? null),
            'plan_credits_balance' => $user->plan_credits_balance ?? 0,
            'plan_credits_used_this_month' => $user->credits_used_this_month ?? 0,
            'purchased_credits_balance' => 0,
            'last_credit_reset_at' => null,
            'created_at' => $joinedAt,
            'updated_at' => $now,
        ]);

        DB::table('organization_user')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => 'admin',
            'joined_at' => $joinedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $organizationId;
    }

    /**
     * O slug é único globalmente, então "personal" sozinho colidiria entre
     * usuários: cada organização Personal recebe um sufixo aleatório.
     */
    private function uniquePersonalSlug(): string
    {
        do {
            $slug = 'personal-'.Str::lower(Str::random(12));
        } while (DB::table('organizations')->where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * `users.plan` guarda os planos legados (`free`, `pro`, `enterprise`), que
     * não existem no enum comercial de `organizations.plan` (SAD 12.6). Só um
     * valor já pertencente ao novo enum é copiado; qualquer legado vira `null`
     * — organização sem assinatura ativa (SAD 12.18), que é o estado correto
     * enquanto o mapeamento comercial entre planos antigos e novos permanece
     * indefinido. O saldo e o consumo do mês são copiados sempre, para que
     * nenhum crédito seja perdido no corte.
     */
    private function translatePlan(?string $plan): ?string
    {
        return in_array($plan, ['copy_briefing', 'studio_visual', 'full_pass'], true)
            ? $plan
            : null;
    }
};
