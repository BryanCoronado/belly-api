<?php

namespace Belly\Modulos\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class ModuloController extends BaseController {

    use ResponseTrait;

    public function crear() {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $datos = $this->request->getJSON(true);
            $modulos = modulo()->crearModulo($datos);

            $db->transComplete();
            return $this->response->setJSON([
                        'success' => true,
                        'data' => $modulos
            ]);
        } catch (\Exception $e) {
            // $db->transComplete();   
            return $this->response->setJSON([
                        'success' => false,
                        'data' => $e->getMessage()
            ]);
        }
    }

    public function listar() {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $modulos = modulo()->listarModulos($page, $perPage);
        return $this->response->setJSON([
                    'success' => true,
                    'data' => $modulos['data'] ?? [],
                    'pagination' => $modulos['pagination'] ?? null,
        ]);
    }

    public function editar(int $id) {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $modulos = modulo()->editarModulo($id, $datos);

            $db->transComplete();
            return $this->response->setJSON([
                        'success' => true,
                        'data' => $modulos
            ]);
        } catch (\Exception $e) {
            // $db->transComplete();
            return $this->response->setJSON([
                        'success' => false,
                        'data' => $e->getMessage()
            ]);
        }
    }

    public function ver(int $id){
        try {
            $modulo = modulo()->obtenerModulo($id);
            return $this->response->setJSON([
                'success' => true,
                'data' => $modulo
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function eliminar(int $id) {
        try {
            $grupo = modulo()->borrarModulo($id);

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

    public function menu(){
        $menu = modulo()->getMenu();
        return $this->response->setJSON([
            'success' => true,
            'data' => $menu
        ]);
    }
}
