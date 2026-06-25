<?php
namespace App\helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    public static function send(string $toEmail, string $toName, string $subject, string $body, string $altBody = ''): bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$_ENV['MAIL_PORT'];

            $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public static function appointmentBooked(string $toEmail, string $toName, array $appointment): bool {
        $subject = 'Appointment Booked - MediCare Clinic';
        $body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #064E3B, #059669); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0;">MediCare Clinic</h1>
                <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0;">Appointment Confirmation</p>
            </div>
            <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 12px 12px;">
                <h2 style="color: #1f2937;">Hello ' . htmlspecialchars($toName) . ',</h2>
                <p style="color: #6b7280;">Your appointment has been successfully booked.</p>
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <table style="width: 100%;">
                        <tr><td style="color: #6b7280; padding: 6px 0;">Doctor</td><td style="font-weight: bold; color: #1f2937;">Dr. ' . htmlspecialchars($appointment['doctor_name'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Specialization</td><td style="color: #1f2937;">' . htmlspecialchars($appointment['specialization'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Date</td><td style="color: #1f2937;">' . htmlspecialchars($appointment['date'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Time</td><td style="color: #1f2937;">' . htmlspecialchars($appointment['time'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Status</td><td><span style="background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 99px; font-size: 13px;">Pending Confirmation</span></td></tr>
                    </table>
                </div>
                <p style="color: #6b7280; font-size: 14px;">Please arrive 10 minutes before your appointment time.</p>
                <div style="border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 20px; text-align: center;">
                    <p style="color: #9ca3af; font-size: 12px;">MediCare Clinic &middot; Better appointments, better care.</p>
                </div>
            </div>
        </div>';
        return self::send($toEmail, $toName, $subject, $body);
    }

    public static function appointmentConfirmed(string $toEmail, string $toName, array $appointment): bool {
        $subject = 'Appointment Confirmed - MediCare Clinic';
        $body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #064E3B, #059669); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0;">MediCare Clinic</h1>
                <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0;">Appointment Confirmed &#x2713;</p>
            </div>
            <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 12px 12px;">
                <h2 style="color: #1f2937;">Hello ' . htmlspecialchars($toName) . ',</h2>
                <p style="color: #6b7280;">Great news! Your appointment has been <strong style="color: #059669;">confirmed</strong>.</p>
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <table style="width: 100%;">
                        <tr><td style="color: #6b7280; padding: 6px 0;">Doctor</td><td style="font-weight: bold; color: #1f2937;">Dr. ' . htmlspecialchars($appointment['doctor_name'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Date</td><td style="color: #1f2937;">' . htmlspecialchars($appointment['date'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Time</td><td style="color: #1f2937;">' . htmlspecialchars($appointment['time'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Status</td><td><span style="background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 99px; font-size: 13px;">Confirmed</span></td></tr>
                    </table>
                </div>
                <p style="color: #6b7280; font-size: 14px;">Please arrive 10 minutes before your appointment time.</p>
                <div style="border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 20px; text-align: center;">
                    <p style="color: #9ca3af; font-size: 12px;">MediCare Clinic &middot; Better appointments, better care.</p>
                </div>
            </div>
        </div>';
        return self::send($toEmail, $toName, $subject, $body);
    }

    public static function appointmentCancelled(string $toEmail, string $toName, array $appointment): bool {
        $subject = 'Appointment Cancelled - MediCare Clinic';
        $body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #881337, #BE123C); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0;">MediCare Clinic</h1>
                <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0;">Appointment Cancelled</p>
            </div>
            <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 12px 12px;">
                <h2 style="color: #1f2937;">Hello ' . htmlspecialchars($toName) . ',</h2>
                <p style="color: #6b7280;">Your appointment has been <strong style="color: #BE123C;">cancelled</strong>.</p>
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <table style="width: 100%;">
                        <tr><td style="color: #6b7280; padding: 6px 0;">Doctor</td><td style="font-weight: bold; color: #1f2937;">Dr. ' . htmlspecialchars($appointment['doctor_name'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Date</td><td style="color: #1f2937;">' . htmlspecialchars($appointment['date'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Time</td><td style="color: #1f2937;">' . htmlspecialchars($appointment['time'] ?? '') . '</td></tr>
                    </table>
                </div>
                <p style="color: #6b7280; font-size: 14px;">If you did not request this cancellation, please contact us immediately.</p>
                <div style="border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 20px; text-align: center;">
                    <p style="color: #9ca3af; font-size: 12px;">MediCare Clinic &middot; Better appointments, better care.</p>
                </div>
            </div>
        </div>';
        return self::send($toEmail, $toName, $subject, $body);
    }

    public static function offDayDecision(string $toEmail, string $toName, array $offDay): bool {
        $approved = $offDay['status'] === 'approved';
        $subject  = 'Off Day Request ' . ($approved ? 'Approved' : 'Rejected') . ' - MediCare Clinic';
        $color    = $approved ? '#059669' : '#BE123C';
        $status   = $approved ? 'Approved &#x2713;' : 'Rejected &#x2717;';
        $body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #1A365D, #2B6CB0); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0;">MediCare Clinic</h1>
                <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0;">Off Day Request Update</p>
            </div>
            <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 12px 12px;">
                <h2 style="color: #1f2937;">Hello Dr. ' . htmlspecialchars($toName) . ',</h2>
                <p style="color: #6b7280;">Your off day request has been <strong style="color:' . $color . ';">' . $status . '</strong>.</p>
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <table style="width: 100%;">
                        <tr><td style="color: #6b7280; padding: 6px 0;">Date</td><td style="color: #1f2937;">' . htmlspecialchars($offDay['date'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Reason</td><td style="color: #1f2937;">' . htmlspecialchars($offDay['reason'] ?? '') . '</td></tr>
                        <tr><td style="color: #6b7280; padding: 6px 0;">Decision</td><td><span style="background:' . ($approved ? '#d1fae5' : '#fee2e2') . '; color:' . $color . '; padding: 3px 10px; border-radius: 99px; font-size: 13px;">' . $status . '</span></td></tr>
                    </table>
                </div>
                <div style="border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 20px; text-align: center;">
                    <p style="color: #9ca3af; font-size: 12px;">MediCare Clinic &middot; Better appointments, better care.</p>
                </div>
            </div>
        </div>';
        return self::send($toEmail, $toName, $subject, $body);
    }

    public static function forgotPassword(string $toEmail, string $toName, string $resetToken): bool {
        $resetLink = 'http://localhost:5173/reset-password?token=' . $resetToken;
        $subject   = 'Reset Your Password - MediCare Clinic';
        $body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background-color: #1A365D; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0;">MediCare Clinic</h1>
                <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0;">Password Reset Request</p>
            </div>
            <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 12px 12px;">
                <h2 style="color: #1f2937;">Hello ' . htmlspecialchars($toName) . ',</h2>
                <p style="color: #6b7280;">We received a request to reset your password. Click the button below to set a new password.</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $resetLink . '" style="background-color: #2B6CB0; color: white; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block; font-size: 15px;">Reset Password</a>
                </div>
                <p style="color: #6b7280; font-size: 13px;">If the button does not work, copy and paste this link into your browser:</p>
                <p style="word-break: break-all; font-size: 12px;"><a href="' . $resetLink . '" style="color: #2B6CB0;">' . $resetLink . '</a></p>
                <p style="color: #6b7280; font-size: 13px;">This link expires in <strong>1 hour</strong>. If you did not request a password reset, you can ignore this email.</p>
                <div style="border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 20px; text-align: center;">
                    <p style="color: #9ca3af; font-size: 12px;">MediCare Clinic &middot; Better appointments, better care.</p>
                </div>
            </div>
        </div>';
        $altBody = "Hello {$toName},\n\nReset your password by visiting this link:\n{$resetLink}\n\nThis link expires in 1 hour.\nIf you did not request a password reset, ignore this email.\n\n-- MediCare Clinic";
        return self::send($toEmail, $toName, $subject, $body, $altBody);
    }
}
