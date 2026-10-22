<?php

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
                'User-Agent: Belly-CRM/1.0',
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
