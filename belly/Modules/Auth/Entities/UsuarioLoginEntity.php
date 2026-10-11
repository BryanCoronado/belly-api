<?php

namespace Belly\Auth\Entities;
use CodeIgniter\Entity\Entity;


class UsuarioLoginEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
