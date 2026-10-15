<?php

namespace Belly\Flujos\Entities;

use CodeIgniter\Entity\Entity;

class FlujoSesionEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'reanudar_at'];
    protected $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'id_flujo' => 'int',
        'id_conversacion' => 'int',
        'intentos' => 'int',
        'variables' => 'json-array',
    ];
}
