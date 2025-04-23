<?php
require_once '../libs/PHPMailer/src/PHPMailer.php';
require_once '../libs/PHPMailer/src/SMTP.php';
require_once '../libs/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

Class Email {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
    }

    public function send($to, $subject, $message, $return = true) {
        if(is_string($to)){
            if(strpos($to, ',') !== false) {
                $addresses = explode(',', $to);
            } else {
                $addresses = array($to);
            }
        } else {
            $addresses = $to;
        }

        try {
            $this->mail->SMTPDebug = SMTP::DEBUG_OFF;
            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.office365.com';
            $this->mail->SMTPAuth = true;
            $this->mail->Username = 'no-reply@vittilog.com';
            $this->mail->Password = getenv('NOREPLY_MAIL_PASSWORD');
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = 587;

            $this->mail->setFrom('no-reply@vittilog.com', 'Equipo RH - VICA (no-reply)');

            $address = array_shift($addresses);
            $this->mail->addAddress($address, $address);
            foreach ($addresses as $address) {
                $to = trim($address);
                if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $this->mail->addCC($to, $to);
                } else {
                    throw new Exception("Invalid email address: {$to}");
                }
            }
            
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $message;
            return $this->mail->send();
        }
        catch (Exception $error) {
            if($return) {
                return "El mensaje no pudo ser enviado. Error: {$this->mail->ErrorInfo}";
            } else {
                throw new Exception('Error: No se pudo realizar el envío del correo electrónico. Error: ' . $error->getMessage() . ' - ' . json_encode([$address, $addresses]));
            }	
        }
    }
}

?>