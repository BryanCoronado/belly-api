<?php

namespace Belly\Catalogo\UseCases;

use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

/** Sube la foto de un producto. */
class ImagenProductoUseCase
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @return array{url:string} */
    public function subir(?UploadedFile $archivo): array
    {
        if ($archivo === null || ! $archivo->isValid() || $archivo->hasMoved()) {
            throw new DomainException('No se pudo recibir la imagen. Inténtalo de nuevo.');
        }

        $tamano = (int) $archivo->getSize();
        if ($tamano <= 0) {
            throw new DomainException('La imagen está vacía.');
        }
        if ($tamano > self::MAX_BYTES) {
            throw new DomainException('La imagen es muy pesada. Se permite hasta 5 MB.');
        }

        $extension = self::MIMES[strtolower((string) $archivo->getMimeType())] ?? null;
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
}
