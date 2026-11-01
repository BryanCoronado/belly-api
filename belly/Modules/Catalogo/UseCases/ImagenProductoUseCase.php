<?php

namespace Belly\Catalogo\UseCases;

use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

/** Sube la foto de un producto, banner o logo del catálogo. */
class ImagenProductoUseCase
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MIMES = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
    ];
    private const EXTENSIONES = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];

    /** @return array{url:string} */
    public function subir(?UploadedFile $archivo): array
    {
        if ($archivo === null) {
            throw new DomainException('No se pudo recibir la imagen. Inténtalo de nuevo.');
        }
        if (method_exists($archivo, 'getError') && $archivo->getError() !== UPLOAD_ERR_OK) {
            throw new DomainException($this->errorCarga((int) $archivo->getError()));
        }
        if (! $archivo->isValid() || $archivo->hasMoved()) {
            throw new DomainException('No se pudo recibir la imagen. Inténtalo de nuevo.');
        }

        $tamano = (int) $archivo->getSize();
        if ($tamano <= 0) {
            throw new DomainException('La imagen está vacía.');
        }
        if ($tamano > self::MAX_BYTES) {
            throw new DomainException('La imagen es muy pesada. Se permite hasta 5 MB.');
        }

        $extension = $this->extension($archivo);
        if ($extension === null) {
            throw new DomainException('Usa una imagen JPG, PNG o WEBP.');
        }

        helper('vip2system');
        $idEmpresa = id_empresa_sesion();
        if ($idEmpresa === null) {
            throw new DomainException('No se pudo identificar tu empresa.');
        }

        $ruta = 'productos/' . $idEmpresa . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
        $guardado = media()->guardarContenidoEnUploads((string) file_get_contents($archivo->getTempName()), $ruta);

        return ['url' => $guardado['media_url']];
    }

    private function extension(UploadedFile $archivo): ?string
    {
        $mimes = [
            strtolower((string) $archivo->getMimeType()),
            strtolower((string) $archivo->getClientMimeType()),
        ];
        foreach ($mimes as $mime) {
            if (isset(self::MIMES[$mime])) {
                return self::MIMES[$mime];
            }
        }

        $ext = strtolower((string) ($archivo->getClientExtension() ?: pathinfo($archivo->getClientName(), PATHINFO_EXTENSION)));

        return self::EXTENSIONES[$ext] ?? null;
    }

    private function errorCarga(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen es muy pesada. Se permite hasta 5 MB.',
            UPLOAD_ERR_PARTIAL => 'La imagen no terminó de subir. Inténtalo de nuevo.',
            UPLOAD_ERR_NO_FILE => 'No se pudo recibir la imagen. Inténtalo de nuevo.',
            default => 'No se pudo subir la imagen. Prueba con un JPG, PNG o WEBP más liviano.',
        };
    }
}
