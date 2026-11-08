<?php

namespace Belly\Catalogo\Models;

use Belly\Catalogo\Entities\PedidoCatalogoEntity;
use Belly\Configuracion\Models\FiltraPorEmpresa;
use CodeIgniter\Model;

class PedidoCatalogoModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'pedido_catalogo';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = PedidoCatalogoEntity::class;
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa', 'id_cliente', 'id_conversacion', 'tienda', 'cliente_nombre', 'nota',
        'items', 'total', 'moneda', 'estado', 'texto',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
