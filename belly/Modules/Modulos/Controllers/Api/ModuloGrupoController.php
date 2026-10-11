<?php

namespace Belly\Modulos\Controllers\Api;

use App\Controllers\BaseController;

class ModuloGrupoController extends BaseController
{
    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo = modulo_grupo()->crearGrupo($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo
            ]);
        } catch (\Exception $e) {
            // $db->transComplete();
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function listar()
    {
        $grupo = modulo_grupo()->listarGrupos();
        
        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo
        ]);
    }
    
    public function editar(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);
            
            $grupo = modulo_grupo()->editarGrupo($id, $datos);
            
            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo
            ]);
        } catch (\Exception $e) {
            $db->transComplete();
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $grupo = modulo_grupo()->borrarGrupo($id);

            return $this->response->setJSON([
                'success' => true,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }
}
