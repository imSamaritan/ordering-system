<?php

declare(strict_types=1);

namespace App\Support;

final class Validator
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function validateRegistration(array $input): array
    {
        $errors = [];

        $role = strtolower(trim((string) ($input['role'] ?? 'customer')));
        if (!in_array($role, ['customer', 'company'], true)) {
            $errors['role'] = 'Account type must be customer or company.';
        }

        $contactName = trim((string) ($input['contact_name'] ?? ''));
        if ($contactName === '' || mb_strlen($contactName) < 2) {
            $errors['contact_name'] = 'Contact name must be at least 2 characters.';
        } elseif (mb_strlen($contactName) > 191) {
            $errors['contact_name'] = 'Contact name must not exceed 191 characters.';
        }

        $companyName = trim((string) ($input['company_name'] ?? ''));
        if ($role === 'company' && $companyName === '') {
            $errors['company_name'] = 'Company name is required for company accounts.';
        } elseif (mb_strlen($companyName) > 191) {
            $errors['company_name'] = 'Company name must not exceed 191 characters.';
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'A valid email address is required.';
        } elseif (mb_strlen($email) > 191) {
            $errors['email'] = 'Email address must not exceed 191 characters.';
        }

        $password = (string) ($input['password'] ?? '');
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        } elseif (mb_strlen($password) > 128) {
            $errors['password'] = 'Password must not exceed 128 characters.';
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        if ($phone !== '' && mb_strlen($phone) > 50) {
            $errors['phone'] = 'Phone number must not exceed 50 characters.';
        }

        $location = trim((string) ($input['location'] ?? ''));
        if ($location !== '' && mb_strlen($location) > 255) {
            $errors['location'] = 'Location must not exceed 255 characters.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function validateLogin(array $input): array
    {
        $errors = [];

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'A valid email address is required.';
        }

        $password = (string) ($input['password'] ?? '');
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        return $errors;
    }

    public static function isValidEmail(string $email): bool
    {
        $trimmed = trim($email);
        return $trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function validatePasswordReset(array $input): array
    {
        $errors = [];

        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') {
            $errors['token'] = 'A valid password reset token is required.';
        }

        $password = (string) ($input['password'] ?? '');
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        } elseif (mb_strlen($password) > 128) {
            $errors['password'] = 'Password must not exceed 128 characters.';
        }

        if (array_key_exists('confirm_password', $input) && (string) $input['confirm_password'] !== $password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        return $errors;
    }
}
