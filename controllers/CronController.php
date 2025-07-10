<?php
// TODO: Validate if email is sent successfully and return the result in the response for both methods.
// TODO: Create a method to validate if any pulbication is schedule to show today and send the email to the marketing team with the publication.

require_once '../models/email.php';
require_once '../models/communication.php';
require_once '../models/users.php';



class CronController {
    private $usersModel;

    private $emailAllowed = [
        // 'rsalazar@vittilog.com',
        // 'ebernal@vittilog.com',
        // 'evargas@vittilog.com',
        // 'vmolar@vittilog.com',
        'mleon@vittilog.com',
        // 'igonzalez@vittilog.com',
        // 'fmartinez@vittilog.com'
    ];

    public function __construct() {
        $this->usersModel = new Users();
        $this->communicationModel = new Communication();
    }

    public function getAnniversaries($startDate = null, $endDate = null, $debug = false) {
        try{
            if($startDate){
                $startDate = date('Y-m-d', strtotime($startDate));
            } else {
                $today = date('Y-m-d');
                if (date('N', strtotime($today)) == 1) {
                    $startDate = date('Y-m-d', strtotime($today));
                } else {
                    $startDate = date('Y-m-d', strtotime('last Monday'));
                }
            }
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
                    return in_array($email, $this->emailAllowed);
                });
                $recipients = array_values($recipients);
            }
            
            $subject = "🥳 ¡Gracias por un año más juntos! - Semana $weekNumber";
            if ($debug) $subject = "[CORREO DE PRUEBA] " . $subject;
            $template = str_replace('[[EMPLOYEES]]', "<tr>" . $persons . "</tr>", $template);
            $this->sendEmail($recipients, $subject, $template);

            sendJsonResponse(200, [
                'ok' => true,
                'message' => 'Users with anniversaries retrieved successfully.',
                'count' => count($users),
                'users' => $users,
                'info' => ['start_date' => $startDate, 'end_date' => $endDate],
                'recipients' => $recipients,
                'debug' => $debug
            ]);
        }catch(Exception $error) {
            handleError(400, $error->getMessage());
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
                    return in_array($email, $this->emailAllowed);
                });
                $recipients = array_values($recipients);
            }
            
            foreach ($users as $user) {
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
                $outputPath = __DIR__ . "/../public/birthday-cards/user-{$user["id"]}.png";

                $this->generateImage(
                    __DIR__ . '/../public/birthday_base.png',
                    $user['profile_picture'] ?? $dummyImage,
                    explode(" ", $user['first_name'])[0] . ' ' . $user['last_name_1'],
                    $user['job_position'],
                    $user['birthday'],
                    $outputPath
                );

                $template = "<p style='font-size: 0px; color: transparent; margin: 0; padding: 0; line-height: 0;'>Feliz cumpleaños {$user["full_name"]}. En este día especial, queremos felicitarte y agradecerte por tu esfuerzo y dedicación. Esperamos que este nuevo año de vida esté lleno de grandes logros, alegría y momento inolvidables junto a tus seres queridos . ¡Disfruta tu día al máximo!</p>
                    <table border='0' celspacing='0' width='100%'>
                        <tr>
                            <td>&nbsp;</td>
                            <td width='600'>
                                <img src='cid:user-{$user["id"]}' alt='Feliz cumpleaños {$user["full_name"]}' style='width: 100%; height: auto; max-width: 600px; margin: 0 auto; display: block;' width='600'>
                            </td>
                            <td>&nbsp;</td>
                        </tr>
                    </table>";
                
                $subject = "🎉 ¡Feliz cumpleaños {$user["first_name"]} {$user["last_name_1"]}! 🎂";
                if ($debug) $subject = "[CORREO DE PRUEBA] " . $subject;
                $this->sendEmail($recipients, $subject, $template, $outputPath, "user-{$user["id"]}");

                if (file_exists($outputPath)) {
                    unlink($outputPath);
                }
            }
    
            sendJsonResponse(200, [
                'ok' => true,
                'message' => 'User birthdays retrieved and send successfully.',
                'count' => count($users),
                'users' => $users,
                'info' => ['date' => $date],
                'recipients' => $recipients,
                'debug' => $debug
            ]);
        }catch(Exception $error) {
            handleError(400, $error->getMessage());
        }

        exit();
    }

    public function getAllCommunications($date = null,$debug = false) {
        $comunications = $this->communicationModel->getAllCronPosts(); 

        if(empty($comunications)) return sendJsonResponse(200, [
            'ok' => true,
            'message' => 'There is not communication in this moment',
            'count' => count($comunications),
            'comunications' => $comunications,
            'info' => ['date' => $date],
            'debug' => $debug
        ]);

        foreach($comunications as $com) {
            $emails = $this->usersModel->getAllCommunicationUsers($com['fk_job_position_type_id']);

            $recipients = array_map(function($user) {
                return $user['email'];
            }, $emails);

            $recipients = array_filter($recipients, function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
                
            if (preg_match('/dev/', $_SERVER['HTTP_ORIGIN']) || preg_match('/sandbox/', $_SERVER['HTTP_ORIGIN']) || preg_match('/localhost/', $_SERVER['HTTP_ORIGIN']) || $debug) {
                $recipients = array_filter($recipients, function($email) {
                    return in_array($email, $this->emailAllowed);
                });
                $recipients = array_values($recipients);
            }

            $template = file_get_contents('../templates/communicationEmail.html');
            $image = "";
            $outputPath = "";
            $imagePath = "";
            if(isset($com['file'])) {
                $file = base64_decode($com['file']);
                $outputPath = __DIR__ . "/../public/birthday-cards/communication-{$com["pk_post_id"]}.png";
                $imagePath = "communication-{$com["pk_post_id"]}";
                $file = file_put_contents($outputPath, $file);
                $image .= "<img src='cid:communication-{$com["pk_post_id"]}' alt='".$com['title']."' width='600' style='display: block' />";
            }

            $template = str_replace('{{comunicationTitle}}', $com['title'], $template);
            $template = str_replace('{{comunicationImage}}', $image, $template);
            $template = str_replace('{{comunicationText}}', $com['content'], $template);


            $HTTP_HOST = null;
            if ($_SERVER['HTTP_HOST'] === 'localhost') {
                // $HTTP_HOST = 'http://localhost:3000';
                $HTTP_HOST = 'http://localhost:5173';
            }
            else {
                $HTTP_HOST = $_SERVER['HTTP_ORIGIN'];
            }

            // $urlLink = $HTTP_HOST."/internal-communication?id=".$com['pk_post_id'];
            $urlLink = $HTTP_HOST."/internal-communication";
            if($com['fk_post_type_id'] === 3) {
                // $urlLink = $HTTP_HOST."/internal-communication/c4?id=".$com['pk_post_id'];
                $urlLink = $HTTP_HOST."/internal-communication/c4";
            }
            $template = str_replace('{{comunicationLink}}', $urlLink, $template);

            $subject = "¡Hay novedades importantes, consulta el nuevo comunicado!";
            if ($debug) $subject = "[CORREO DE PRUEBA] " . $subject;
            // $this->sendEmail($recipients, $subject, $template, "path/to/save.png", "communication-image");
            $this->sendEmail($recipients, $subject, $template, $outputPath, $imagePath);

            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }

        sendJsonResponse(200, [
            'ok' => true,
            'message' => 'Communication retrieved and send successfully.',
            'count' => count($comunications),
            'comunications' => $comunications,
            'info' => ['date' => $date],
            'recipients' => $recipients,
            'debug' => $debug
        ]);
    }

    private function sendEmail($email, $subject, $template, $attachmentPath = null, $attachmentEmbeddedName = null) {
        array_unshift($email, 'no-reply@vittilog.com');
        $email = array_unique($email);
        $email = array_filter($email, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        
        $mail = new Email();
        $send = $mail->send($email, $subject, $template, $attachmentPath, $attachmentEmbeddedName, false);
        if (!$send) {
            throw new Exception('Error: No se pudo realizar el envío del correo electrónico.');
        }
    }

    private function generateImage(string $basePath, string $photoUrl, string $name, string $position, string $date, string $outputPath){
        $base = imagecreatefrompng($basePath);
        imagealphablending($base, true);
        imagesavealpha($base, true);

        $photoData = @file_get_contents($photoUrl);
        if (!$photoData) {
            http_response_code(400);
            exit("No se pudo cargar la imagen desde la URL.");
        }

        $photo = @imagecreatefromstring($photoData);
        if (!$photo) {
            http_response_code(400);
            exit("Formato de imagen no soportado o corrupto.");
        }

        $size = 435;
        $photoResized = imagescale($photo, $size, $size);

        $circle = imagecreatetruecolor($size, $size);
        imagesavealpha($circle, true);
        $transparent = imagecolorallocatealpha($circle, 0, 0, 0, 127);
        imagefill($circle, 0, 0, $transparent);

        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $dx = $x - $size / 2;
                $dy = $y - $size / 2;
                if (sqrt($dx * $dx + $dy * $dy) <= $size / 2) {
                    $color = imagecolorat($photoResized, $x, $y);
                    imagesetpixel($circle, $x, $y, $color);
                }
            }
        }
        imagecopy($base, $circle, 645, 368, 0, 0, $size, $size);

        $white = imagecolorallocate($base, 255, 255, 255);
        $fontBoldPath = __DIR__ . '/../public/Raleway-Bold.ttf';
        $fontPath = __DIR__ . '/../public/Raleway-Regular.ttf';

        $this->drawCenteredText($base, $name, 40, 1380, $white, $fontBoldPath);
        $maxWidth = 1000; // píxeles permitidos
        $lineSpacing = 10;

        $lines = $this->wrapTextByWidth($position, $fontPath, 35, $maxWidth);
        $countLines = count($lines);
        $this->drawMultilineCenteredText($base, $lines, 35, 1490, $lineSpacing, $white, $fontBoldPath);
        $this->drawCenteredText($base, $date, 35, 1490 + (50 * $countLines), $white, $fontPath);

        imagepng($base, $outputPath);

        imagedestroy($base);
        imagedestroy($photo);
        imagedestroy($photoResized);
    }

    private function drawCenteredText($image, $text, $fontSize, $y, $color, $fontPath) {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (imagesx($image) - $textWidth) / 2;
        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
    }

    private function wrapTextByWidth($text, $fontPath, $fontSize, $maxWidth){
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine ? $currentLine . ' ' . $word : $word;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
            $lineWidth = $bbox[2] - $bbox[0];

            if ($lineWidth > $maxWidth && $currentLine) {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    private function drawMultilineCenteredText($image, $lines, $fontSize, $startY, $lineSpacing, $color, $fontPath) {
        $imageWidth = imagesx($image);
        foreach ($lines as $i => $line) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $textWidth = $bbox[2] - $bbox[0];
            $x = ($imageWidth - $textWidth) / 2;
            $y = $startY + $i * ($fontSize + $lineSpacing);
            imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $line);
        }
    }

}

?>