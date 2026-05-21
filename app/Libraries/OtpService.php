<?php

namespace App\Libraries;

class OtpService
{
    public static function generate(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function save(int $userId, string $otpCode): void
    {
        $pdo       = getDB();
        $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
        $hashed    = hash('sha256', $otpCode);

        // Invalidate previous unused OTPs before issuing a new one
        $pdo->prepare('UPDATE otps SET is_used = 1 WHERE user_id = ? AND is_used = 0')
            ->execute([$userId]);

        $pdo->prepare('INSERT INTO otps (user_id, otp_code, expires_at) VALUES (?, ?, ?)')
            ->execute([$userId, $hashed, $expiresAt]);

        // Clean up to prevent unbounded table growth
        $pdo->prepare('DELETE FROM otps WHERE expires_at < NOW() AND is_used = 1')
            ->execute();
    }
}
