<?php

namespace Belly\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Se puede sobrescribir en .env con:
 *   plataforma.idEmpresaOperadora = 1
 *   plataforma.urlPublicaApi = https://api.tudominio.com
 */
class Plataforma extends BaseConfig
{
    public int $idEmpresaOperadora = 1;

    /** URL HTTPS pública del API: Meta la usa para llamar al webhook. Vacío = base_url(). */
    public string $urlPublicaApi = '';

    public function urlApi(): string
    {
        $url = trim($this->urlPublicaApi) !== '' ? $this->urlPublicaApi : base_url();

        return rtrim($url, '/');
    }
}
