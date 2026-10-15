<?php

namespace Belly\Flujos\Entities;

use CodeIgniter\Entity\Entity;

class FlujoEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'activo' => 'bool',
        'definicion' => 'json-array',
    ];
}
