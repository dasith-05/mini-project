<?php
/**
 * CSRF token generation and validation for all state-changing forms.
 */
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'traceit_csrf');
}

function csrf_ensure_token(): void {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string {
    csrf_ensure_token();
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string {
    return '<input type="hidden" name="' . htmlspecialchars(CSRF_TOKEN_NAME) . '" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_validate(): bool {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $valid = isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals((string) $_SESSION[CSRF_TOKEN_NAME], $token);
    if ($valid) {
        // Rotate token after use for critical actions
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $valid;
}
