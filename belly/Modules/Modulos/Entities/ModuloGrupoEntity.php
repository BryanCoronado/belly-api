<?php

namespace Belly\Modulos\Entities;

use CodeIgniter\Entity\Entity;

class ModuloGrupoEntity extends Entity
{

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];    
}
