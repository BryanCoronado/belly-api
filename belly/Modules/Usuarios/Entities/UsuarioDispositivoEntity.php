<?php

namespace Belly\Usuarios\Entities;

use CodeIgniter\Entity\Entity;

class UsuarioDispositivoEntity extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'visto_en'];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'id_empresa' => 'integer',
        'activo' => 'integer',
        'id_conversacion_abierta' => '?integer',
    ];
}
