<?php
// includes/otp.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Generates a random 6 digit OTP code
 */
function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Saves a generated OTP to the database for a user.
 * The OTP is stored as a SHA-256 hash so a DB breach does not expose live codes.
 */
function saveOTP($userId, $otpCode) {
    $pdo = getDB();
    $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
    $hashedOtp = hash('sha256', $otpCode);

    // M1: Invalidate all previous unused OTPs for this user before issuing a new one.
    $pdo->prepare('UPDATE otps SET is_used = 1 WHERE user_id = ? AND is_used = 0')->execute([$userId]);

    $stmt = $pdo->prepare('INSERT INTO otps (user_id, otp_code, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $hashedOtp, $expiresAt]);

    // M1: Clean up expired/used OTPs to prevent unbounded table growth.
    $pdo->prepare('DELETE FROM otps WHERE expires_at < NOW() AND is_used = 1')->execute();
}
