<?php
// Xác nhận email khi đăng ký: tạo token, lưu BẢN BĂM trong database, gửi link qua SMTP.
use PHPMailer\PHPMailer\PHPMailer;

const TRIP_EMAIL_TOKEN_TTL_HOURS = 24;
const TRIP_EMAIL_RESEND_COOLDOWN_SECONDS = 60;

/** Đọc config/mail.php (file này KHÔNG được commit lên Git). Trả về null nếu chưa cấu hình. */
function trip_email_config(): ?array
{
    static $config = false;
    if ($config === false) {
        $file = __DIR__ . '/mail.php';
        $loaded = is_file($file) ? require $file : null;
        $config = is_array($loaded) ? $loaded : null;
    }
    return $config;
}

function trip_email_load_phpmailer(): bool
{
    if (class_exists(PHPMailer::class, false)) {
        return true;
    }
    $root = dirname(__DIR__);
    if (is_file($root . '/PHPMailer/src/PHPMailer.php')) {
        require_once $root . '/PHPMailer/src/Exception.php';
        require_once $root . '/PHPMailer/src/PHPMailer.php';
        require_once $root . '/PHPMailer/src/SMTP.php';
        return true;
    }
    if (is_file($root . '/vendor/autoload.php')) {
        require_once $root . '/vendor/autoload.php';
        return class_exists(PHPMailer::class);
    }
    return false;
}

function trip_email_send(string $to, string $name, string $subject, string $html, string $text): bool
{
    $config = trip_email_config();
    if ($config === null || !trip_email_load_phpmailer()) {
        error_log('[TripConnect mail] thiếu config/mail.php hoặc thư viện PHPMailer');
        return false;
    }
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($config['port'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        // Gmail chỉ chấp nhận người gửi trùng với tài khoản đăng nhập SMTP.
        $mail->setFrom($config['from_email'] ?? $config['username'], $config['from_name'] ?? 'TripConnect');
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('[TripConnect mail] gửi thất bại: ' . $e->getMessage());
        return false;
    }
}

/** Tạo token mới (vô hiệu token cũ), lưu bản băm + hạn dùng. Trả về token gốc để đưa vào link. */
function trip_email_issue_token(PDO $conn, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare('UPDATE users SET email_verify_token_hash = ?, email_verify_expires = DATE_ADD(NOW(), INTERVAL ' . TRIP_EMAIL_TOKEN_TTL_HOURS . ' HOUR), email_verify_sent_at = NOW() WHERE id = ? AND email_verified_at IS NULL');
    $stmt->execute([hash('sha256', $token), $userId]);
    return $token;
}

/** $user cần có: id, email, full_name. */
function trip_email_send_verification(PDO $conn, array $user): bool
{
    $config = trip_email_config();
    if ($config === null) {
        error_log('[TripConnect mail] chưa tạo config/mail.php');
        return false;
    }
    $token = trip_email_issue_token($conn, (int) $user['id']);
    // Link lấy từ cấu hình, không lấy từ header Host của người dùng.
    $link = rtrim((string) $config['base_url'], '/') . '/verify-email.php?token=' . $token;
    $name = (string) ($user['full_name'] ?? '');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $hours = TRIP_EMAIL_TOKEN_TTL_HOURS;
    $html = "<p>Xin chào {$safeName},</p>"
        . "<p>Cảm ơn bạn đã đăng ký TripConnect. Bấm nút bên dưới để xác nhận email và kích hoạt tài khoản (liên kết có hiệu lực {$hours} giờ):</p>"
        . "<p><a href=\"{$safeLink}\" style=\"display:inline-block;padding:10px 20px;background:#2d6a63;color:#fff;text-decoration:none;border-radius:6px\">Xác nhận email</a></p>"
        . "<p>Nếu nút không bấm được, hãy sao chép đường dẫn này vào trình duyệt:<br>{$safeLink}</p>"
        . "<p>Nếu bạn không đăng ký tài khoản này, hãy bỏ qua email.</p>";
    $text = "Xin chào {$name},\nXác nhận email TripConnect (hiệu lực {$hours} giờ): {$link}\nNếu bạn không đăng ký, hãy bỏ qua email này.";
    return trip_email_send((string) $user['email'], $name, 'Xác nhận tài khoản TripConnect của bạn', $html, $text);
}
