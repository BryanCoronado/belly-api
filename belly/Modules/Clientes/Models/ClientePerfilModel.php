<?php

namespace Belly\Clientes\Models;
use Belly\Clientes\Entities\ClientePerfilEntity;

use CodeIgniter\Model;

class ClientePerfilModel extends Model {

    protected $table = 'cliente_perfil';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ClientePerfilEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_cliente',
        'nombre',
        'apellido',
        'nombre_completo',
        'marca',
        'modelo',
        'placa',
        'correo',
        'distrito',
        'tipo',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [
        'id' => 'int',
        'id_cliente' => 'int',
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
        'id_cliente'     => 'required|integer',
        'nombre'     => 'required|max_length[100]',
        'apellido'     => 'max_length[100]',
        'marca'     => 'max_length[100]',
        'modelo'     => 'max_length[100]',
        'placa'     => 'max_length[20]',
        'correo'     => 'max_length[150]',
        'distrito'     => 'max_length[100]',
        'tipo' => 'max_length[100]',
    ];
    protected $validationMessages = [
        'id_cliente'        => [
            'required'      => 'El campo ID de cliente es obligatorio.',
            'integer'       => 'El campo ID de cliente debe ser un número entero.',
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
