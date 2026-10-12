<?php

namespace Belly\Configuracion\Entities;

use Belly\Services\Cifrado;
use CodeIgniter\Entity\Entity;

class ConexionMetaEntity extends Entity
{
    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'webhook_verificado_at', 'ultimo_evento_at'];
    protected $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
    ];

    public function setAppSecret(?string $valor): static
    {
        $this->attributes['app_secret'] = Cifrado::cifrar($valor);

        return $this;
    }

    public function getAppSecret(): ?string
    {
        return Cifrado::descifrar($this->attributes['app_secret'] ?? null);
    }

    public function tieneAppSecret(): bool
    {
        return ! empty($this->attributes['app_secret']);
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $datos = parent::toArray($onlyChanged, $cast, $recursive);
        unset($datos['app_secret'], $datos['verify_token'], $datos['webhook_clave']);

        return $datos;
    }
}
