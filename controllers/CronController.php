<?php
require_once '../models/email.php';
require_once '../models/users.php';

class CronController {
    private $usersModel;

    public function __construct() {
        $this->usersModel = new Users();
    }

    public function getAnniversaries($startDate = null, $endDate = null) {
        $startDate = $startDate ? date('Y-m-d', strtotime($startDate)) : date('Y-m-d', strtotime('last Monday'));
        $weekNumber = date('W', strtotime($startDate));

        $persons = "";
        $users = $this->usersModel->getUsersWithAnniversary($startDate, $endDate);
        $emails = $this->usersModel->getUsersEmails();
        $template = file_get_contents('../templates/anniversaries.html');
        $chunks = array_chunk($users, 3);
        
        foreach($chunks as $chunk) {
            foreach ($chunk as $index => $user) {
                $personTemplate = file_get_contents('../templates/anniversaries_person.html');
    
                $personTemplate = str_replace('[[NAME]]', $user['full_name'], $personTemplate);
                $personTemplate = str_replace('[[ANNIVERSARY]]', $user['anniversary'], $personTemplate);
                $personTemplate = str_replace('[[PROFILE_PICTURE]]', $user['profile_picture'], $personTemplate);
                $personTemplate = str_replace('[[JOB_POSITION]]', $user['job_position'], $personTemplate);
                $personTemplate = str_replace('[[OFFICE_NAME]]', $user['office_name'], $personTemplate);
    
                if(count($chunk) < 3 && $index == 0) {
                    $personTemplate = "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td align=\"center\">" . $personTemplate . "</td>";
                }
                if(count($chunk) < 3 && $index == count($chunk) - 1) {
                    $personTemplate .= "</tr></table>";
                }

                $colspan = 1;
                if (count($chunk) < 3 && $index == 0) {
                    $colspan = 3;
                } 

                $persons .= "<!-- START OF EMPLOYEE --><td align=\"center\" colspan=\"" . $colspan . "\">" .$personTemplate."</td>";
                if (($index + 1) % 3 == 0) {
                    $persons .= "</tr><tr>";
                } elseif ($index == count($chunk) - 1) {
                    $persons .= "";
                }
                $persons .= "<!-- END OF EMPLOYEE -->";
            }
        }

        // Store the emails in an array
        $recipients = array_map(function($user) {
            return $user['email'];
        }, $emails);
        
        // Validate environment and filter emails when is local, dev or sandbox.
        if (preg_match('/dev/', $_SERVER['HTTP_ORIGIN']) || preg_match('/sandbox/', $_SERVER['HTTP_ORIGIN']) || preg_match('/localhost/', $_SERVER['HTTP_ORIGIN'])) {
            $recipients = array_filter($recipients, function($email) {
                $emailAllowed = ['rsalazar@vittilog.com', 'ebernal@vittilog.com', 'vmolar@vittilog.com'];
                return in_array($email, $emailAllowed);
            });
        }
        
        $template = str_replace('[[EMPLOYEES]]', "<tr>" . $persons . "</tr>", $template);
        $this->sendEmail($recipients, "🥳 ¡Gracias por un año más juntos! - Semana $weekNumber", $template);

        sendJsonResponse(200, [
            'ok' => true,
            'message' => 'Users with anniversaries retrieved successfully.',
            'count' => count($users),
            'users' => $users,
            'info' => ['start_date' => $startDate, 'end_date' => $endDate]
        ]);

        exit();
    }

    public function getBirthdays($date = null) {
        $users = $this->usersModel->getUsersWithBirthday($date);
        $emails = $this->usersModel->getUsersEmails();

        $recipients = array_map(function($user) {
            return $user['email'];
        }, $emails);
        
        if (preg_match('/dev/', $_SERVER['HTTP_ORIGIN']) || preg_match('/sandbox/', $_SERVER['HTTP_ORIGIN']) || preg_match('/localhost/', $_SERVER['HTTP_ORIGIN'])) {
            $recipients = array_filter($recipients, function($email) {
                $emailAllowed = ['rsalazar@vittilog.com', 'ebernal@vittilog.com', 'vmolar@vittilog.com'];
                return in_array($email, $emailAllowed);
            });
            $recipients = array_values($recipients);
        }

        foreach ($users as $user) {
            $template = file_get_contents('../templates/birthdays.html');
            $user['birthday'] = date('d \d\e F', strtotime($user['birth_date']));

            $template = str_replace('[[NAME]]', $user['full_name'], $template);
            $template = str_replace('[[BIRTHDAY]]', $user['birthday'], $template);
            $template = str_replace('[[PROFILE_PICTURE]]', $user['profile_picture'], $template);
            $template = str_replace('[[JOB_POSITION]]', $user['job_position'], $template);
            $template = str_replace('[[OFFICE_NAME]]', $user['office_name'], $template);
            
            $this->sendEmail($recipients, "👏 Celebramos contigo un año más vida. Feliz cumpleaños te desea Vitti Logistics. 🎂", $template);
        }

        sendJsonResponse(200, [
            'ok' => true,
            'message' => 'Users with anniversaries retrieved successfully.',
            'count' => count($users),
            'users' => $users,
            'info' => ['date' => $date]
        ]);
        exit();
    }

    private function sendEmail($email, $subject, $template) {
        // Enviar correo de recuperación de contraseña
        $mail = new Email();
        $send = $mail->send($email, $subject, $template);
        if (!$send) {
            throw new Exception('Error: No se pudo realizar el envío del correo electrónico.');
        }
    }
}

?>