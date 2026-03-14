<?php
/**
 * Optional OTP encryption at rest. If OTP_ENCRYPTION_KEY is set in config, OTPs are stored encrypted.
 */
function otp_encrypt(string $plain): string {
    if (!defined('OTP_ENCRYPTION_KEY') || OTP_ENCRYPTION_KEY === '') {
        return $plain;
    }
    $key = hash('sha256', OTP_ENCRYPTION_KEY, true);
    $iv = random_bytes(16);
    $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function otp_decrypt(string $stored): string {
    if (!defined('OTP_ENCRYPTION_KEY') || OTP_ENCRYPTION_KEY === '') {
        return $stored;
    }
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 17) {
        return $stored; // legacy plain
    }
    $key = hash('sha256', OTP_ENCRYPTION_KEY, true);
    $iv = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $dec !== false ? $dec : $stored;
}
