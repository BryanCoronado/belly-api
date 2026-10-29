<?php
namespace Belly\Media\UseCases;

use Belly\Media\Config\MediaConfig;
use Config\App;

class MediaUseCase {
    protected MediaConfig $config;
    protected App $appConfig;

    public function __construct(MediaConfig $config)
    {
        $this->config = $config;
        $this->appConfig = config('App');
    }

    public function procesarMedia(
        $media_id,
        $tipo,
        $app_id,
        $id_conversacion,
        ?string $webhook_url = null,
        ?string $mime_type = null,
    ) {
        try {
            $app = app()->obtenerApp('id', $app_id);
            $accessToken = trim((string) ($app->access_token ?? ''));
            if (!$app || $accessToken === '') {
                throw new \Exception("No se encontró el token para la app: $app_id");
            }

            if (!function_exists('\obtenerDatosMediaWhatsapp')) {
                \helper('file_helper');
            }

            $mime_resuelto = trim((string) $mime_type) !== '' ? trim((string) $mime_type) : 'application/octet-stream';
            $media_data = \obtenerDatosMediaWhatsapp($media_id, $accessToken);
            if (trim((string) ($media_data['mime_type'] ?? '')) !== '') {
                $mime_resuelto = (string) $media_data['mime_type'];
            }

            $candidatas = [];
            foreach ([
                trim((string) ($media_data['url'] ?? '')),
                trim((string) $webhook_url),
            ] as $url) {
                if ($url !== '' && ! in_array($url, $candidatas, true)) {
                    $candidatas[] = $url;
                }
            }

            if ($candidatas === []) {
                $detalle = [];
                if (!empty($media_data['http_code'])) {
                    $detalle[] = 'HTTP ' . $media_data['http_code'];
                }
                if (!empty($media_data['error'])) {
                    $detalle[] = (string) $media_data['error'];
                }
                if (!empty($media_data['curl_error'])) {
                    $detalle[] = 'cURL: ' . $media_data['curl_error'];
                }

                $mensaje = "No se pudo obtener la URL para el media_id: $media_id";
                if ($detalle !== []) {
                    $mensaje .= ' (' . implode(' - ', $detalle) . ')';
                }

                throw new \Exception($mensaje);
            }

            $filename = \generarNombreArchivoMedia($media_id, $tipo, $id_conversacion, $mime_resuelto, 'downloads');
            $contenido = null;
            $erroresDescarga = [];
            foreach ($candidatas as $url) {
                try {
                    $contenido = \descargarContenidoURL($url, $accessToken);
                    break;
                } catch (\Exception $e) {
                    $erroresDescarga[] = $e->getMessage();
                }
            }

            if ($contenido === null || $contenido === '') {
                $detalleGraph = [];
                if (!empty($media_data['http_code'])) {
                    $detalleGraph[] = 'Graph HTTP ' . $media_data['http_code'];
                }
                if (!empty($media_data['error'])) {
                    $detalleGraph[] = (string) $media_data['error'];
                }
                $tokenDiag = function_exists('\resumenDiagnosticoTokenWhatsapp')
                    ? \resumenDiagnosticoTokenWhatsapp($accessToken)
                    : 'sin_diagnostico';

                throw new \Exception(
                    'No se pudo descargar media_id ' . $media_id
                    . ( $detalleGraph !== [] ? ' | Graph: ' . implode(' - ', $detalleGraph) : '' )
                    . ' | Descarga: ' . implode(' | ', $erroresDescarga)
                    . ' | Token app ' . $app_id . ': ' . $tokenDiag
                    . ' | Acción: renueve el access_token de la app en Meta (permiso whatsapp_business_messaging).',
                );
            }
            $this->guardarEnDownloads($contenido, $filename);

            $publicUrl = $this->construirUrlPublicaDownloads($filename);
            log_message(
                'debug',
                "Media descargada OK: media_id={$media_id} tipo={$tipo} conv={$id_conversacion} path={$filename} url={$publicUrl}",
            );

            return [
                'success' => true,
                'media_path' => $filename,
                'media_url' => $publicUrl,
                'media_type' => $tipo
            ];

        } catch (\Exception $e) {
            log_message('error', "Error procesando media: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function procesarReferralThumbnail(string $thumbnail_url, ?int $id_conversacion, ?string $message_id = null): array
    {
        try {
            $contenido = $this->descargarContenidoPublico($thumbnail_url);
            $media_id = $message_id ? 'referral_' . sha1($message_id) : uniqid('referral_', true);
            $filename = \generarNombreArchivoMedia($media_id, 'image', $id_conversacion, 'image/jpeg', 'downloads');
            $this->guardarEnDownloads($contenido, $filename);

            return [
                'success' => true,
                'media_path' => $filename,
                'media_url' => $this->construirUrlPublicaDownloads($filename),
                'media_type' => 'image'
            ];
        } catch (\Exception $e) {
            log_message('error', "Error procesando thumbnail referral: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Imagen de plantilla rápida en downloads/{fecha}/plantillas-rapidas/{idUsuario}/image/.
     *
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file
     */
    public function subirImagenPlantillaRapida($file, int $idUsuario): array
    {
        try {
            if (! $file || (method_exists($file, 'getError') && $file->getError() !== UPLOAD_ERR_OK)) {
                $detalle = method_exists($file, 'getErrorString') ? $file->getErrorString() : 'sin archivo';
                throw new \Exception("Error al cargar imagen: {$detalle}");
            }

            if (! $file->isValid()) {
                throw new \Exception('Imagen no válida.');
            }

            if (! function_exists('\detectarTipoMedia')) {
                \helper('file_helper');
            }

            $mimeType = strtolower((string) $file->getMimeType());
            $extension = strtolower((string) ($file->getClientExtension() ?: pathinfo($file->getClientName(), PATHINFO_EXTENSION)));
            if ($mimeType === 'application/octet-stream' && in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $mimeType = match ($extension) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    default => 'image/jpeg',
                };
            }
            $permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
            if (! in_array($mimeType, $permitidos, true)) {
                throw new \Exception('Formato no permitido. Use JPG, PNG, WEBP o GIF.');
            }

            $size = (int) $file->getSize();
            if ($size <= 0) {
                throw new \Exception('La imagen está vacía.');
            }
            if ($size > 10 * 1024 * 1024) {
                throw new \Exception('La imagen no puede superar 10 MB.');
            }

            $mediaId = uniqid('pr_', true);
            $carpetaAsesor = 'plantillas-rapidas/' . max(1, $idUsuario);
            $filename = \generarNombreArchivoMedia($mediaId, 'image', $carpetaAsesor, $mimeType, 'uploads');
            $this->guardarEnUploads($file->getTempName(), $filename);

            return [
                'success'    => true,
                'media_path' => $filename,
                'media_url'  => $this->construirUrlPublicaUploads($filename),
                'media_type' => 'image',
                'size'       => $size,
                'mime_type'  => $mimeType,
            ];
        } catch (\Exception $e) {
            log_message('error', 'MediaUseCase::subirImagenPlantillaRapida - ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function subirArchivo($file, $id_conversacion = null) 
    {
        try {
            if (!$file) {
                throw new \Exception("No se recibió el archivo");
            }

            // Error de upload reportado por PHP/CodeIgniter
            if (method_exists($file, 'getError') && $file->getError() !== UPLOAD_ERR_OK) {
                $detalle = method_exists($file, 'getErrorString') ? $file->getErrorString() : 'Error de carga desconocido';
                throw new \Exception("Error al cargar archivo: {$detalle}");
            }

            if (!$file->isValid()) {
                throw new \Exception("Archivo no válido");
            }

            if (!function_exists('\detectarTipoMedia')) {
                \helper('file_helper');
            }

            $mimeType = strtolower((string) $file->getMimeType());
            $mimeCliente = strtolower((string) (method_exists($file, 'getClientMimeType') ? $file->getClientMimeType() : ''));
            $nombreCliente = strtolower((string) $file->getClientName());
            $forzado = strtolower(trim((string) (service('request')->getPost('media_type') ?? '')));
            $size = (int) $file->getSize();

            // Chrome graba notas de voz en contenedor WebM y finfo lo marca como video/webm.
            if (
                in_array($mimeType, ['video/webm', 'application/octet-stream'], true)
                && (
                    $forzado === 'audio'
                    || str_starts_with($mimeCliente, 'audio/')
                    || str_contains($nombreCliente, 'nota-de-voz')
                )
            ) {
                $mimeType = 'audio/webm';
            }

            // Validaciones de seguridad/compatibilidad para móvil
            $mimePermitidos = [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/webp',
                'image/gif',
                'video/mp4',
                'video/quicktime',
                'video/3gpp',
                'video/webm',
                'audio/aac',
                'audio/mp4',
                'audio/mpeg',
                'audio/mp3',
                'audio/amr',
                'audio/ogg',
                'audio/opus',
                'audio/webm',
                'application/ogg',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
            ];
            if (! in_array($mimeType, $mimePermitidos, true)) {
                throw new \Exception("Ese archivo no se puede mandar por WhatsApp ({$mimeType}).");
            }

            if ($size <= 0) {
                throw new \Exception("El archivo está vacío o no se pudo leer su tamaño.");
            }

            $tipo = \detectarTipoMedia($mimeType);
            if ($forzado === 'sticker' && in_array($mimeType, ['image/webp', 'image/png'], true)) {
                $tipo = 'sticker';
            }
            if ($forzado === 'audio' && (str_starts_with($mimeType, 'audio/') || $mimeType === 'video/webm')) {
                $tipo = 'audio';
                if ($mimeType === 'video/webm') {
                    $mimeType = 'audio/webm';
                }
            }
            if ($forzado === 'video' && str_starts_with($mimeType, 'video/')) {
                $tipo = 'video';
            }
            $maxBytes = in_array($tipo, ['video', 'document'], true) ? (16 * 1024 * 1024) : (10 * 1024 * 1024);
            if ($size > $maxBytes) {
                throw new \Exception("El archivo excede el tamaño permitido (" . ($tipo === 'video' ? '16' : '10') . " MB).");
            }

            $media_id = uniqid('upload_', true);
            $filename = \generarNombreArchivoMedia($media_id, $tipo, $id_conversacion, $mimeType, 'uploads');
            $this->guardarEnUploads($file->getTempName(), $filename);
            $publicUrl = $this->construirUrlPublicaUploads($filename);

            return [
                'success' => true,
                'media_path' => $filename,
                'media_url' => $publicUrl,
                'media_type' => $tipo,
                'media_id' => $media_id,
                'original_name' => $file->getClientName(),
                'size' => $size
            ];

        } catch (\Exception $e) {
            log_message('error', "MediaUseCase::subirArchivo - Error: " . $e->getMessage());
            return [
                'success' => false, 
                'error' => $e->getMessage()
            ];
        }
    }

    private function guardarEnUploads(string $rutaTemporal, string $filename): void
    {
        $relative = ltrim($filename, '/');
        $rutaLocalDestino = WRITEPATH . 'uploads/' . preg_replace('#^uploads/#', '', $relative);
        $directorioDestino = dirname($rutaLocalDestino);

        if (!is_dir($directorioDestino) && !mkdir($directorioDestino, 0777, true) && !is_dir($directorioDestino)) {
            throw new \Exception("No se pudo crear directorio: {$directorioDestino}");
        }

        if (!@copy($rutaTemporal, $rutaLocalDestino)) {
            throw new \Exception("No se pudo guardar el archivo en uploads");
        }
    }

 
    public function guardarContenidoEnUploads(string $contenido, string $pathUnderUploads): array
    {
        $mediaPath = $this->normalizarPathUploads($pathUnderUploads);
        $rutaLocal = $this->rutaDiscoUploads($mediaPath);
        $directorio = dirname($rutaLocal);

        if (! is_dir($directorio) && ! mkdir($directorio, 0777, true) && ! is_dir($directorio)) {
            throw new \Exception("No se pudo crear directorio: {$directorio}");
        }

        if (@file_put_contents($rutaLocal, $contenido) === false) {
            throw new \Exception('No se pudo guardar el archivo en uploads');
        }

        return [
            'media_path' => $mediaPath,
            'media_url'  => $this->construirUrlPublicaUploads($mediaPath),
        ];
    }

    /**
     * Ruta absoluta en disco (writable/uploads/...).
     */
    public function rutaDiscoUploads(string $pathUnderUploads): string
    {
        $mediaPath = $this->normalizarPathUploads($pathUnderUploads);

        return WRITEPATH . 'uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $mediaPath);
    }

    /**
     * Obtiene media_path desde URL pública o ruta relativa guardada en BD.
     */
    public function rutaMediaDesdeAlmacenado(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            $path = parse_url($stored, PHP_URL_PATH);
            $stored = is_string($path) ? ltrim($path, '/') : '';
        }

        $stored = $this->normalizarPathUploads($stored);

        return $stored !== '' ? $stored : null;
    }

    public function construirUrlPublicaUploads(string $filename): string
    {
        $mediaPath = $this->normalizarPathUploads($filename);
        $baseUploadsUrl = trim((string) env('MEDIA_UPLOADS_BASE_URL', ''));
        if ($baseUploadsUrl !== '') {
            return rtrim($baseUploadsUrl, '/') . '/uploads/' . $mediaPath;
        }

        return rtrim($this->appConfig->baseURL, '/') . '/uploads/' . $mediaPath;
    }

    private function normalizarPathUploads(string $path): string
    {
        return ltrim(preg_replace('#^uploads/#', '', ltrim($path, '/')), '/');
    }

    private function guardarEnDownloads(string $contenido, string $filename): void
    {
        $relative = ltrim($filename, '/');
        $rutaLocalDestino = WRITEPATH . $relative;
        $directorioDestino = dirname($rutaLocalDestino);

        if (!is_dir($directorioDestino) && !mkdir($directorioDestino, 0777, true) && !is_dir($directorioDestino)) {
            throw new \Exception("No se pudo crear directorio: {$directorioDestino}");
        }

        if (@file_put_contents($rutaLocalDestino, $contenido) === false) {
            throw new \Exception("No se pudo guardar el archivo en downloads");
        }
    }

    private function construirUrlPublicaDownloads(string $filename): string
    {
        $baseDownloadsUrl = trim((string) env('MEDIA_DOWNLOADS_BASE_URL', ''));
        if ($baseDownloadsUrl !== '') {
            return rtrim($baseDownloadsUrl, '/') . '/downloads/' . ltrim(preg_replace('#^downloads/#', '', ltrim($filename, '/')), '/');
        }

        return rtrim($this->appConfig->baseURL, '/') . '/' . ltrim($filename, '/');
    }

    private function descargarContenidoPublico(string $url): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
        ]);

        $contenido = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || empty($contenido)) {
            throw new \Exception($error ?: "Error descargando archivo público. HTTP Code: $httpCode");
        }

        return $contenido;
    }
}