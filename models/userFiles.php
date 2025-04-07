<?php
require_once '../config/config.php';

Class UserFiles {
    private $dbConnection;

    const TYPE_PROFILE_PICTURE = 1;
    const TYPE_SIGNATURE = 2;

    public function __construct() {
        $this->dbConnection = dbConnection();
    }

    public function upload($userId, $typeFile, $file) {
        try {
            if ($typeFile == self::TYPE_PROFILE_PICTURE) {
                if (!$userId || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error: No se pudo cargar la imagen.');
                }
        
                $filePath = $_FILES['profile_picture']['tmp_name'];
                $fileName = $_FILES['profile_picture']['name'];
                $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                $fileSize = $_FILES['profile_picture']['size'];
                
                if ($fileSize > 5000000) { // 5MB como ejemplo
                    throw new Exception('Error: El archivo cargado es demasiado grande.');
                }
        
                $imageType = mime_content_type($filePath);
                if (!in_array($imageType, ['image/jpeg', 'image/png', 'image/gif'])) {
                    throw new Exception('Error: El archivo cargado no es una imagen válida.');
                }

                // Intentamos abrir el archivo para lectura
                $fopen = fopen($filePath, 'r');
                if (!$fopen) {
                    throw new Exception('Error: El archivo no se puede abrir.');
                }

                // Inicializar cURL
                $blobName = rawurlencode($fileName);
                $SAS_TOKEN = getenv('SAS_TOKEN');
                $url = "https://vicafiles.blob.core.windows.net/users/{$blobName}?{$SAS_TOKEN}";
                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_PUT, true);
                curl_setopt($curl, CURLOPT_INFILE, $fopen);
                curl_setopt($curl, CURLOPT_INFILESIZE, filesize($filePath));
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    "x-ms-blob-type: BlockBlob",
                    "Content-Type: " . mime_content_type($filePath),
                ]);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_TIMEOUT, 30);
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($response === false) {
                    $error = curl_error($curl);
                    curl_close($curl);
                    fclose($fopen);
                    throw new Exception("Error curl: $error.");
                }

                curl_close($curl);
                fclose($fopen);

                if ($httpCode === 201) {
                    $fileUrl = "https://vicafiles.blob.core.windows.net/users/{$blobName}";
                    $this->dbConnection->beginTransaction();
                    $sql1 = "SELECT pk_file_id FROM [user].[files] WHERE fk_user_id = :fk_user_id AND type_file = :type_file";
                    $stmt1 = $this->dbConnection->prepare($sql1);
                    $stmt1->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
                    $stmt1->bindParam(':type_file', $typeFile, PDO::PARAM_INT);
                    $stmt1->execute();
                    $exists = $stmt1->fetch(PDO::FETCH_ASSOC);
                    $message = '';
                    if ($exists) {
                        $sql2 = "UPDATE [user].[files] SET [file] = :file, file_name = :file_name, file_extension = :file_extension, file_size = :file_size
                                WHERE pk_file_id = :pk_file_id";
                        $stmt2 = $this->dbConnection->prepare($sql2);
                        $stmt2->bindParam(':file', $fileUrl, PDO::PARAM_STR);
                        $stmt2->bindParam(':file_name', $fileName, PDO::PARAM_STR);
                        $stmt2->bindParam(':file_extension', $fileExt, PDO::PARAM_STR);
                        $stmt2->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
                        $stmt2->bindParam(':pk_file_id', $exists['pk_file_id'], PDO::PARAM_INT);
                        if (!$stmt2->execute()) {
                            throw new Exception('Error: No se pudo actualizar la fotografía de perfil.');
                        }
                        $message = 'Fotografía de perfil actualizada exitosamente.';
                    }
                    else {
                        $sql3 = "INSERT INTO [user].[files] (fk_user_id, [file], file_name, file_extension, file_size, type_file) 
                                VALUES (:fk_user_id, :file, :file_name, :file_extension, :file_size, :type_file)";
                        $stmt3 = $this->dbConnection->prepare($sql3);
                        $stmt3->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
                        $stmt3->bindParam(':file', $fileUrl, PDO::PARAM_STR);
                        $stmt3->bindParam(':file_name', $fileName, PDO::PARAM_STR);
                        $stmt3->bindParam(':file_extension', $fileExt, PDO::PARAM_STR);
                        $stmt3->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
                        $stmt3->bindParam(':type_file', $typeFile, PDO::PARAM_INT);
                        if (!$stmt3->execute()) {
                            throw new Exception('Error: No se pudo cargar la fotografía de perfil.');
                        }
                        $message = 'Fotografía de perfil cargada exitosamente.';
                    }

                    $this->dbConnection->commit();
                    sendJsonResponse(200, ['ok' => true, 'message' => $message]);
                }
            }
            elseif ($typeFile == self::TYPE_SIGNATURE) {
                if (!$userId || !$file) {
                    throw new Exception('Error: Id de usuario y/o imagen no válidos.');
                }
                
                $this->dbConnection->beginTransaction();

                $sql1 = "SELECT pk_file_id FROM [user].[files] WHERE fk_user_id = :fk_user_id AND type_file = :type_file";
                $stmt1 = $this->dbConnection->prepare($sql1);
                $stmt1->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
                $stmt1->bindParam(':type_file', $typeFile, PDO::PARAM_INT);
                $stmt1->execute();
                $exists = $stmt1->fetch(PDO::FETCH_ASSOC);
                $message = null;
                if ($exists) {
                    $sql2 = 'UPDATE [user].[files] SET [file] = :file WHERE pk_file_id = :pk_file_id';
                    $stmt2 = $this->dbConnection->prepare($sql2);
                    $stmt2->bindParam(':file', $file, PDO::PARAM_STR);
                    $stmt2->bindParam(':pk_file_id', $exists['pk_file_id'], PDO::PARAM_INT);
                    if (!$stmt2->execute()) {
                        throw new Exception('Error: No se pudo actualizar la firma digital.');
                    }
                    $message = 'Firma digital actualizada exitosamente.';
                }
                else {
                    $sql3 = "INSERT INTO [user].[files] (fk_user_id, [file], file_name, file_extension, file_size, type_file) 
                             VALUES (:fk_user_id, :file, :file_name, :file_extension, :file_size, :type_file)";
                    $stmt3 = $this->dbConnection->prepare($sql3);
                    $fileName = 'signature.png';
                    $fileExt = 'png';
                    $fileSize = 5503;
                    $stmt3->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
                    $stmt3->bindParam(':file', $file, PDO::PARAM_STR);
                    $stmt3->bindParam(':file_name', $fileName, PDO::PARAM_STR);
                    $stmt3->bindParam(':file_extension', $fileExt, PDO::PARAM_STR);
                    $stmt3->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
                    $stmt3->bindParam(':type_file', $typeFile, PDO::PARAM_INT);
                    if (!$stmt3->execute()) {
                        throw new Exception('Error: No se pudo cargar la firma digital.');
                    }
                    $message = 'Firma digital cargada exitosamente.';
                }
                
                $this->dbConnection->commit();
                sendJsonResponse(200, ['ok' => true, 'message' => $message]);
            }
            else {
                throw new Exception('Error: No se recibió el tipo de archivo a subir.');
            }
        }
        catch (Exception $error) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
    
            handleExceptionError($error);
        }

        exit();
    }

    public function getByType($userId, $typeFile) {
        try {
            $sql = 'SELECT [file] FROM [user].[files] WHERE fk_user_id = :fk_user_id AND type_file = :type_file;';
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':fk_user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':type_file', $typeFile, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            sendJsonResponse(200, ['ok' => true, 'file' => isset($result['file']) ? $result['file'] : null]);
        }
        catch(Exception $error) {
            handleExceptionError($error);
        }

        exit();
    }
}

?>