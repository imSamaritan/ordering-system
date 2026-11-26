<?php

declare(strict_types=1);

namespace App\Repositories;

use Dibi\Connection;

final class PasswordResetRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Creates a password reset record with MySQL-calculated expiry (minimum 7 minutes).
     */
    public function create(int $userId, string $hashedToken, int $ttlMinutes = 7): int
    {
        $minutes = max(7, $ttlMinutes);
        $this->db->query(
            'INSERT INTO password_resets (user_id, hashed_token, expiry_date, created_at)
             VALUES (%i, %s, DATE_ADD(NOW(), INTERVAL %i MINUTE), NOW())',
            $userId,
            $hashedToken,
            $minutes
        );

        return $this->db->getInsertId();
    }

    /**
     * Deletes all expired password reset tokens using MySQL NOW().
     */
    public function deleteExpired(): int
    {
        $this->db->query('DELETE FROM password_resets WHERE expiry_date <= NOW()');

        return $this->db->getAffectedRows();
    }

    /**
     * Deletes all password reset records for the given user.
     */
    public function deleteForUser(int $userId): void
    {
        $this->db->query('DELETE FROM password_resets WHERE user_id = %i', $userId);
    }

    /**
     * Cleans up expired rows and returns a strict bool indicating whether hashed_token is valid right now.
     */
    public function isTokenValid(string $hashedToken): bool
    {
        $this->deleteExpired();

        $exists = (int) $this->db->query(
            'SELECT EXISTS(
                SELECT 1 FROM password_resets
                WHERE hashed_token = %s AND expiry_date > NOW()
            )',
            $hashedToken
        )->fetchSingle();

        return $exists === 1;
    }

    /**
     * Cleans up expired rows and returns the owning user_id if the token is valid, or null.
     */
    public function findValidUserIdByHashedToken(string $hashedToken): ?int
    {
        $this->deleteExpired();

        $userId = $this->db->query(
            'SELECT user_id FROM password_resets
             WHERE hashed_token = %s AND expiry_date > NOW()
             LIMIT 1',
            $hashedToken
        )->fetchSingle();

        return $userId !== false && $userId !== null ? (int) $userId : null;
    }
}
