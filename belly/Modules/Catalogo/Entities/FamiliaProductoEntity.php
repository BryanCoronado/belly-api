<?php

namespace Belly\Catalogo\Entities;

use CodeIgniter\Entity\Entity;

class FamiliaProductoEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'orden' => 'int',
        'activo' => 'bool',
    ];
}
