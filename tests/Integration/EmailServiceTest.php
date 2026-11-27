<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\EmailLogRepository;
use App\Services\EmailService;
use Dibi\Connection;
use Dotenv\Dotenv;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

final class EmailServiceTest extends TestCase
{
    private Connection $db;
    private EmailLogRepository $logRepo;
    private int $testUserId;
    private string $testEmail;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        Dotenv::createImmutable($root)->safeLoad();

        $this->db = new Connection([
            'driver' => 'mysqli',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_NAME'] ?? 'ordering_system',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ]);

        $this->logRepo = new EmailLogRepository($this->db);
        $this->testEmail = 'phpunit-mail-' . bin2hex(random_bytes(5)) . '@example.co.za';
        $now = date('Y-m-d H:i:s');

        $this->db->query('INSERT INTO users', [
            'role' => 'customer',
            'company_name' => null,
            'contact_name' => 'Mail Test User',
            'email' => $this->testEmail,
            'phone' => null,
            'location' => null,
            'password_hash' => password_hash('MailPass#2026', PASSWORD_DEFAULT),
            'email_verified_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->testUserId = $this->db->getInsertId();
    }

    protected function tearDown(): void
    {
        $this->db->query('DELETE FROM users WHERE id = %i', $this->testUserId);
    }

    public function testDryRunLogsSentWithoutOpeningSmtp(): void
    {
        $called = false;
        $service = new EmailService(
            ['host' => 'mail.example.co.za', 'port' => 465, 'encryption' => 'ssl'],
            $this->logRepo,
            true,
            static function () use (&$called): PHPMailer {
                $called = true;
                return new PHPMailer(true);
            }
        );

        $result = $service->send(
            $this->testUserId,
            $this->testEmail,
            'Mail Test User',
            'email_verification',
            'Verify your email',
            '<p>Hello</p>',
            'Hello'
        );

        $this->assertTrue($result['sent']);
        $this->assertNull($result['error']);
        $this->assertFalse($called);

        $log = $this->db->query(
            'SELECT status, recipient_email, email_type FROM email_logs WHERE user_id = %i ORDER BY id DESC LIMIT 1',
            $this->testUserId
        )->fetch();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame($this->testEmail, $log->recipient_email);
    }

    public function testInvalidRecipientFailsCleanlyAndLogsError(): void
    {
        $service = new EmailService(
            ['host' => 'mail.example.co.za', 'port' => 465, 'encryption' => 'ssl'],
            $this->logRepo,
            false
        );

        $result = $service->send(
            $this->testUserId,
            'not-an-email',
            'Bad User',
            'email_verification',
            'Verify',
            '<p>Hello</p>'
        );

        $this->assertFalse($result['sent']);
        $this->assertSame('Invalid recipient email address.', $result['error']);

        $log = $this->db->query(
            'SELECT status, error_message FROM email_logs WHERE user_id = %i ORDER BY id DESC LIMIT 1',
            $this->testUserId
        )->fetch();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertSame('Invalid recipient email address.', $log->error_message);
    }

    public function testSsl465ConfigurationAndQuotedPrintableEncoding(): void
    {
        $capturedMailer = null;

        $service = new EmailService(
            [
                'host' => ' mail.asamaritan-order-management-sys.co.za ',
                'port' => 465,
                'user' => 'no-reply@asamaritan-order-management-sys.co.za',
                'pass' => 'O(8.QaUb38P;VrS0',
                'encryption' => 'SSL',
                'from_email' => 'no-reply@asamaritan-order-management-sys.co.za',
                'from_name' => 'Produce & Inventory Ordering System',
                'reply_to' => 'no-reply@asamaritan-order-management-sys.co.za',
                'timeout' => 15,
                'verify_peer' => true,
            ],
            $this->logRepo,
            false,
            static function () use (&$capturedMailer): PHPMailer {
                $capturedMailer = new class (true) extends PHPMailer {
                    public bool $sendCalled = false;

                    public function send(): bool
                    {
                        $this->sendCalled = true;
                        return true;
                    }
                };
                return $capturedMailer;
            }
        );

        $result = $service->send(
            $this->testUserId,
            $this->testEmail,
            'Customer One',
            'order_confirmation',
            'Order Confirmed — ORD-001',
            '<p>Confirmed</p>',
            'Confirmed'
        );

        $this->assertTrue($result['sent']);
        $this->assertNull($result['error']);
        $this->assertNotNull($capturedMailer);
        $this->assertTrue($capturedMailer->sendCalled);
        $this->assertSame('smtp', $capturedMailer->Mailer);
        $this->assertSame('mail.asamaritan-order-management-sys.co.za', $capturedMailer->Host);
        $this->assertSame(465, $capturedMailer->Port);
        $this->assertTrue($capturedMailer->SMTPAuth);
        $this->assertSame('no-reply@asamaritan-order-management-sys.co.za', $capturedMailer->Username);
        $this->assertSame('O(8.QaUb38P;VrS0', $capturedMailer->Password);
        $this->assertSame(PHPMailer::ENCRYPTION_SMTPS, $capturedMailer->SMTPSecure);
        $this->assertFalse($capturedMailer->SMTPAutoTLS);
        $this->assertSame(PHPMailer::CHARSET_UTF8, $capturedMailer->CharSet);
        $this->assertSame(PHPMailer::ENCODING_QUOTED_PRINTABLE, $capturedMailer->Encoding);
        $this->assertSame('no-reply@asamaritan-order-management-sys.co.za', $capturedMailer->From);
        $this->assertSame('no-reply@asamaritan-order-management-sys.co.za', $capturedMailer->Sender);
    }

    public function testStartTls587AndVerifyPeerFalseOptions(): void
    {
        $capturedMailer = null;

        $service = new EmailService(
            [
                'host' => 'mail.example.co.za',
                'port' => 587,
                'user' => 'mailer@example.co.za',
                'pass' => 'secret',
                'encryption' => 'tls',
                'from_email' => '',
                'verify_peer' => false,
            ],
            $this->logRepo,
            false,
            static function () use (&$capturedMailer): PHPMailer {
                $capturedMailer = new class (true) extends PHPMailer {
                    public function send(): bool
                    {
                        return true;
                    }
                };
                return $capturedMailer;
            }
        );

        $result = $service->send($this->testUserId, $this->testEmail, 'Dest', 'password_reset', 'Reset', '<p>Hi</p>');

        $this->assertTrue($result['sent']);
        $this->assertNotNull($capturedMailer);
        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $capturedMailer->SMTPSecure);
        $this->assertTrue($capturedMailer->SMTPAutoTLS);
        $this->assertSame('mailer@example.co.za', $capturedMailer->From);
        $this->assertSame(false, $capturedMailer->SMTPOptions['ssl']['verify_peer'] ?? null);
    }

    public function testSmtpFailureLogsDetailedErrorWithTrace(): void
    {
        $service = new EmailService(
            [
                'host' => 'mail.example.co.za',
                'port' => 465,
                'user' => 'no-reply@example.co.za',
                'pass' => 'wrong',
                'encryption' => 'ssl',
            ],
            $this->logRepo,
            false,
            static fn (): PHPMailer => new class (true) extends PHPMailer {
                public function send(): bool
                {
                    if (is_callable($this->Debugoutput)) {
                        ($this->Debugoutput)('SERVER -> CLIENT: 535 Incorrect authentication data', 2);
                    }
                    throw new PHPMailerException('SMTP Error: Could not authenticate.');
                }
            }
        );

        $result = $service->send($this->testUserId, $this->testEmail, 'Buyer', 'email_verification', 'Verify', '<p>Hi</p>');

        $this->assertFalse($result['sent']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Could not authenticate', $result['error']);
        $this->assertStringContainsString('535 Incorrect authentication data', $result['error']);

        $log = $this->db->query(
            'SELECT status, error_message FROM email_logs WHERE user_id = %i ORDER BY id DESC LIMIT 1',
            $this->testUserId
        )->fetch();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('535 Incorrect authentication data', (string) $log->error_message);
    }

    public function testNewSupplierAccountExistsAndAuthenticates(): void
    {
        $row = $this->db->query(
            'SELECT role, email_verified_at, is_active, password_hash FROM users WHERE email = %s',
            'no-reply@asamaritan-order-management-sys.co.za'
        )->fetch();

        $this->assertNotNull($row);
        $this->assertSame('supplier', $row->role);
        $this->assertNotNull($row->email_verified_at);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertTrue(password_verify('61780890', (string) $row->password_hash));
    }
}
