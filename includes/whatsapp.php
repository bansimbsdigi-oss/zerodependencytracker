<?php
// includes/whatsapp.php

require_once __DIR__ . '/../config/config.php';

/**
 * Strips non-digit characters from a mobile number.
 * The number is expected to already include the country code (e.g. 919876543210).
 */
function formatWhatsAppNumber($mobile) {
    return preg_replace('/\D/', '', $mobile);
}

/**
 * Shared cURL helper — sends a POST to the Combot API and returns true on 2xx.
 */
function _combotPost(array $payload): bool {
    $to = $payload['to'] ?? '?';
    $ch = curl_init(COMBOT_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . COMBOT_API_KEY,
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    error_log("WhatsApp API Error: HTTP $httpCode for recipient " . substr($to, 0, 4) . "****");
    return false;
}

/**
 * Sends an OTP via the wp_otp_ms template.
 *
 * Template variables (body components):
 *   {{1}} — OTP code
 *   {{2}} — Expiry in minutes
 *   {{3}} — App name
 */
function sendWhatsAppOTP(string $mobile, string $otp): bool {
    $to = formatWhatsAppNumber($mobile);

    $payload = [
        'to'             => $to,
        'recipient_type' => 'individual',
        'type'           => 'template',
        'template'       => [
            'language'   => [
                'policy' => 'deterministic',
                'code'   => 'en',
            ],
            'name'       => 'wp_otp_ms',
            'components' => [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'OTP for your ' . APP_NAME . ': ' . $otp],
                        ['type' => 'text', 'text' => 'Valid for the next ' . OTP_EXPIRY_MINUTES . ' minutes'],
                        ['type' => 'text', 'text' => 'Freedom'],
                    ],
                ],
            ],
        ],
    ];

    return _combotPost($payload);
}

/**
 * Sends an audit reminder via the wp_audit_reminder template.
 *
 * Template variables (body components):
 *   {{1}} — Client first name
 *   {{2}} — Audit type label (Mid Month / Month End)
 *   {{3}} — Month and year (e.g. May 2026)
 */
function sendWhatsAppAuditReminder(string $mobile, string $firstName, string $auditTypeLabel, string $auditMonthYear): bool {
    $to = formatWhatsAppNumber($mobile);

    $payload = [
        'to'             => $to,
        'recipient_type' => 'individual',
        'type'           => 'template',
        'template'       => [
            'language'   => [
                'policy' => 'deterministic',
                'code'   => 'en',
            ],
            'name'       => 'wp_audit_reminder',
            'components' => [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $firstName],
                        ['type' => 'text', 'text' => $auditTypeLabel],
                        ['type' => 'text', 'text' => $auditMonthYear],
                    ],
                ],
            ],
        ],
    ];

    return _combotPost($payload);
}

/**
 * Sends a coach feedback notification via the wp_audit_feedback template.
 *
 * Template variables (body components):
 *   {{1}} — Client first name
 *   {{2}} — Audit type label (Mid Month / Month End)
 *   {{3}} — Month and year (e.g. May 2026)
 *   {{4}} — Feedback text from coach
 */
function sendWhatsAppAuditFeedback(string $mobile, string $firstName, string $auditTypeLabel, string $auditMonthYear, string $feedbackText): bool {
    $to = formatWhatsAppNumber($mobile);

    $payload = [
        'to'             => $to,
        'recipient_type' => 'individual',
        'type'           => 'template',
        'template'       => [
            'language'   => [
                'policy' => 'deterministic',
                'code'   => 'en',
            ],
            'name'       => 'wp_audit_feedback',
            'components' => [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $firstName],
                        ['type' => 'text', 'text' => $auditTypeLabel],
                        ['type' => 'text', 'text' => $auditMonthYear],
                        ['type' => 'text', 'text' => $feedbackText],
                    ],
                ],
            ],
        ],
    ];

    return _combotPost($payload);
}

/**
 * Sends a plain WhatsApp text message via the Combot Meta API.
 * Used for non-OTP messages (audit reminders, admin alerts, etc.)
 */
function sendWhatsAppMessage($mobile, $messageText) {
    $to = formatWhatsAppNumber($mobile);

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'text',
        'text'              => [
            'body' => $messageText,
        ],
    ];

    return _combotPost($payload);
}
