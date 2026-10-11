<?php

namespace Belly\Media\Entities;
use CodeIgniter\Entity\Entity;


class ProductoPrecioEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}