<?php

namespace Belly\Catalogo\Entities;

use CodeIgniter\Entity\Entity;

class ProductoEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'id_familia' => '?int',
        'precio' => '?float',
        'precio_oferta' => '?float',
        'destacado' => 'bool',
        'visible_catalogo' => 'bool',
        'orden' => 'int',
        'activo' => 'bool',
        'detalles' => 'json-array',
        'imagenes' => 'json-array',
        'variantes' => 'json-array',
    ];
}
