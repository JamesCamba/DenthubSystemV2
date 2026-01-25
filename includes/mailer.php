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
        // - 'maileroo' (HTTP API, works on free hosting; simple setup - RECOMMENDED for free tier)
        // - 'sendgrid' (HTTP API, works on free hosting; student-friendly, no company required)
        // - 'brevo'    (HTTP API, works on free hosting; supports sender verification)
        // - 'resend'   (HTTP API, but requires verified domain to email others)
        // - 'smtp'     (default, uses PHPMailer + SMTP - blocked on free hosting)
        $this->driver   = strtolower(getenv('MAIL_DRIVER') ?: 'smtp');
        
        // API key and settings depend on driver
        if ($this->driver === 'maileroo') {
            $this->apiKey = getenv('MAILEROO_API_KEY') ?: '';
            // Maileroo requires using their domain alias (e.g., denthub@93832b22d815d4ec.maileroo.org)
            // DO NOT use Gmail addresses - Maileroo requires their domain
            $this->fromEmail = getenv('MAILEROO_FROM_EMAIL') ?: 'denthub@93832b22d815d4ec.maileroo.org';
            $this->fromName = getenv('MAILEROO_FROM_NAME') ?: 'Denthub Dental Clinic';
        } elseif ($this->driver === 'sendgrid') {
            $this->apiKey = getenv('SENDGRID_API_KEY') ?: '';
            $this->fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: (getenv('MAIL_USERNAME') ?: 'dentalclinicdenthub@gmail.com');
            $this->fromName = getenv('SENDGRID_FROM_NAME') ?: 'Denthub Dental Clinic';
        } elseif ($this->driver === 'brevo') {
            $this->apiKey = getenv('BREVO_API_KEY') ?: '';
            $this->fromEmail = getenv('BREVO_FROM_EMAIL') ?: (getenv('MAIL_USERNAME') ?: 'dentalclinicdenthub@gmail.com');
            $this->fromName = getenv('BREVO_FROM_NAME') ?: 'Denthub Dental Clinic';
        } elseif ($this->driver === 'resend') {
            $this->apiKey = getenv('RESEND_API_KEY') ?: '';
            // For Resend, avoid gmail.com addresses (requires domain verification)
            $resendFromEmail = getenv('RESEND_FROM_EMAIL');
            if (empty($resendFromEmail) || strpos($resendFromEmail, '@gmail.com') !== false) {
                $this->fromEmail = 'onboarding@resend.dev';
            } else {
                $this->fromEmail = $resendFromEmail ?: (getenv('MAIL_USERNAME') ?: 'dentalclinicdenthub@gmail.com');
            }
            $this->fromName = getenv('RESEND_FROM_NAME') ?: 'Denthub Dental Clinic';
        } else {
            // SMTP driver
            $this->apiKey = '';
            $this->fromEmail = getenv('MAIL_USERNAME') ?: 'dentalclinicdenthub@gmail.com';
            $this->fromName = 'Denthub Dental Clinic';
        }

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
     * @param string $to Recipient email
     * @param string $code 6-digit verification code
     * @param string $name Recipient name
     * @param string $purpose 'registration' or 'password_reset' (optional, for subject customization)
     */
    public function sendVerificationCode($to, $code, $name = '', $purpose = 'registration') {
        if ($purpose === 'password_reset') {
            $subject = 'Password Reset Verification Code - Denthub Dental Clinic';
            $text    = "Your password reset verification code is: $code\n\nThis code will expire in 10 minutes.\n\nIf you did not request a password reset, please ignore this email.";
        } else {
            $subject = 'Email Verification Code - Denthub Dental Clinic';
            $text    = "Your verification code is: $code\n\nThis code will expire in 10 minutes.";
        }
        $html    = $this->getVerificationTemplate($code, $name);

        if ($this->driver === 'maileroo' && $this->apiKey) {
            return $this->sendViaMaileroo($to, $subject, $html, $text, $name);
        }
        if ($this->driver === 'sendgrid' && $this->apiKey) {
            return $this->sendViaSendGrid($to, $subject, $html, $text, $name);
        }
        if ($this->driver === 'brevo' && $this->apiKey) {
            return $this->sendViaBrevo($to, $subject, $html, $text, $name);
        }
        if ($this->driver === 'resend' && $this->apiKey) {
            return $this->sendViaResend($to, $subject, $html, $text, $name);
        }

        // Fallback: SMTP/PHPMailer (works on local/dev or paid hosting)
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

        if ($this->driver === 'maileroo' && $this->apiKey) {
            return $this->sendViaMaileroo($to, $subject, $html, $text, $name);
        }
        if ($this->driver === 'sendgrid' && $this->apiKey) {
            return $this->sendViaSendGrid($to, $subject, $html, $text, $name);
        }
        if ($this->driver === 'brevo' && $this->apiKey) {
            return $this->sendViaBrevo($to, $subject, $html, $text, $name);
        }
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

        if ($this->driver === 'maileroo' && $this->apiKey) {
            return $this->sendViaMaileroo($to, $subject, $html, $text, $name);
        }
        if ($this->driver === 'sendgrid' && $this->apiKey) {
            return $this->sendViaSendGrid($to, $subject, $html, $text, $name);
        }
        if ($this->driver === 'brevo' && $this->apiKey) {
            return $this->sendViaBrevo($to, $subject, $html, $text, $name);
        }
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

    /**
     * Send email via Brevo HTTP API (works on free hosting; supports sender verification)
     * Brevo allows you to verify your Gmail address and send FROM it to ANY recipient
     */
    private function sendViaBrevo($to, $subject, $html, $text, $name = '') {
        if (empty($this->apiKey)) {
            error_log('Brevo Error: BREVO_API_KEY not configured');
            return false;
        }

        $fromEmail = trim($this->fromEmail);
        $fromName  = $this->fromName ?: 'Denthub Dental Clinic';
        
        // Brevo API payload
        $payload = [
            'sender' => [
                'name' => $fromName,
                'email' => $fromEmail
            ],
            'to' => [
                [
                    'email' => $to,
                    'name' => $name ?: ''
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $html,
            'textContent' => $text
        ];

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'api-key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false || $httpCode >= 400) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Brevo Error: HTTP $httpCode - $error - Response: $response");
            return false;
        }
        curl_close($ch);
        return true;
    }

    /**
     * Send email via SendGrid HTTP API (works on free hosting; student-friendly, no company required)
     * SendGrid free tier: 100 emails/day
     */
    private function sendViaSendGrid($to, $subject, $html, $text, $name = '') {
        if (empty($this->apiKey)) {
            error_log('SendGrid Error: SENDGRID_API_KEY not configured');
            return false;
        }

        $fromEmail = trim($this->fromEmail);
        $fromName  = $this->fromName ?: 'Denthub Dental Clinic';
        
        // SendGrid API payload
        $payload = [
            'personalizations' => [
                [
                    'to' => [
                        [
                            'email' => $to,
                            'name' => $name ?: ''
                        ]
                    ],
                    'subject' => $subject
                ]
            ],
            'from' => [
                'email' => $fromEmail,
                'name' => $fromName
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $html
                ],
                [
                    'type' => 'text/plain',
                    'value' => $text
                ]
            ]
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
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
            error_log("SendGrid Error: HTTP $httpCode - $error - Response: $response");
            return false;
        }
        curl_close($ch);
        return true;
    }

    /**
     * Send email via Maileroo HTTP API (works on free hosting; simple setup)
     * Maileroo API: https://smtp.maileroo.com/api/v2/emails
     */
    private function sendViaMaileroo($to, $subject, $html, $text, $name = '') {
        if (empty($this->apiKey)) {
            error_log('Maileroo Error: MAILEROO_API_KEY not configured');
            return false;
        }

        // Get and validate from email
        $fromEmail = is_string($this->fromEmail) ? trim($this->fromEmail) : '';
        if (empty($fromEmail)) {
            // Fallback to environment variable or default Maileroo domain
            $fromEmail = trim(getenv('MAILEROO_FROM_EMAIL') ?: 'denthub@93832b22d815d4ec.maileroo.org');
        }
        
        $fromName = is_string($this->fromName) ? trim($this->fromName) : 'Denthub Dental Clinic';
        if (empty($fromName)) {
            $fromName = 'Denthub Dental Clinic';
        }
        
        // Validate email addresses
        if (empty($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Maileroo Error: Invalid from email - value: " . var_export($this->fromEmail, true) . ", after trim: " . var_export($fromEmail, true));
            return false;
        }
        
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Maileroo Error: Invalid to email: {$to}");
            return false;
        }
        
        // Maileroo API payload (correct format: address and display_name)
        $payload = [
            'from' => [
                'address' => (string)$fromEmail,
                'display_name' => (string)$fromName
            ],
            'to' => [
                [
                    'address' => (string)$to,
                    'display_name' => (string)($name ?: '')
                ]
            ],
            'subject' => (string)$subject,
            'html' => (string)$html,
            'plain' => (string)$text  // Maileroo uses 'plain' not 'text'
        ];

        // Debug log
        error_log("Maileroo Debug: Driver = {$this->driver}, API Key present = " . (!empty($this->apiKey) ? 'yes' : 'no'));
        error_log("Maileroo Debug: From email = '{$fromEmail}' (type: " . gettype($fromEmail) . ")");
        error_log("Maileroo Debug: From name = '{$fromName}'");
        error_log("Maileroo Debug: To email = '{$to}'");
        error_log("Maileroo Debug: Payload JSON: " . json_encode($payload, JSON_PRETTY_PRINT));

        // Maileroo API endpoint
        $apiUrl = 'https://smtp.maileroo.com/api/v2/emails';
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Api-Key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false); // Set to true for detailed curl info if needed

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Always log the full response for debugging
        error_log("Maileroo API Response: HTTP $httpCode - Body: " . ($response ?: '(empty)'));
        
        if ($response === false) {
            error_log("Maileroo Error: cURL failed - {$curlError}");
            return false;
        }
        
        // Parse response
        $responseData = json_decode($response, true);
        
        // Check for HTTP errors
        if ($httpCode >= 400) {
            $errorMsg = 'Unknown error';
            if (is_array($responseData)) {
                $errorMsg = $responseData['message'] ?? $responseData['error'] ?? json_encode($responseData);
            } else {
                $errorMsg = $response ?: 'Empty response';
            }
            error_log("Maileroo Error: HTTP $httpCode - {$errorMsg}");
            return false;
        }
        
        // Check response data for success/error indicators
        if (is_array($responseData)) {
            // Maileroo might return success: true or a data object
            if (isset($responseData['success']) && $responseData['success'] === true) {
                $referenceId = $responseData['data']['reference_id'] ?? 'N/A';
                $message = $responseData['message'] ?? 'Email queued';
                error_log("Maileroo Success: Email queued for delivery to {$to}");
                error_log("Maileroo Reference ID: {$referenceId}");
                error_log("Maileroo Message: {$message}");
                error_log("Maileroo Note: If email not received, check: 1) Spam folder, 2) Maileroo dashboard (reference_id: {$referenceId}), 3) Gmail filters");
                return true;
            }
            
            // Check for error messages
            if (isset($responseData['error']) || isset($responseData['message'])) {
                $errorMsg = $responseData['error'] ?? $responseData['message'] ?? 'Unknown error';
                error_log("Maileroo Error in response: {$errorMsg} - Full response: " . json_encode($responseData));
                return false;
            }
            
            // If response has data field, might be success
            if (isset($responseData['data']) && $httpCode === 200) {
                error_log("Maileroo Success: Email sent to {$to} - Response data: " . json_encode($responseData['data']));
                return true;
            }
        }
        
        // If we got HTTP 200-299 and no explicit error, check if response is empty or has content
        if ($httpCode >= 200 && $httpCode < 300) {
            // Empty response on 200 might indicate success for some APIs
            if (empty($response) || trim($response) === '') {
                error_log("Maileroo Success: HTTP $httpCode with empty response (assuming success) - Email sent to {$to}");
                return true;
            }
            
            // If response is not JSON or doesn't have error, assume success
            if (!is_array($responseData) || (!isset($responseData['error']) && !isset($responseData['message']))) {
                error_log("Maileroo Success: HTTP $httpCode - Email sent to {$to} - Response: " . substr($response, 0, 200));
                return true;
            }
        }
        
        // Unexpected response
        error_log("Maileroo Warning: Unexpected response - HTTP $httpCode - Response: " . substr($response, 0, 500));
        return false;
    }
}

// Helper function for easy access
function getMailer() {
    return new Mailer();
}
