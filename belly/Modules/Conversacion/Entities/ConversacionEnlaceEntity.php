<?php

namespace Belly\Conversacion\Entities;

use CodeIgniter\Entity\Entity;

class ConversacionEnlaceEntity extends Entity
{
    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'ultimo_aviso_origen_at'];
    protected $casts = [
        'id' => 'integer',
        'id_conversacion_origen' => 'integer',
        'id_conversacion_destino' => 'integer',
        'id_cliente_origen' => 'integer',
        'id_cliente_destino' => 'integer',
    ];
}
