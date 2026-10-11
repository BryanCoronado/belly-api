<?php

if (!function_exists('subirArchivoSFTP')) {
    function subirArchivoSFTP(string $rutaLocal, string $rutaRemota): array
    {
        try {
            if (!file_exists($rutaLocal)) {
                throw new \Exception("El archivo local no existe: $rutaLocal");
            }

            $sftpHost = env('SFTP_HOST', 'driver.vip2sistem.com.pe');
            $sftpPort = (int) env('SFTP_PORT', 22);
            $sftpUser = trim(env('SFTP_USER', ''));
            $sftpPass = trim(env('SFTP_PASS', ''));
            $sftpBaseUrl = env('SFTP_BASE_URL', 'https://driver.vip2sistem.com.pe');
            $sftpBasePath = env('SFTP_BASE_PATH', '/upload');

            if (empty($sftpUser) || empty($sftpPass)) {
                throw new \Exception("Credenciales SFTP no configuradas");
            }

            $tipoCarpeta = str_starts_with($rutaRemota, 'downloads/') ? 'downloads' : 'uploads';
            $rutaRelativa = preg_replace('/^(downloads|uploads)\//', '', $rutaRemota);
            
            $rutaCompletaRemota = rtrim($sftpBasePath, '/') . '/crm/modulo/' . $tipoCarpeta . '/' . $rutaRelativa;
            $directorioRemoto = dirname($rutaCompletaRemota);
            $publicUrl = rtrim($sftpBaseUrl, '/') . '/crm/modulo/' . $tipoCarpeta . '/' . $rutaRelativa;

            // phpseclib
            if (class_exists('\phpseclib4\Net\SFTP')) {
                try {
                    $sftp = new \phpseclib4\Net\SFTP($sftpHost, $sftpPort);
                    if (!$sftp->login($sftpUser, $sftpPass)) {
                        throw new \Exception("Error de autenticación");
                    }

                    $partes = explode('/', trim($directorioRemoto, '/'));
                    $ruta = '';
                    foreach ($partes as $p) {
                        if (empty($p)) continue;
                        $ruta .= '/' . $p;
                        if (!$sftp->is_dir($ruta)) {
                            $sftp->mkdir($ruta, -1, true);
                        }
                    }

                    if (!$sftp->put($rutaCompletaRemota, $rutaLocal, \phpseclib4\Net\SFTP::SOURCE_LOCAL_FILE)) {
                        throw new \Exception("Error al subir archivo");
                    }

                    return ['success' => true, 'url' => $publicUrl];
                } catch (\Exception $e) {
                    log_message('error', 'SFTP phpseclib: ' . $e->getMessage());
                }
            }

            // ssh2
            if (extension_loaded('ssh2')) {
                try {
                    $conn = @ssh2_connect($sftpHost, $sftpPort);
                    if (!$conn || !@ssh2_auth_password($conn, $sftpUser, $sftpPass)) {
                        throw new \Exception("Error de conexión/autenticación");
                    }

                    $sftp = @ssh2_sftp($conn);
                    if (!$sftp) {
                        throw new \Exception("Error al inicializar SFTP");
                    }

                    $partes = explode('/', trim($directorioRemoto, '/'));
                    $ruta = '';
                    foreach ($partes as $p) {
                        if (empty($p)) continue;
                        $ruta .= '/' . $p;
                        $rutaSFTP = "ssh2.sftp://{$sftp}{$ruta}";
                        if (!@is_dir($rutaSFTP)) {
                            @mkdir($rutaSFTP, 0777, true);
                        }
                    }

                    if (!@copy($rutaLocal, "ssh2.sftp://{$sftp}{$rutaCompletaRemota}")) {
                        throw new \Exception("Error al subir archivo");
                    }

                    return ['success' => true, 'url' => $publicUrl];
                } catch (\Exception $e) {
                    log_message('error', 'SFTP ssh2: ' . $e->getMessage());
                }
            }

            // cURL
            $ch = curl_init();
            $fp = fopen($rutaLocal, 'r');
            curl_setopt_array($ch, [
                CURLOPT_URL => "sftp://{$sftpUser}:{$sftpPass}@{$sftpHost}:{$sftpPort}{$rutaCompletaRemota}",
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $fp,
                CURLOPT_INFILESIZE => filesize($rutaLocal),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSH_AUTH_TYPES => CURLSSH_AUTH_PASSWORD,
                CURLOPT_TIMEOUT => 30
            ]);

            $result = curl_exec($ch);
            $error = curl_error($ch);
            fclose($fp);
            curl_close($ch);

            if ($result !== false && empty($error)) {
                return ['success' => true, 'url' => $publicUrl];
            }

            throw new \Exception("Error al subir archivo a SFTP: " . ($error ?: 'Error desconocido'));

        } catch (\Exception $e) {
            log_message('error', 'subirArchivoSFTP: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('subirContenidoASFTP')) {
    function subirContenidoASFTP($archivoOrContenido, $filename): array
    {
        if (empty(trim(env('SFTP_USER', ''))) || empty(trim(env('SFTP_PASS', '')))) {
            throw new \Exception("Credenciales SFTP no configuradas");
        }

        if (!function_exists('\subirArchivoSFTP')) {
            \helper('file_helper');
        }

        $esArchivo = is_string($archivoOrContenido) && file_exists($archivoOrContenido);
        $tmpFile = null;
        
        if (!$esArchivo) {
            $tmpFile = sys_get_temp_dir() . '/' . uniqid('media_', true);
            file_put_contents($tmpFile, $archivoOrContenido);
            $archivoOrContenido = $tmpFile;
        }

        try {
            $resultado = \subirArchivoSFTP($archivoOrContenido, $filename);
            
            if (!$resultado['success']) {
                throw new \Exception($resultado['error'] ?? 'Error desconocido al subir a SFTP');
            }

            return $resultado;
        } finally {
            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }
}

if (!function_exists('resumenDiagnosticoTokenWhatsapp')) {
    function resumenDiagnosticoTokenWhatsapp(string $access_token): string
    {
        $access_token = trim($access_token);
        if ($access_token === '') {
            return 'token vacío';
        }

        $endpoint = 'https://graph.facebook.com/v19.0/debug_token?'
            . http_build_query(['input_token' => $access_token, 'access_token' => $access_token]);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $rawResponse, true);
        $info = is_array($data['data'] ?? null) ? $data['data'] : [];
        if ($httpCode !== 200 || $info === []) {
            return 'debug_token HTTP ' . $httpCode;
        }

        $valido = ($info['is_valid'] ?? false) === true ? 'si' : 'no';
        $expira = isset($info['expires_at']) && (int) $info['expires_at'] > 0
            ? date('Y-m-d H:i:s', (int) $info['expires_at'])
            : 'sin_expiracion';
        $scopes = implode(',', (array) ($info['scopes'] ?? []));

        return "valido={$valido} expira={$expira} scopes={$scopes}";
    }
}

if (!function_exists('obtenerDatosMediaWhatsapp')) {
    function obtenerDatosMediaWhatsapp($media_id, $access_token): array
    {
        $access_token = trim((string) $access_token);
        $intentos = [
            [
                'url' => "https://graph.facebook.com/v19.0/{$media_id}",
                'headers' => ["Authorization: Bearer {$access_token}"],
            ],
            [
                'url' => 'https://graph.facebook.com/v19.0/' . $media_id . '?' . http_build_query(['access_token' => $access_token]),
                'headers' => [],
            ],
        ];

        $ultimo = [
            'url' => null,
            'mime_type' => 'application/octet-stream',
            'http_code' => 0,
            'curl_error' => '',
            'error' => null,
            'raw_response' => null,
        ];

        foreach ($intentos as $intento) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $intento['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => $intento['headers'],
            ]);

            $rawResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $response = json_decode((string) $rawResponse, true);
            if (!is_array($response)) {
                $response = [];
            }

            $ultimo = [
                'url' => $response['url'] ?? null,
                'mime_type' => $response['mime_type'] ?? 'application/octet-stream',
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'error' => $response['error']['message'] ?? null,
                'raw_response' => is_string($rawResponse) ? $rawResponse : null,
            ];

            if (!empty($ultimo['url'])) {
                return $ultimo;
            }
        }

        return $ultimo;
    }
}

if (!function_exists('descargarContenidoURL')) {
    function descargarContenidoURL($url, $access_token): string
    {
        $url = trim((string) $url);
        $access_token = trim((string) $access_token);
        if ($url === '') {
            throw new \Exception('URL de descarga vacía.');
        }
        if ($access_token === '') {
            throw new \Exception('Token de descarga vacío.');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_UNRESTRICTED_AUTH => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$access_token}",
                'User-Agent: CIMAK-CRM/1.0',
            ],
        ]);

        $contenido = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($contenido)) {
            $detalle = $curlError !== '' ? " cURL: {$curlError}" : '';
            $host = parse_url($url, PHP_URL_HOST) ?: 'desconocido';
            throw new \Exception("Error descargando archivo desde {$host}. HTTP Code: {$httpCode}{$detalle}");
        }

        return $contenido;
    }
}

if (!function_exists('detectarTipoMedia')) {
    function detectarTipoMedia($mimeType): string
    {
        return match(true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            $mimeType === 'application/pdf' => 'document',
            str_starts_with($mimeType, 'application/') => 'document',
            default => 'document'
        };
    }
}

if (!function_exists('obtenerExtensionDocumento')) {
    function obtenerExtensionDocumento($mimeType): string
    {
        return match($mimeType) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/html' => 'html',
            'text/css' => 'css',
            'application/javascript' => 'js',
            'application/octet-stream' => 'bin',
            default => 'bin'
        };
    }
}

if (!function_exists('generarNombreArchivoMedia')) {
    function generarNombreArchivoMedia($media_id, $tipo, $id_conversacion, $mimeType = null, $prefijo = 'downloads'): string
    {
        $extension = match($tipo) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'ogg',
            'document' => obtenerExtensionDocumento($mimeType),
            'sticker' => 'webp',
            default => 'bin'
        };
        
        $fecha = date('Y-m-d');
        $conversacion_folder = $id_conversacion ?? 'general';
        return "{$prefijo}/{$fecha}/{$conversacion_folder}/{$tipo}/{$media_id}.{$extension}";
    }
}

