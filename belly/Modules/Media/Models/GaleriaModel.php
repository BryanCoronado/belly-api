<?php

namespace Belly\Media\Models;
use Belly\Media\Entities\GaleriaEntity;

use CodeIgniter\Model;

class GaleriaModel extends Model {

    protected $table = 'galeria';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = GaleriaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_grupo_galeria',
        'nombre',
        'tipo',
        'descripcion',
        'activo',
        'bloque',
        'campaign_id',
        'filename',
        'path',
        'url',
        'original_name',
        'mime_type',
        'size',
        'tags',
        'fecha_inicio',
        'fecha_fin',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_grupo_galeria' => 'int',
        'size' => 'int',
        'activo' => 'int',
        'fecha_inicio' => '?datetime',
        'fecha_fin' => '?datetime',
        'created_at' => 'datetime',
        'updated_at' => '?datetime',
        'deleted_at' => '?datetime',
    ];
    protected array $castHandlers = [];
    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    // Validation
    protected $validationRules = [
        'id_grupo_galeria' => 'permit_empty|integer',
        'nombre' => 'required|max_length[255]',
        'tipo' => 'required|max_length[50]',
        'descripcion' => 'required|max_length[50]',
        'activo' => 'required|integer|in_list[0,1]',
        'bloque' => 'permit_empty|in_list[primero,segundo,tercero]',
        'campaign_id' => 'permit_empty|max_length[64]',
        'filename' => 'required|max_length[255]',
        'path' => 'required|max_length[500]',
        'url' => 'required|max_length[1000]',
        'mime_type' => 'required|max_length[100]',
        'size' => 'required|integer|greater_than[0]',
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;
    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];
}

