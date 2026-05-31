<?php
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    // Guard against empty tokens — hash_equals() would return true on two empty strings
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function rotate_csrf_token(): void {
    // Call after login/logout to invalidate the old token
    unset($_SESSION['csrf_token']);
}

function format_time($timestamp) {
    $time = strtotime($timestamp);
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');
    
    if ($time >= $today) {
        return 'Today at ' . date('g:i A', $time);
    } elseif ($time >= $yesterday) {
        return 'Yesterday at ' . date('g:i A', $time);
    } else {
        return date('M j, Y \a\t g:i A', $time);
    }
}

function validate_indian_phone($phone) {
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (strpos($phone, '+91') === 0) {
        $phone = substr($phone, 3);
    } elseif (strpos($phone, '91') === 0 && strlen($phone) == 12) {
        $phone = substr($phone, 2);
    }
    
    if (preg_match('/^[6-9]\d{9}$/', $phone)) {
        return $phone;
    }
    return false;
}

function generate_otp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send a transactional email via Resend REST API.
 * No Composer or SDK required — pure cURL.
 * Requires RESEND_API_KEY and MAIL_FROM environment variables.
 *
 * @param  string $to      Recipient email address
 * @param  string $subject Email subject line
 * @param  string $text    Plain-text email body
 * @return bool            true on HTTP 200/201, false on any failure
 */
function send_email_via_resend(string $to, string $subject, string $text): bool {
    $api_key = getenv('RESEND_API_KEY');
    $from    = getenv('MAIL_FROM') ?: 'noreply@yourdomain.com';

    if (!$api_key) {
        error_log("send_email_via_resend: RESEND_API_KEY not set — email not sent to $to");
        return false;
    }

    $payload = json_encode([
        'from'    => $from,
        'to'      => [$to],
        'subject' => $subject,
        'text'    => $text,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log("send_email_via_resend: cURL error — $curl_err");
        return false;
    }

    if ($http_code !== 200 && $http_code !== 201) {
        error_log("send_email_via_resend: Resend API returned HTTP $http_code — $response");
        return false;
    }

    return true;
}
?>
