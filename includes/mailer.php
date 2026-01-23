<?php
/**
 * Denthub Dental Clinic - Email Mailer Service
 * Handles all email sending functionality
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $driver;
    private $apiKey;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Decide how to send mail:
        // - 'resend' (HTTP API, works on Railway free/hobby)
        // - 'smtp'   (default, uses PHPMailer + SMTP)
        $this->driver   = strtolower(getenv('MAIL_DRIVER') ?: 'smtp');
        $this->apiKey   = getenv('RESEND_API_KEY') ?: '';
        
        // For Resend, prioritize RESEND_FROM_EMAIL and avoid gmail.com addresses
        // (gmail.com requires domain verification which you can't do)
        $resendFromEmail = getenv('RESEND_FROM_EMAIL');
        if ($this->driver === 'resend' && (empty($resendFromEmail) || strpos($resendFromEmail, '@gmail.com') !== false)) {
            // Default to Resend's test sender for free tier
            $this->fromEmail = 'onboarding@resend.dev';
        } else {
            $this->fromEmail = $resendFromEmail ?: (getenv('MAIL_USERNAME') ?: 'dentalclinicdenthub@gmail.com');
        }
        
        $this->fromName  = getenv('RESEND_FROM_NAME') ?: 'Denthub Dental Clinic';

        // Only configure SMTP when we are actually using it
        if ($this->driver === 'smtp') {
            $this->configure();
        }
    }
    
    private function configure() {
        // Read SMTP settings from environment (for Railway/hosting platforms)
        $host       = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $username   = getenv('MAIL_USERNAME') ?: 'dentalclinicdenthub@gmail.com';
        $password   = getenv('MAIL_PASSWORD') ?: 'hakp xtdl gksu ooxs';
        $port       = getenv('MAIL_PORT') ?: 587;
        $encryption = strtolower(getenv('MAIL_ENCRYPTION') ?: 'tls');

        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host       = $host;
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $username;
        $this->mail->Password   = $password;

        // Map generic encryption string to PHPMailer constant
        if ($encryption === 'ssl') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            // 'tls' / 'starttls' / default
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $this->mail->Port       = (int)$port;
        $this->mail->CharSet    = 'UTF-8';
        
        // Sender info
        $this->mail->setFrom($username, $this->fromName ?: 'Denthub Dental Clinic');
    }
    
    /**
     * Send verification code email
     */
    public function sendVerificationCode($to, $code, $name = '') {
        $subject = 'Email Verification Code - Denthub Dental Clinic';
        $html    = $this->getVerificationTemplate($code, $name);
        $text    = "Your verification code is: $code\n\nThis code will expire in 10 minutes.";

        if ($this->driver === 'resend' && $this->apiKey) {
            return $this->sendViaResend($to, $subject, $html, $text, $name);
        }

        // Fallback: SMTP/PHPMailer (works on local/dev or Railway Pro)
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $html;
            $this->mail->AltBody = $text;

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Send dentist account creation email
     */
    public function sendDentistAccountEmail($to, $name, $username, $tempPassword, $email) {
        $subject = 'Your Denthub Dental Clinic Account - Welcome!';
        $html    = $this->getDentistAccountTemplate($name, $username, $tempPassword, $email);
        $text    = "Welcome to Denthub Dental Clinic!\n\nYour account has been created.\nUsername: $username\nTemporary Password: $tempPassword\n\nPlease login and change your password immediately.\n\nContact: dentalclinicdenthub@gmail.com";

        if ($this->driver === 'resend' && $this->apiKey) {
            return $this->sendViaResend($to, $subject, $html, $text, $name);
        }

        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $html;
            $this->mail->AltBody = $text;

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmation($to, $name, $appointmentData) {
        $subject = 'Appointment Confirmation - Denthub Dental Clinic';
        $html    = $this->getAppointmentConfirmationTemplate($name, $appointmentData);
        $text    = "Your appointment has been confirmed.\n\nReference: {$appointmentData['appointment_number']}\nDate: {$appointmentData['date']}\nTime: {$appointmentData['time']}\nService: {$appointmentData['service']}";

        if ($this->driver === 'resend' && $this->apiKey) {
            return $this->sendViaResend($to, $subject, $html, $text, $name);
        }

        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $html;
            $this->mail->AltBody = $text;

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Get verification code email template
     */
    private function getVerificationTemplate($code, $name) {
        $name = $name ?: 'Valued Patient';
        $templatePath = __DIR__ . '/../templates/email_verification.html';
        if (!file_exists($templatePath)) {
            // Fallback to simple text template
            return "Hello $name,\n\nYour verification code is: $code\n\nThis code will expire in 10 minutes.";
        }
        $template = file_get_contents($templatePath);
        $template = str_replace('{{NAME}}', htmlspecialchars($name), $template);
        $template = str_replace('{{CODE}}', htmlspecialchars($code), $template);
        return $template;
    }
    
    /**
     * Get dentist account creation email template
     */
    private function getDentistAccountTemplate($name, $username, $tempPassword, $email) {
        $loginUrl = APP_URL . '/login-unified.php';
        $templatePath = __DIR__ . '/../templates/dentist_account.html';
        if (!file_exists($templatePath)) {
            // Fallback to simple text template
            return "Hello $name,\n\nYour account has been created.\nUsername: $username\nPassword: $tempPassword\n\nLogin at: $loginUrl";
        }
        $template = file_get_contents($templatePath);
        $template = str_replace('{{NAME}}', htmlspecialchars($name), $template);
        $template = str_replace('{{USERNAME}}', htmlspecialchars($username), $template);
        $template = str_replace('{{PASSWORD}}', htmlspecialchars($tempPassword), $template);
        $template = str_replace('{{EMAIL}}', htmlspecialchars($email), $template);
        $template = str_replace('{{LOGIN_URL}}', $loginUrl, $template);
        return $template;
    }
    
    /**
     * Get appointment confirmation email template
     */
    private function getAppointmentConfirmationTemplate($name, $appointmentData) {
        $templatePath = __DIR__ . '/../templates/appointment_confirmation.html';
        if (!file_exists($templatePath)) {
            // Fallback to simple text template
            return "Hello $name,\n\nYour appointment has been confirmed.\nReference: " . ($appointmentData['appointment_number'] ?? '') . "\nDate: " . ($appointmentData['date'] ?? '') . "\nTime: " . ($appointmentData['time'] ?? '');
        }
        $template = file_get_contents($templatePath);
        $template = str_replace('{{NAME}}', htmlspecialchars($name), $template);
        $template = str_replace('{{APPOINTMENT_NUMBER}}', htmlspecialchars($appointmentData['appointment_number'] ?? ''), $template);
        $template = str_replace('{{SERVICE}}', htmlspecialchars($appointmentData['service'] ?? ''), $template);
        $template = str_replace('{{DATE}}', htmlspecialchars($appointmentData['date'] ?? ''), $template);
        $template = str_replace('{{TIME}}', htmlspecialchars($appointmentData['time'] ?? ''), $template);
        
        $dentistRow = '';
        if (!empty($appointmentData['dentist'])) {
            $dentistRow = '<tr><td style="padding: 8px 0; color: #333333; font-weight: bold;">Dentist:</td><td style="padding: 8px 0; color: #666666;">' . htmlspecialchars($appointmentData['dentist']) . '</td></tr>';
        }
        $template = str_replace('{{DENTIST_ROW}}', $dentistRow, $template);
        
        return $template;
    }

    /**
     * Send email via Resend HTTP API (works on Railway free/hobby)
     */
    private function sendViaResend($to, $subject, $html, $text, $name = '') {
        if (empty($this->apiKey)) {
            error_log('Resend Error: RESEND_API_KEY not configured');
            return false;
        }

        // Use the fromEmail set in constructor, but double-check it's not gmail.com
        // If it is, force use of Resend's test sender
        $fromEmail = $this->fromEmail;
        if (empty($fromEmail) || strpos($fromEmail, '@gmail.com') !== false || strpos($fromEmail, '@gmail') !== false) {
            // Force use of Resend's test sender which works without domain verification
            $fromEmail = 'onboarding@resend.dev';
            error_log('Resend Warning: Invalid from email detected, forcing onboarding@resend.dev');
        } else {
            $fromEmail = trim($fromEmail);
        }

        $fromName  = $this->fromName ?: 'Denthub Dental Clinic';
        $payload = [
            'from' => sprintf('%s <%s>', $fromName, $fromEmail),
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
        
        // Debug log to help troubleshoot
        error_log("Resend Debug: Driver={$this->driver}, From={$fromEmail}, To={$to}, API Key present=" . (!empty($this->apiKey) ? 'yes' : 'no'));

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false || $httpCode >= 400) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Resend Error: HTTP $httpCode - $error - Response: $response");
            return false;
        }
        curl_close($ch);
        return true;
    }
}

// Helper function for easy access
function getMailer() {
    return new Mailer();
}
