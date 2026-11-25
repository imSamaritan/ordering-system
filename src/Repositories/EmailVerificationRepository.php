<?php

declare(strict_types=1);

namespace App\Repositories;

use Dibi\Connection;

final class EmailVerificationRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function invalidateUnusedForUser(int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE email_verifications SET used_at = %s WHERE user_id = %i AND used_at IS NULL',
            $now,
            $userId
        );
    }

    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $this->db->query('INSERT INTO email_verifications', [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->getInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $row = $this->db->query(
            'SELECT * FROM email_verifications WHERE token_hash = %s',
            $tokenHash
        )->fetch();

        return $row ? $row->toArray() : null;
    }

    public function markUsed(int $id): void
    {
        $this->db->query(
            'UPDATE email_verifications SET used_at = %s WHERE id = %i',
            date('Y-m-d H:i:s'),
            $id
        );
    }
}
