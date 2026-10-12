<?php

namespace Belly\Configuracion\Entities;

use Belly\Services\Cifrado;
use CodeIgniter\Entity\Entity;

/**
 * El access_token se guarda cifrado y se lee descifrado de forma transparente.
 * Nunca sale en las respuestas: toArray/jsonSerialize lo ocultan.
 */
class AppEntity extends Entity
{
    private const OCULTOS = ['access_token'];

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'verificado_at'];
    protected $casts = [];

    public function setAccessToken(?string $valor): static
    {
        $this->attributes['access_token'] = Cifrado::cifrar($valor);

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return Cifrado::descifrar($this->attributes['access_token'] ?? null);
    }

    public function tieneToken(): bool
    {
        return ! empty($this->attributes['access_token']);
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $datos = parent::toArray($onlyChanged, $cast, $recursive);
        foreach (self::OCULTOS as $campo) {
            unset($datos[$campo]);
        }
        $datos['tiene_token'] = $this->tieneToken();

        return $datos;
    }
}
