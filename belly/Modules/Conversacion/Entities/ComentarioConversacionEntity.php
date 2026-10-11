<?php

namespace Belly\Conversacion\Entities;
use CodeIgniter\Entity\Entity;


class ComentarioConversacionEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}

