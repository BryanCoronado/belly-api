<?php

namespace Belly\Modulos\Models;

use Belly\Modulos\Entities\ModuloEntity;

use CodeIgniter\Model;

class ModuloModel extends Model
{
    protected $table = 'modulo';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ModuloEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_modulo_grupo',
        'id_permiso',
        'parent_id',
        'nombre',
        'alias',
        'descripcion',
        'activo',
        'orden',
        'url',
        'icon'
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_permiso' => '?int',
        'id_modulo_grupo' => '?int',
        'parent_id' => '?int',
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
    protected $validationRules = [];
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
