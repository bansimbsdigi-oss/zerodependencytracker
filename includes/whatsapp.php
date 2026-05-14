<?php
// includes/whatsapp.php

require_once __DIR__ . '/../config/config.php';

/**
 * Sends a WhatsApp message using the Combot REST API via cURL
 */
function sendWhatsAppMessage($mobile, $messageText) {
    if (empty(COMBOT_API_KEY) || COMBOT_API_KEY === 'YOUR_COMBOT_API_KEY_HERE') {
        // H4/C3: Never log message content — it may contain OTPs or credentials.
        error_log("WhatsApp Mock: message queued for " . substr($mobile, 0, 4) . "****");
        return true;
    }

    $url = "https://api.combot.net/v1/messages";

    $payload = [
        'to' => $mobile,
        'text' => $messageText
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . COMBOT_API_KEY
    ]);
    // H4: Prevent indefinite hangs on network failure.
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    } else {
        error_log("WhatsApp API Error: HTTP $httpCode for recipient " . substr($mobile, 0, 4) . "****");
        return false;
    }
}
