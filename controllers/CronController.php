<?php
// TODO: Validate if email is sent successfully and return the result in the response for both methods.

require_once '../models/email.php';
require_once '../models/users.php';

class CronController {
    private $usersModel;

    public function __construct() {
        $this->usersModel = new Users();
    }

    public function getAnniversaries($startDate = null, $endDate = null, $debug = false) {
        try{
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
                    $months = [
                        'January' => 'Enero',
                        'February' => 'Febrero',
                        'March' => 'Marzo',
                        'April' => 'Abril',
                        'May' => 'Mayo',
                        'June' => 'Junio',
                        'July' => 'Julio',
                        'August' => 'Agosto',
                        'September' => 'Septiembre',
                        'October' => 'Octubre',
                        'November' => 'Noviembre',
                        'December' => 'Diciembre'
                    ];
                    
                    $timestamp = strtotime($user['date_of_hire']);
                    $day = date('d', $timestamp);
                    $monthEnglish = date('F', $timestamp);
                    $monthSpanish = $months[$monthEnglish];
                    
                    $date = "$day de $monthSpanish";
        
                    $dummyImage = "https://vicafiles.blob.core.windows.net/files/dummy_avatar.png";
                    $personTemplate = str_replace('[[NAME]]', $user['full_name'], $personTemplate);
                    $personTemplate = str_replace('[[ANNIVERSARY]]', $user['anniversary'], $personTemplate);
                    $personTemplate = str_replace('[[PROFILE_PICTURE]]', $user['profile_picture'] ?? $dummyImage, $personTemplate);
                    $personTemplate = str_replace('[[JOB_POSITION]]', $user['job_position'], $personTemplate);
                    $personTemplate = str_replace('[[OFFICE_NAME]]', $user['office_name'], $personTemplate);
                    $personTemplate = str_replace('[[DATE]]', $date, $personTemplate);
        
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

            $recipients = array_filter($recipients, function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
            
            // Validate environment and filter emails when is local, dev or sandbox.
            if (preg_match('/dev/', $_SERVER['HTTP_ORIGIN']) || preg_match('/sandbox/', $_SERVER['HTTP_ORIGIN']) || preg_match('/localhost/', $_SERVER['HTTP_ORIGIN']) || $debug) {
                $recipients = array_filter($recipients, function($email) {
                    $emailAllowed = ['rsalazar@vittilog.com', 'ebernal@vittilog.com', 'evargas@vittilog.com', 'vmolar@vittilog.com'];
                    return in_array($email, $emailAllowed);
                });
                $recipients = array_values($recipients);
            }
            
            $template = str_replace('[[EMPLOYEES]]', "<tr>" . $persons . "</tr>", $template);
            $this->sendEmail($recipients, "🥳 ¡Gracias por un año más juntos! - Semana $weekNumber", $template);

            sendJsonResponse(200, [
                'ok' => true,
                'message' => 'Users with anniversaries retrieved successfully.',
                'count' => count($users),
                'users' => $users,
                'info' => ['start_date' => $startDate, 'end_date' => $endDate],
                'recipients' => $recipients,
            ]);
        }catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    public function getBirthdays($date = null, $debug = false) {
        try{
            $users = $this->usersModel->getUsersWithBirthday($date);
            $emails = $this->usersModel->getUsersEmails();
            $recipients = array_map(function($user) {
                return $user['email'];
            }, $emails);

            $recipients = array_filter($recipients, function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
                
            if (preg_match('/dev/', $_SERVER['HTTP_ORIGIN']) || preg_match('/sandbox/', $_SERVER['HTTP_ORIGIN']) || preg_match('/localhost/', $_SERVER['HTTP_ORIGIN']) || $debug) {
                $recipients = array_filter($recipients, function($email) {
                    $emailAllowed = ['rsalazar@vittilog.com', 'ebernal@vittilog.com', 'evargas@vittilog.com', 'vmolar@vittilog.com'];
                    return in_array($email, $emailAllowed);
                });
                $recipients = array_values($recipients);
            }
            
            foreach ($users as $user) {
                $template = file_get_contents('../templates/birthdays.html');
                $months = [
                    'January' => 'Enero',
                    'February' => 'Febrero',
                    'March' => 'Marzo',
                    'April' => 'Abril',
                    'May' => 'Mayo',
                    'June' => 'Junio',
                    'July' => 'Julio',
                    'August' => 'Agosto',
                    'September' => 'Septiembre',
                    'October' => 'Octubre',
                    'November' => 'Noviembre',
                    'December' => 'Diciembre'
                ];
                
                $timestamp = strtotime($user['birth_date']);
                $day = date('d', $timestamp);
                $monthEnglish = date('F', $timestamp);
                $monthSpanish = $months[$monthEnglish];
                
                $user['birthday'] = "$day de $monthSpanish";
                
                $dummyImage = "https://vicafiles.blob.core.windows.net/files/dummy_avatar.png";
                $template = str_replace('[[NAME]]', $user['full_name'], $template);
                $template = str_replace('[[BIRTHDAY]]', $user['birthday'], $template);
                $template = str_replace('[[PROFILE_PICTURE]]', $user['profile_picture'] ?? $dummyImage, $template);
                $template = str_replace('[[JOB_POSITION]]', $user['job_position'], $template);
                $template = str_replace('[[OFFICE_NAME]]', $user['office_name'], $template);
                
                $this->sendEmail($recipients, "👏 Feliz cumpleaños te desea Vitti Logistics. 🎂", $template);
            }
    
            sendJsonResponse(200, [
                'ok' => true,
                'message' => 'Users with anniversaries retrieved successfully.',
                'count' => count($users),
                'users' => $users,
                'info' => ['date' => $date],
                'recipients' => $recipients,
            ]);
        }catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }

    private function sendEmail($email, $subject, $template) {
        // Enviar correo de recuperación de contraseña
        $mail = new Email();
        $send = $mail->send($email, $subject, $template, false);
        if (!$send) {
            throw new Exception('Error: No se pudo realizar el envío del correo electrónico.');
        }
    }
}

?>