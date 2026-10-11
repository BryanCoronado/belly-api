<?php

namespace Belly\Clientes\Entities;
use CodeIgniter\Entity\Entity;


class ListaNegraEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
