<?php

declare(strict_types=1);

namespace App\Repositories;

use Dibi\Connection;

final class EmailLogRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function log(
        int $userId,
        string $recipientEmail,
        string $emailType,
        string $subject,
        string $status,
        ?string $errorMessage = null,
        ?int $orderId = null
    ): int {
        $this->db->query('INSERT INTO email_logs', [
            'order_id' => $orderId,
            'user_id' => $userId,
            'recipient_email' => $recipientEmail,
            'email_type' => $emailType,
            'subject' => $subject,
            'status' => $status === 'sent' ? 'sent' : 'failed',
            'error_message' => $errorMessage,
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->getInsertId();
    }
}
