<?php

namespace Belly\Configuracion\Entities;

use CodeIgniter\Entity\Entity;

class EmpresaEntity extends Entity
{
    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id' => 'int',
        'estado' => 'int',
    ];
}
