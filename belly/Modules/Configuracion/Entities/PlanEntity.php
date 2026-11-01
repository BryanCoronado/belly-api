<?php

namespace Belly\Configuracion\Entities;

use CodeIgniter\Entity\Entity;

class PlanEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id' => 'int',
        'precio_mensual' => 'float',
        'max_conversaciones' => 'int',
        'max_equipos' => 'int',
        'max_personas_equipo' => 'int',
        'max_personas' => 'int',
        'max_flujos' => 'int',
        'max_envios' => 'int',
        'max_numeros' => 'int',
        'incluye_agente_ia' => 'int',
        'activo' => 'int',
        'orden' => 'int',
    ];
}
