<?php

namespace Belly\Catalogo\Entities;

use CodeIgniter\Entity\Entity;

class VitrinaEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'mostrar_precios' => 'bool',
        'publicado' => 'bool',
        'pagina' => 'json-array',
    ];
}
