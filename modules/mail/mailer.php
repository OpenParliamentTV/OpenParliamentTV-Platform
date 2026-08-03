<?php

/**
 * Mail Wrapper Module
 * Provides a simple interface for sending emails using PHPMailer with dev/production mode handling
 */

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../utilities/paths.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Sanitize email names to prevent header injection
 */
function sanitizeEmailName($name) {
    if (empty($name)) return '';
    
    // Remove newlines and control characters that could be used for header injection
    $name = preg_replace('/[\r\n\t]/', '', $name);
    
    // Limit length to prevent abuse
    $name = substr($name, 0, 100);
    
    return trim($name);
}

/**
 * Sanitize email subject to prevent header injection
 */
function sanitizeEmailSubject($subject) {
    if (empty($subject)) return '';
    
    // Remove newlines and control characters
    $subject = preg_replace('/[\r\n\t]/', '', $subject);
    
    // Limit length
    $subject = substr($subject, 0, 200);
    
    return trim($subject);
}


/**
 * Send an email using PHPMailer with development/production mode handling
 * 
 * @param string $to_email Recipient email address
 * @param string $to_name Recipient name (optional)
 * @param string $subject Email subject
 * @param string $message Email body (HTML or plain text)
 * @param bool $is_html Whether the message is HTML (default: false)
 * @param string|null $reply_to Reply-to email (optional)
 * @param string|null $reply_to_name Reply-to name (optional)
 * @return bool True on success, false on failure
 */
function sendMail($to_email, $to_name = '', $subject = '', $message = '', $is_html = false, $reply_to = null, $reply_to_name = null, $headers = []) {
    global $config;
    
    // Validate required parameters
    if (empty($to_email) || empty($subject) || empty($message)) {
        error_log("sendMail: Missing required parameters (to_email, subject, or message)");
        return false;
    }
    
    // Security: Validate email addresses
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("sendMail: Invalid recipient email address");
        return false;
    }
    
    if ($reply_to && !filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
        error_log("sendMail: Invalid reply-to email address");
        return false;
    }
    
    // Security: Sanitize names to prevent header injection
    $to_name = sanitizeEmailName($to_name);
    $reply_to_name = sanitizeEmailName($reply_to_name);
    
    // Security: Sanitize subject to prevent header injection
    $subject = sanitizeEmailSubject($subject);
    
    // Handle development mode - save emails to files instead of sending
    if ($config["mode"] === "dev") {
        return saveMailToFile($to_email, $to_name, $subject, $message, $is_html, $reply_to, $reply_to_name, $headers);
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        if (!empty($config["mail"]["smtp"]["enabled"]) && $config["mail"]["smtp"]["enabled"]) {
            $mail->isSMTP();
            $mail->Host = $config["mail"]["smtp"]["host"];
            $mail->SMTPAuth = true;
            $mail->Username = $config["mail"]["smtp"]["username"];
            $mail->Password = $config["mail"]["smtp"]["password"];
            $mail->SMTPSecure = $config["mail"]["smtp"]["encryption"] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config["mail"]["smtp"]["port"] ?? 587;
            
            // Optional: Enable SMTP debugging in dev mode
            if ($config["mode"] === "dev") {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }
        } else {
            // Use PHP's mail() function as fallback
            $mail->isMail();
        }
        
        // Recipients - Support both new and legacy config structure
        $from_email = $config["mail"]["from"]["email"] ?? $config["mail"]["from"] ?? "";
        $from_name = $config["mail"]["from"]["name"] ?? "";
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email, $to_name);
        
        // Reply-to
        if ($reply_to) {
            $mail->addReplyTo($reply_to, $reply_to_name ?: $reply_to);
        } elseif (!empty($config["mail"]["replyto"])) {
            $mail->addReplyTo($config["mail"]["replyto"]);
        }
        
        // Content
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body = $message;

        // Set charset
        $mail->CharSet = 'UTF-8';

        // Optional custom headers (e.g. List-Unsubscribe for notification emails)
        if (!empty($headers) && is_array($headers)) {
            foreach ($headers as $hName => $hValue) {
                $mail->addCustomHeader($hName, $hValue);
            }
        }

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("sendMail failed: " . $mail->ErrorInfo);
        return false;
    }
}


/**
 * Save email to file in development mode
 */
function saveMailToFile($to_email, $to_name, $subject, $message, $is_html, $reply_to, $reply_to_name, $headers = []) {
    global $config;
    
    // Default: <var>/log/mail (created by optvVarPath on first use).
    $default_dir = optvVarPath("log/mail");

    // Security: a configured path must stay inside the project root or the
    // runtime state directory — otherwise fall back to the default.
    $mail_dir = $default_dir;
    if (!empty($config["mail"]["dev"]["file_path"])) {
        $configured = realpath($config["mail"]["dev"]["file_path"]) ?: $config["mail"]["dev"]["file_path"];
        $allowed_roots = array_filter([realpath(__DIR__ . "/../.."), realpath(optvVarPath())]);
        $inside = false;
        foreach ($allowed_roots as $root) {
            if (strpos($configured, $root) === 0) {
                $inside = true;
                break;
            }
        }
        if ($inside) {
            $mail_dir = $configured;
        } else {
            error_log("sendMail (DEV MODE): Invalid mail directory path, using default");
        }
    }

    // Create directory if it doesn't exist
    if (!is_dir($mail_dir)) {
        if (!mkdir($mail_dir, 0755, true)) {
            error_log("sendMail (DEV MODE): Failed to create mail directory");
            return false;
        }
    }
    
    $filename = $mail_dir . "/" . date('Y-m-d_H-i-s') . "_" . uniqid() . ".eml";
    
    $email_content = "To: " . ($to_name ? "{$to_name} <{$to_email}>" : $to_email) . "\n";
    $email_content .= "Subject: {$subject}\n";
    $email_content .= "Date: " . date('r') . "\n";
    $email_content .= "Content-Type: " . ($is_html ? "text/html" : "text/plain") . "; charset=UTF-8\n";
    if ($reply_to) {
        $email_content .= "Reply-To: " . ($reply_to_name ? "{$reply_to_name} <{$reply_to}>" : $reply_to) . "\n";
    }
    if (!empty($headers) && is_array($headers)) {
        foreach ($headers as $hName => $hValue) {
            $email_content .= $hName . ": " . preg_replace('/[\r\n]/', '', $hValue) . "\n";
        }
    }
    $email_content .= "\n";
    $email_content .= $message;
    
    $result = file_put_contents($filename, $email_content);
    
    if ($result !== false) {
        error_log("sendMail (DEV MODE): Email saved to {$filename}");
        return true;
    } else {
        error_log("sendMail (DEV MODE): Failed to save email to {$filename}");
        return false;
    }
}


/**
 * Send a simple text email (convenience function)
 * 
 * @param string $to_email
 * @param string $subject
 * @param string $message
 * @param string $to_name
 * @return bool
 */
function sendSimpleMail($to_email, $subject, $message, $to_name = '') {
    return sendMail($to_email, $to_name, $subject, $message, false);
}

/**
 * Send an HTML email (convenience function)
 * 
 * @param string $to_email
 * @param string $subject
 * @param string $html_message
 * @param string $to_name
 * @return bool
 */
function sendHtmlMail($to_email, $subject, $html_message, $to_name = '') {
    return sendMail($to_email, $to_name, $subject, $html_message, true);
}