<?php

namespace Belly\Flujos\UseCases;

use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

/** Sube las imágenes, videos, audios y documentos que el bot enviará dentro de un flujo. */
class ArchivoFlujoUseCase
{
    private const MB = 1024 * 1024;

    /** Por tipo de archivo: mimes aceptados (según el contenido real), extensiones y tamaño máximo. */
    private const REGLAS = [
        'imagen' => [
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png'],
            'max' => 5 * self::MB,
            'permitido' => 'JPG o PNG de hasta 5 MB',
        ],
        'video' => [
            'mimes' => ['video/mp4' => 'mp4', 'video/3gpp' => '3gp'],
            'max' => 16 * self::MB,
            'permitido' => 'MP4 o 3GP de hasta 16 MB',
        ],
        'audio' => [
            'mimes' => ['audio/aac' => 'aac', 'audio/mp4' => 'm4a', 'audio/mpeg' => 'mp3', 'audio/amr' => 'amr', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg'],
            'max' => 16 * self::MB,
            'permitido' => 'MP3, M4A, AAC, AMR u OGG de hasta 16 MB',
        ],
        'documento' => [
            'mimes' => [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'text/plain' => 'txt',
            ],
            'max' => 20 * self::MB,
            'permitido' => 'PDF, Word, Excel, PowerPoint o TXT de hasta 20 MB',
        ],
    ];

    /** @return array{url:string, nombre:string, tipo:string, tamano:int} */
    public function subir(?UploadedFile $archivo, string $tipo): array
    {
        $reglas = self::REGLAS[$tipo] ?? null;
        if ($reglas === null) {
            throw new DomainException('Elige qué tipo de archivo vas a subir.');
        }
        if ($archivo === null || ! $archivo->isValid() || $archivo->hasMoved()) {
            throw new DomainException('No se pudo recibir el archivo. Inténtalo de nuevo.');
        }

        $tamano = (int) $archivo->getSize();
        if ($tamano <= 0) {
            throw new DomainException('El archivo está vacío.');
        }
        if ($tamano > $reglas['max']) {
            throw new DomainException('El archivo es muy pesado. Se permite ' . $reglas['permitido'] . '.');
        }

        $extension = $this->extension($archivo, $reglas['mimes']);
        if ($extension === null) {
            throw new DomainException('Ese formato no es compatible con WhatsApp. Se permite ' . $reglas['permitido'] . '.');
        }

        helper('vip2system');
        $idEmpresa = id_empresa_sesion();
        if ($idEmpresa === null) {
            throw new DomainException('No se pudo identificar tu empresa.');
        }

        $ruta = 'flujos/' . $idEmpresa . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
        $guardado = media()->guardarContenidoEnUploads((string) file_get_contents($archivo->getTempName()), $ruta);

        return [
            'url' => $guardado['media_url'],
            'nombre' => $this->nombreSeguro($archivo->getClientName(), $extension),
            'tipo' => $tipo,
            'tamano' => $tamano,
        ];
    }

    /**
     * El formato se decide por el contenido real del archivo, no por el nombre que trae.
     *
     * @param array<string, string> $mimes
     */
    private function extension(UploadedFile $archivo, array $mimes): ?string
    {
        $mime = strtolower((string) $archivo->getMimeType());
        if (isset($mimes[$mime])) {
            return $mimes[$mime];
        }

        // Los documentos de Office modernos a veces se detectan como un zip genérico; ahí se mira la extensión.
        $declarada = strtolower($archivo->getClientExtension());
        $esOffice = in_array($mime, ['application/zip', 'application/octet-stream', 'application/x-ole-storage', 'application/cdfv2'], true);
        if ($esOffice && in_array($declarada, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true) && in_array($declarada, $mimes, true)) {
            return $declarada;
        }

        return null;
    }

    private function nombreSeguro(string $nombre, string $extension): string
    {
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', $base));
        $base = mb_substr($base !== '' ? $base : 'archivo', 0, 60);

        return $base . '.' . $extension;
    }
}
