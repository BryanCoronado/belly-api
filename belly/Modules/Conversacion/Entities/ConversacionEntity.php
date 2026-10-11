<?php

namespace Belly\Conversacion\Entities;
use CodeIgniter\Entity\Entity;


class ConversacionEntity extends Entity {

    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [];
}
