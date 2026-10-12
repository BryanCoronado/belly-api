<?php

namespace Belly\Configuracion\Models;
use Belly\Configuracion\Entities\AppEntity;

use CodeIgniter\Model;

class AppModel extends Model {
    use FiltraPorEmpresa;

    protected $table = 'app';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = AppEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa',
        'id_portafolio',
        'nombre',
        'telefono',
        'phone_number_id',
        'canal',
        'whatsapp_business_account_id',
        'access_token',
        'verificado_at',
        'modo',
        'tipo',
        'max_usuarios',
        'estado',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_empresa' => 'int',
        'id_portafolio' => 'int',
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
        'nombre' => 'required|max_length[100]',
        'telefono' => 'permit_empty|max_length[20]',
        'modo' => 'required|in_list[individual,compartido]',
        'max_usuarios' => 'permit_empty|integer|greater_than[0]',
        'id_portafolio' => 'permit_empty|integer',
        'phone_number_id' => 'permit_empty|max_length[255]',
        'canal' => 'permit_empty|in_list[whatsapp]',
        'whatsapp_business_account_id' => 'permit_empty|max_length[255]',
        'access_token' => 'permit_empty',
        'estado' => 'permit_empty|in_list[0,1]',
    ];
    
    protected $validationMessages = [
        'nombre' => [
            'required' => 'El nombre de la app es obligatorio.',
            'max_length' => 'El nombre no debe exceder los 100 caracteres.',
        ],
        'telefono' => [
            'max_length' => 'El teléfono no debe exceder los 20 caracteres.',
        ],
        'modo' => [
            'required' => 'El modo es obligatorio.',
            'in_list' => 'El modo debe ser "individual" o "compartido".',
        ],
        'max_usuarios' => [
            'integer' => 'Max usuarios debe ser un número entero.',
            'greater_than' => 'Max usuarios debe ser mayor a 0.',
        ],
        'estado' => [
            'in_list' => 'El estado debe ser 0 (inactivo) o 1 (activo).',
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
