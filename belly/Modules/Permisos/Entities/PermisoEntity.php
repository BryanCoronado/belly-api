<?php

namespace Belly\Permisos\Entities;
use CodeIgniter\Entity\Entity;


class PermisoEntity extends Entity {

    protected $datamap =[];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
