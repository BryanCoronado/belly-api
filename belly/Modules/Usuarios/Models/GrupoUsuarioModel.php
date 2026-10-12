<?php

namespace Belly\Usuarios\Models;

use CodeIgniter\Model;
use Belly\Configuracion\Models\FiltraPorEmpresa;
use Belly\Usuarios\Entities\GrupoUsuarioEntity;

class GrupoUsuarioModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'grupo_usuario';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = GrupoUsuarioEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa',
        'grupo',
        'descripcion'
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
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
        'grupo'       => 'required|max_length[45]',
        'descripcion'  => 'permit_empty|max_length[150]',
    ];
    protected $validationMessages = [
        'grupo' => [
            'required' => 'El grupo es requerido.',
            'max_length' => 'Se permiten maximo 45 carácteres.',
        ],
        'descripcion' => [
            'max_length' => 'Se permiten maximo 150 carácteres.',
        ],
    ];
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