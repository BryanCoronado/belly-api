<?php

namespace Belly\Usuarios\Models;

use Belly\Usuarios\Entities\UsuarioDispositivoEntity;
use CodeIgniter\Model;

class UsuarioDispositivoModel extends Model
{
    protected $table = 'usuario_dispositivo';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = UsuarioDispositivoEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_usuario',
        'id_empresa',
        'expo_push_token',
        'plataforma',
        'nombre',
        'activo',
        'id_conversacion_abierta',
        'visto_en',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
