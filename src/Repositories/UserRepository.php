<?php

declare(strict_types=1);

namespace App\Repositories;

use Dibi\Connection;

final class UserRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM users WHERE id = %i', $id)->fetch();
        return $row ? $row->toArray() : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $row = $this->db->query('SELECT * FROM users WHERE email = %s', strtolower(trim($email)))->fetch();
        return $row ? $row->toArray() : null;
    }

    /**
     * @param array{
     *   role: string,
     *   company_name: ?string,
     *   contact_name: string,
     *   email: string,
     *   phone: ?string,
     *   location: ?string,
     *   password_hash: string
     * } $data
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO users', [
            'role' => $data['role'],
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'],
            'location' => $data['location'],
            'password_hash' => $data['password_hash'],
            'email_verified_at' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->getInsertId();
    }

    public function markEmailVerified(int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE users SET email_verified_at = %s, updated_at = %s WHERE id = %i AND email_verified_at IS NULL',
            $now,
            $now,
            $userId
        );
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function toPublicArray(array $user): array
    {
        $verifiedAt = $user['email_verified_at'] ?? null;
        if ($verifiedAt instanceof \DateTimeInterface) {
            $verifiedAt = $verifiedAt->format('Y-m-d H:i:s');
        }

        return [
            'id' => (int) $user['id'],
            'role' => (string) $user['role'],
            'company_name' => $user['company_name'] !== null ? (string) $user['company_name'] : null,
            'contact_name' => (string) $user['contact_name'],
            'email' => (string) $user['email'],
            'phone' => $user['phone'] !== null ? (string) $user['phone'] : null,
            'location' => $user['location'] !== null ? (string) $user['location'] : null,
            'email_verified_at' => $verifiedAt !== null ? (string) $verifiedAt : null,
            'is_active' => (bool) ((int) ($user['is_active'] ?? 0)),
        ];
    }
}
