<?php
/**
 * OTP verification rate limiting: max attempts per item per lockout window (session + optional IP).
 */
function otp_attempts_key(int $itemId): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    return 'otp_' . $itemId . '_' . substr(md5($ip), 0, 8);
}

function otp_rate_limit_exceeded(int $itemId): bool {
    $key = otp_attempts_key($itemId);
    $window = defined('OTP_LOCKOUT_SECONDS') ? OTP_LOCKOUT_SECONDS : 900;
    $max = defined('OTP_MAX_ATTEMPTS') ? OTP_MAX_ATTEMPTS : 5;

    if (empty($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return false;
    }
    $cutoff = time() - $window;
    $_SESSION[$key] = array_values(array_filter($_SESSION[$key], function ($t) use ($cutoff) {
        return $t > $cutoff;
    }));
    return count($_SESSION[$key]) >= $max;
}

function otp_record_attempt(int $itemId, bool $success): void {
    $key = otp_attempts_key($itemId);
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    if ($success) {
        unset($_SESSION[$key]);
        return;
    }
    $_SESSION[$key][] = time();
}

function otp_lockout_remaining(int $itemId): int {
    $key = otp_attempts_key($itemId);
    if (empty($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return 0;
    }
    $window = defined('OTP_LOCKOUT_SECONDS') ? OTP_LOCKOUT_SECONDS : 900;
    $oldest = min($_SESSION[$key]);
    $end = $oldest + $window;
    return max(0, $end - time());
}
