<?php
namespace Belly\Clientes\UseCases;

use Belly\Clientes\Config\ClientesConfig;
use Belly\Clientes\Models\ListaNegraModel;
use Belly\Clientes\Entities\ListaNegraEntity;

class ListaNegraUseCase {
    protected ClientesConfig $config;
    protected ?ListaNegraModel $lista_negra_provider = NULL;

    public function __construct(ClientesConfig $config)
    {
        $this->config = $config;
    }

    public function getListaNegraProvider(): ListaNegraModel
    {
        if ($this->lista_negra_provider !== null) {
            return $this->lista_negra_provider;
        }

        $class_name = $this->config->lista_negra_provider;
        $this->lista_negra_provider = new $class_name();
        return $this->lista_negra_provider;
    }

    public function crear(array $datos)
    {
        $lista_negra_model = $this->getListaNegraProvider();

        $existente = $lista_negra_model->where('telefono', $datos['telefono'])->first();
        if ($existente) {
            throw new \Exception('El número de teléfono ya está en la lista negra');
        }

        $entity = new ListaNegraEntity();
        $entity->id_empresa = $this->idEmpresa();
        $entity->telefono = $datos['telefono'];
        $entity->motivo = $datos['motivo'] ?? null;

        if ($lista_negra_model->save($entity)) {
            $entity->id = $lista_negra_model->insertID;
            return [
                'success' => true,
                'message' => 'Número agregado a lista negra correctamente',
                'data' => $entity
            ];
        } else {
            throw new \Exception(json_encode($lista_negra_model->errors()));
        }
    }

    public function listar($page = 1, $perPage = 10, $search = null)
    {
        $lista_negra_model = $this->getListaNegraProvider();
        
        $lista_negra_model->orderBy('id', 'DESC');
        
        if (!empty($search)) {
            $lista_negra_model->groupStart();
            $lista_negra_model->like('telefono', $search);
            $lista_negra_model->orLike('motivo', $search);
            $lista_negra_model->groupEnd();
        }

        if ($perPage == 'all') {
            $registros = $lista_negra_model->findAll();
            $total = count($registros);
            return [
                'data' => $registros,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'perPage' => $total,
                    'lastPage' => 1,
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ],
            ];
        }

        $result = $lista_negra_model->paginate($perPage, 'default', $page);
        $pager = $lista_negra_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPage = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = ($total > 0) ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = ($from + count($result)) - 1;

        return [
            'data' => $result,
            'pagination' => [
                'total' => $total,
                'currentPage' => $currentPage,
                'perPage' => $perPage,
                'lastPage' => $lastPage,
                'from' => $from,
                'to' => $to,
                'hasNext' => $currentPage < $lastPage,
                'hasPrev' => $currentPage > 1,
            ],
        ];
    }

    public function getById(int $id)
    {
        $lista_negra_model = $this->getListaNegraProvider();
        $registro = $lista_negra_model->find($id);
        
        if (!$registro) {
            throw new \Exception('Registro no encontrado');
        }
        
        return $registro;
    }

    public function actualizar(int $id, array $datos)
    {
        $lista_negra_model = $this->getListaNegraProvider();
        
        $existente = $lista_negra_model->find($id);
        if (!$existente) {
            throw new \Exception('Registro no encontrado');
        }

        // Actualizar datos
        $entity = new ListaNegraEntity();
        $entity->id = $id;
        
        if (isset($datos['telefono'])) {
            $otro = $lista_negra_model->where('telefono', $datos['telefono'])->where('id !=', $id)->first();
            if ($otro) {
                throw new \Exception('El número de teléfono ya está en la lista negra');
            }
            $entity->telefono = $datos['telefono'];
        }
        if (isset($datos['motivo'])) {
            $entity->motivo = $datos['motivo'];
        }

        if ($lista_negra_model->save($entity)) {
            return [
                'success' => true,
                'message' => 'Registro actualizado correctamente',
                'data' => $this->getById($id)
            ];
        } else {
            throw new \Exception(json_encode($lista_negra_model->errors()));
        }
    }

    public function eliminar(int $id)
    {
        $lista_negra_model = $this->getListaNegraProvider();
        
        $existente = $lista_negra_model->find($id);
        if (!$existente) {
            throw new \Exception('Registro no encontrado');
        }

        // Soft delete
        if ($lista_negra_model->delete($id)) {
            return [
                'success' => true,
                'message' => 'Registro eliminado correctamente',
                'id' => $id
            ];
        } else {
            throw new \Exception('Error al eliminar el registro');
        }
    }

    public function eliminarMultiples(array $ids)
    {
        $lista_negra_model = $this->getListaNegraProvider();
        
        if (empty($ids)) {
            throw new \Exception('Debe proporcionar al menos un ID');
        }

        // Validar que todos los IDs existan
        $ids_validos = [];
        $ids_no_encontrados = [];
        
        foreach ($ids as $id) {
            $existente = $lista_negra_model->find($id);
            if ($existente) {
                $ids_validos[] = $id;
            } else {
                $ids_no_encontrados[] = $id;
            }
        }
        
        if (empty($ids_validos)) {
            throw new \Exception('Ninguno de los registros fue encontrado');
        }

        // Soft delete: eliminar todos los válidos
        $eliminados = [];
        foreach ($ids_validos as $id) {
            if ($lista_negra_model->delete($id)) {
                $eliminados[] = $id;
            }
        }
        
        return [
            'success' => true,
            'total_solicitados' => count($ids),
            'total_eliminados' => count($eliminados),
            'ids_eliminados' => $eliminados,
            'ids_no_encontrados' => $ids_no_encontrados,
            'message' => count($eliminados) . ' registro(s) eliminado(s) correctamente'
        ];
    }

    public function verificarTelefono(string $telefono): bool
    {
        $lista_negra_model = $this->getListaNegraProvider();
        $registro = $lista_negra_model->where('telefono', $telefono)->first();
        return $registro !== null;
    }

    private function idEmpresa(): int
    {
        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new \Exception('La empresa de la sesión es obligatoria.');
        }

        return $id;
    }

}