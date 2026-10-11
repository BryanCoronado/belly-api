<?php

namespace Belly\Usuarios\Models;
use Belly\Usuarios\Entities\UsuarioPerfilEntity;

use CodeIgniter\Model;

class UsuarioPerfilModel extends Model {

    protected $table = 'usuario_perfil';
    protected $primaryKey = 'id_usuario';
    protected $useAutoIncrement = false;
    protected $returnType = UsuarioPerfilEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_usuario',
        'nombres',
        'apellidos',
        'id_tipo_documento',
        'nro_documento',
        'correo',
        'celular',
        'celular_secundario',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id_usuario' => 'int',
        'id_tipo_documento' => 'int',
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
