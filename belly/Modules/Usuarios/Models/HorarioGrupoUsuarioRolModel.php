<?php

namespace Belly\Usuarios\Models;

use Belly\Usuarios\Entities\HorarioGrupoUsuarioRolEntity;
use CodeIgniter\Model;

class HorarioGrupoUsuarioRolModel extends Model
{
    protected $table = 'horario_grupo_usuario_rol';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = HorarioGrupoUsuarioRolEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'id_grupo_usuario_rol',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'activo',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'                  => 'int',
        'id_grupo_usuario_rol' => 'int',
        'activo'              => 'bool',
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
        'id_grupo_usuario_rol' => 'required|integer|is_not_unique[grupo_usuario_rol.id]',
        'dia_semana'            => 'required|in_list[lunes,martes,miercoles,jueves,viernes,sabado,domingo]',
        'hora_inicio'           => 'required|regex_match[/^\d{1,2}:\d{2}(:\d{2})?$/]',
        'hora_fin'              => 'required|regex_match[/^\d{1,2}:\d{2}(:\d{2})?$/]',
        'activo'                => 'permit_empty|integer|in_list[0,1]',
    ];

    protected $validationMessages = [
        'id_grupo_usuario_rol' => [
            'required'      => 'El rol es requerido.',
            'is_not_unique' => 'El rol no existe.',
        ],
        'dia_semana' => [
            'required' => 'El día de la semana es requerido.',
            'in_list'  => 'El día debe ser válido.',
        ],
        'hora_inicio' => [
            'required'     => 'La hora de inicio es requerida.',
            'regex_match'  => 'La hora debe tener formato HH:mm:ss.',
        ],
        'hora_fin' => [
            'required'     => 'La hora de fin es requerida.',
            'regex_match'  => 'La hora debe tener formato HH:mm:ss.',
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