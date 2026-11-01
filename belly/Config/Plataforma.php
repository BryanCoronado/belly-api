<?php

namespace Belly\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Se puede sobrescribir en .env con:
 *   plataforma.idEmpresaOperadora = 4
 *   plataforma.urlPublicaApi = https://api.tudominio.com
 *   plataforma.urlPublicaTienda = https://app.tudominio.com
 */
class Plataforma extends BaseConfig
{
    public int $idEmpresaOperadora = 0;

    /** URL HTTPS pública del API: Meta la usa para llamar al webhook. Vacío = base_url(). */
    public string $urlPublicaApi = '';

    public function idOperadora(): int
    {
        $valor = env('plataforma.idEmpresaOperadora');
        if ($valor !== null && $valor !== '') {
            return (int) $valor;
        }

        return $this->idEmpresaOperadora;
    }

    public function urlApi(): string
    {
        $url = trim($this->urlPublicaApi) !== '' ? $this->urlPublicaApi : base_url();

        return rtrim($url, '/');
    }

    public function urlTienda(): string
    {
        $valor = env('plataforma.urlPublicaTienda');
        $url = is_string($valor) ? trim($valor) : '';

        return rtrim($url, '/');
    }
}
