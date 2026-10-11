<?php

namespace Belly\Media\Models;

use CodeIgniter\Model;

class GaleriaCampaniaModel extends Model
{
    protected $table = 'galeria_campania';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_galeria',
        'campaign_id',
        'nombre_campania',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
