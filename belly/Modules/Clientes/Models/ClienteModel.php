<?php

namespace Belly\Clientes\Models;
use Belly\Clientes\Entities\ClienteEntity;

use CodeIgniter\Model;

class ClienteModel extends Model {

    protected $table = 'cliente';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ClienteEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'telefono',
        'psid',
        'wa_user_id',
        'wa_parent_user_id',
        'id',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
    ];
    protected array $castHandlers = [];
    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'telefono'          => 'permit_empty|max_length[15]|is_unique[cliente.telefono,id,{id}]',
        'psid'              => 'permit_empty|max_length[50]|is_unique[cliente.psid,id,{id}]',
        'wa_user_id'        => 'permit_empty|max_length[128]|is_unique[cliente.wa_user_id,id,{id}]',
        'wa_parent_user_id' => 'permit_empty|max_length[128]',
        'id'                => 'permit_empty|integer',
    ];
    protected $validationMessages = [
        'telefono'        => [
            'required'      => 'El campo teléfono es obligatorio.',
            'max_length'    => 'El campo teléfono debe tener exactamente 11 caracteres (51 + 9 dígitos).',
            'min_length'    => 'El campo teléfono debe tener exactamente 11 caracteres (51 + 9 dígitos).',
            'is_unique'     => 'El número de teléfono ya está registrado.',
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
