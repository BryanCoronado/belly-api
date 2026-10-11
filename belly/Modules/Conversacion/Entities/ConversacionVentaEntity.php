<?php

namespace Belly\Conversacion\Entities;

use CodeIgniter\Entity\Entity;

class ConversacionVentaEntity extends Entity
{
    protected $datamap = [];
    protected $dates = ['registrada_at', 'created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id' => 'int',
        'id_conversacion' => 'int',
        'id_cliente' => 'int',
        'id_usuario' => 'int',
        'id_usuario_registro' => '?int',
    ];
}
