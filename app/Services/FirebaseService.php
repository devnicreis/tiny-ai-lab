<?php

namespace App\Services;

use InvalidArgumentException;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;

/**
 * Fronteira entre o Firebase Authentication e o domínio Laravel.
 *
 * Responsável exclusivamente por provar a identidade do usuário a partir de um
 * Firebase ID Token. Nenhuma regra de autorização vive aqui.
 */
class FirebaseService
{
    public function __construct(
        private readonly FirebaseAuth $auth,
    ) {}

    /**
     * Valida assinatura, expiração, emissor, audience e integridade do ID Token.
     *
     * O token nunca é cacheado: sua revogação é gerenciada pelo próprio Firebase.
     *
     * Apenas falhas do próprio token viram InvalidArgumentException. Falhas de
     * infraestrutura (credenciais ausentes, Firebase inacessível) propagam como
     * erro de servidor, para não serem mascaradas como falha de autenticação.
     *
     * @return array<string, mixed> claims do token verificado
     *
     * @throws InvalidArgumentException se o token for inválido, expirado ou revogado
     */
    public function verifyIdToken(string $token): array
    {
        if ($token === '') {
            throw new InvalidArgumentException('Firebase ID token is missing.');
        }

        try {
            return $this->auth->verifyIdToken($token)->claims()->all();
        } catch (FailedToVerifyToken|RevokedIdToken $e) {
            throw new InvalidArgumentException('Invalid Firebase ID token.', previous: $e);
        }
    }

    /**
     * Retorna o firebase_uid (claim `sub`) de um ID Token válido.
     *
     * @throws InvalidArgumentException se o token for inválido ou não contiver `sub`
     */
    public function getUid(string $token): string
    {
        $uid = $this->verifyIdToken($token)['sub'] ?? null;

        if (! is_string($uid) || $uid === '') {
            throw new InvalidArgumentException('Invalid Firebase ID token.');
        }

        return $uid;
    }
}
