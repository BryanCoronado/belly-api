<?php

namespace Belly\Conversacion\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class CitaConversacionControllerApi extends BaseController
{
    use ResponseTrait;

    public function listar()
    {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        try {
            $result = cita_conversacion()->listarCitasConversacion($page, $perPage, $search);
            return $this->respond([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }



    public function crear()
    {
        try {
            $data = $this->request->getJSON(true) ?? $this->request->getPost();
            if (!isset($data['id_conversacion'])) {
                return $this->respond([
                    'success' => false,
                    'message' => 'El campo id_conversacion es obligatorio.'
                ], 400);
            }

            $cita = cita_conversacion()->crearCitaConversacion($data);

            return $this->respond([
                'success' => true,
                'data' => $cita
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }


    public function ver(int $id)
    {
        try {
            $cliente = cita_conversacion()->getById($id);
            return $this->response->setJSON([
                'success' => true,
                'data' => $cliente
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function actualizarAsistenciaCita()
    {
        try {
            $authHeader = $this->request->getHeaderLine('Authorization');
            if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                return $this->response->setJSON(['success' => false, 'error' => 'Token de autorización requerido.'])->setStatusCode(401);
            }
            $token = $matches[1];
            $tokenValido = '8ghskTxsjj0PjwpQ0Ofiw4HruGJMe8oJblAIS3yLapQqP8HKPZr';
            if ($token !== $tokenValido) {
                return $this->response->setJSON(['success' => false, 'error' => 'Token inválido.'])->setStatusCode(401);
            }

            $data = $this->request->getJSON(true);
            if (!isset($data['id_cita_agendada']) || !isset($data['asistencia'])) {
                return $this->response->setJSON(['success' => false, 'error' => 'Faltan parámetros requeridos.'])->setStatusCode(400);
            }

            $respuesta = cita_conversacion()->actualizarAsistenciaCita($data['id_cita_agendada'], $data['asistencia']);
            return $this->response->setJSON(['success' => true, 'data' => $respuesta]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'error' => $e->getMessage()])->setStatusCode(500);
        }
    }
}



