<?php
require_once __DIR__ . '/../config.php';

//Mailer sigue teniendo partes del codigo antiguo, el cual intentaba utilizar el SMTP, pero ahora no es necesario. Esta adaptado para usar Brevo.

function sendMailViaBrevo(string $to, string $subject, string $html, string $toName = ''): bool {
    if (empty($to) || !str_contains($to, '@')) return false;
    $apiKey    = defined('SMTP_PASS') ? SMTP_PASS : '';
    $fromEmail = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@echo.lab';
    $fromName  = defined('APP_NAME')  ? APP_NAME  : 'echo';
    if (empty($apiKey)) { error_log('[echo mailer] SMTP_PASS not set'); return false; }
    $payload = json_encode([
        'sender'      => ['name' => $fromName, 'email' => $fromEmail],
        'to'          => [['email' => $to, 'name' => $toName ?: $to]],
        'subject'     => $subject,
        'htmlContent' => $html,
    ]);
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err)  { error_log('[echo mailer] curl error: ' . $err); return false; }
    if ($code < 200 || $code >= 300) { error_log('[echo mailer] Brevo error ' . $code . ': ' . $raw); return false; }
    return true;
}

function buildEmail(string $title, string $body, string $btnText, string $btnUrl, string $lang = 'en'): string {
    $appName = defined('APP_NAME') ? APP_NAME : 'echo';
    $appUrl  = defined('APP_URL')  ? APP_URL  : '';
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
      body{margin:0;padding:0;background:#0a0a0a;font-family:-apple-system,sans-serif;color:#ededed}
      .wrap{max-width:520px;margin:40px auto;background:#111;border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden}
      .header{background:#000;padding:24px 32px;border-bottom:1px solid rgba(255,255,255,.07)}
      .logo{font-size:13px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#ededed}
      .body{padding:32px}
      h1{font-size:18px;font-weight:700;margin:0 0 12px;color:#ededed}
      p{font-size:14px;line-height:1.7;color:rgba(237,237,237,.65);margin:0 0 20px}
      .btn{display:inline-block;padding:12px 28px;background:#ededed;color:#000;border-radius:6px;text-decoration:none;font-size:14px;font-weight:600}
      .footer{padding:20px 32px;border-top:1px solid rgba(255,255,255,.07);font-size:12px;color:rgba(237,237,237,.25);text-align:center}
    </style></head><body><div class="wrap">
      <div class="header"><span class="logo">' . htmlspecialchars($appName) . '</span></div>
      <div class="body"><h1>' . htmlspecialchars($title) . '</h1><p>' . nl2br(htmlspecialchars($body)) . '</p>
      <a href="' . htmlspecialchars($btnUrl) . '" class="btn">' . htmlspecialchars($btnText) . '</a></div>
      <div class="footer">&copy; ' . date('Y') . ' <a href="' . htmlspecialchars($appUrl) . '" style="color:rgba(237,237,237,.4);text-decoration:none">' . htmlspecialchars($appName) . '</a></div>
    </div></body></html>';
}

function sendResetEmail(array $user, string $link, string $lang = 'en'): bool {
    if (empty($user['email'])) return false;
    $es = $lang === 'es';
    return sendMailViaBrevo($user['email'],
        $es ? 'Restablece tu contraseña — ' . APP_NAME : 'Reset your password — ' . APP_NAME,
        buildEmail(
            $es ? 'Restablece tu contraseña' : 'Reset your password',
            $es ? "Hemos recibido una solicitud para restablecer la contraseña.\n\nEste enlace expira en 2 horas." : "We received a request to reset your password.\n\nThis link expires in 2 hours.",
            $es ? 'Restablecer contraseña' : 'Reset password',
            $link, $lang
        ), $user['username'] ?? '');
}

function sendEmailChangeConfirmation(array $user, string $newEmail, string $link, string $lang = 'en'): bool {
    if (empty($newEmail)) return false;
    $es = $lang === 'es';
    return sendMailViaBrevo($newEmail,
        $es ? 'Confirma tu nuevo correo — ' . APP_NAME : 'Confirm your new email — ' . APP_NAME,
        buildEmail(
            $es ? 'Confirma tu nuevo correo' : 'Confirm your new email',
            $es ? "Solicitaste cambiar el correo de tu cuenta.\n\nHaz clic para confirmar." : "You requested to change your account email.\n\nClick to confirm.",
            $es ? 'Confirmar correo' : 'Confirm email',
            $link, $lang
        ), $user['username'] ?? '');
}

function sendWelcomeEmail(array $user, string $setPasswordLink, string $lang = 'en'): bool {
    if (empty($user['email'])) return false;
    $es = $lang === 'es';
    $app = defined('APP_NAME') ? APP_NAME : 'echo';
    return sendMailViaBrevo($user['email'],
        $es ? "Bienvenido a $app — Crea tu contraseña" : "Welcome to $app — Set your password",
        buildEmail(
            $es ? "Bienvenido a $app" : "Welcome to $app",
            $es ? "Tu cuenta ha sido creada.\n\nHaz clic para crear tu contraseña. El enlace expira en 48 horas." : "Your account has been created.\n\nClick below to set your password. The link expires in 48 hours.",
            $es ? 'Crear contraseña' : 'Set password',
            $setPasswordLink, $lang
        ), $user['username'] ?? '');
}
