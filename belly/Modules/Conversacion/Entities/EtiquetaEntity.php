<?php

namespace Belly\Conversacion\Entities;
use CodeIgniter\Entity\Entity;


class EtiquetaEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
