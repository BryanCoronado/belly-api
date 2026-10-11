<?php

namespace Belly\Mensajes\Entities;
use CodeIgniter\Entity\Entity;


class PlantillaRapidaEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
