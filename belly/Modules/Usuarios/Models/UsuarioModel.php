<?php

namespace Belly\Usuarios\Models;
use Belly\Usuarios\Entities\UsuarioEntity;

use CodeIgniter\Model;

class UsuarioModel extends Model
{

    protected $table = 'usuario';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = UsuarioEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'usuario',
        'password',
        'codigo_recuperacion',
        'fecha_recuperacion',
        'login_fallidos',
        'cuenta_bloqueada',
        'fecha_bloqueo',
        'activo',
        'es_bot',
        'id_estado_usuario',
        'sudo',
        'forzar_cambio_pw',       
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'fecha_recuperacion' => '?datetime',
        'login_fallidos' => 'int',
        'id_estado_usuario' => 'int',
        'cuenta_bloqueada' => 'int',
        'fecha_bloqueo' => '?datetime',
        'activo' => 'int',
        'es_bot' => 'int',
        'sudo' => 'int',
        'forzar_cambio_pw' => 'int',
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
        'usuario'     => 'required|max_length[150]',
        'password'     => 'required',
    ];
    protected $validationMessages = [
        'usuario' => [
            'required' => 'El usuario es requerido.',
            'max_length' => 'Se permiten maximo 150 carácteres.',
        ],
        'password' => [
            'required' => 'El usuario es requerido.'
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
