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
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configure();
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
        $this->mail->setFrom($username, 'Denthub Dental Clinic');
    }
    
    /**
     * Send verification code email
     */
    public function sendVerificationCode($to, $code, $name = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Email Verification Code - Denthub Dental Clinic';
            
            $template = $this->getVerificationTemplate($code, $name);
            $this->mail->Body = $template;
            $this->mail->AltBody = "Your verification code is: $code\n\nThis code will expire in 10 minutes.";
            
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
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Your Denthub Dental Clinic Account - Welcome!';
            
            $template = $this->getDentistAccountTemplate($name, $username, $tempPassword, $email);
            $this->mail->Body = $template;
            $this->mail->AltBody = "Welcome to Denthub Dental Clinic!\n\nYour account has been created.\nUsername: $username\nTemporary Password: $tempPassword\n\nPlease login and change your password immediately.\n\nContact: dentalclinicdenthub@gmail.com";
            
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
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Appointment Confirmation - Denthub Dental Clinic';
            
            $template = $this->getAppointmentConfirmationTemplate($name, $appointmentData);
            $this->mail->Body = $template;
            $this->mail->AltBody = "Your appointment has been confirmed.\n\nReference: {$appointmentData['appointment_number']}\nDate: {$appointmentData['date']}\nTime: {$appointmentData['time']}\nService: {$appointmentData['service']}";
            
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
}

// Helper function for easy access
function getMailer() {
    return new Mailer();
}
