<?php

namespace Belly\Usuarios\Entities;

use CodeIgniter\Entity\Entity;


class GrupoUsuarioPermisoRolEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
