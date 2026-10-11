<?php

namespace Belly\Permisos\Entities;
use CodeIgniter\Entity\Entity;


class GrupoUsuarioPermisoEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
