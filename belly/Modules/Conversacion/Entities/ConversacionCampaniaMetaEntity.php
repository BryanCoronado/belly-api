<?php

namespace Belly\Conversacion\Entities;

use CodeIgniter\Entity\Entity;

class ConversacionCampaniaMetaEntity extends Entity
{
    protected $datamap = [];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id' => 'int',
        'id_conversacion' => 'int',
        'id_app' => 'int',
    ];
}
