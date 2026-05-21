<?php

namespace App\Libraries;

class WhatsAppService
{
    public static function formatNumber(string $mobile): string
    {
        return preg_replace('/\D/', '', $mobile);
    }

    private static function post(array $payload): bool
    {
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

        error_log('WhatsApp API Error: HTTP ' . $httpCode . ' for recipient ' . substr($to, 0, 4) . '****');
        return false;
    }

    public static function sendOtp(string $mobile, string $otp): bool
    {
        return self::post([
            'to'             => self::formatNumber($mobile),
            'recipient_type' => 'individual',
            'type'           => 'template',
            'template'       => [
                'language'   => ['policy' => 'deterministic', 'code' => 'en'],
                'name'       => 'wp_otp_ms',
                'components' => [[
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'OTP for your ' . APP_NAME . ': ' . $otp],
                        ['type' => 'text', 'text' => 'Valid for the next ' . OTP_EXPIRY_MINUTES . ' minutes'],
                        ['type' => 'text', 'text' => 'Freedom'],
                    ],
                ]],
            ],
        ]);
    }

    public static function sendAuditStartReminder(string $mobile, string $firstName, string $auditTypeLabel, string $auditMonthYear): bool
    {
        return self::post([
            'to'             => self::formatNumber($mobile),
            'recipient_type' => 'individual',
            'type'           => 'template',
            'template'       => [
                'language'   => ['policy' => 'deterministic', 'code' => 'en'],
                'name'       => 'access__msg',
                'components' => [[
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $firstName],
                        ['type' => 'text', 'text' => $auditTypeLabel],
                        ['type' => 'text', 'text' => $auditMonthYear],
                    ],
                ]],
            ],
        ]);
    }

    public static function sendAuditFeedback(string $mobile, string $firstName, string $auditTypeLabel, string $auditMonthYear, string $feedbackText): bool
    {
        return self::post([
            'to'             => self::formatNumber($mobile),
            'recipient_type' => 'individual',
            'type'           => 'template',
            'template'       => [
                'language'   => ['policy' => 'deterministic', 'code' => 'en'],
                'name'       => 'access__msg_fed',
                'components' => [[
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => ' ' . $firstName . ','],
                        ['type' => 'text', 'text' => 'your coach has shared feedback on your ' . $auditTypeLabel . ' audit for ' . $auditMonthYear . ':'],
                        ['type' => 'text', 'text' => $feedbackText],
                        ['type' => 'text', 'text' => 'Log in to Zero Dependency Tracker to view your full audit report. Happy Freedom!'],
                    ],
                ]],
            ],
        ]);
    }

    public static function sendMessage(string $mobile, string $messageText): bool
    {
        return self::post([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => self::formatNumber($mobile),
            'type'              => 'text',
            'text'              => ['body' => $messageText],
        ]);
    }
}
