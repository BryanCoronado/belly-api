<?php

namespace Belly\Catalogo\Entities;

use CodeIgniter\Entity\Entity;

class PedidoCatalogoEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at'];
    protected $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'id_cliente' => '?int',
        'id_conversacion' => '?int',
        'total' => '?float',
        'items' => 'json-array',
    ];
}
