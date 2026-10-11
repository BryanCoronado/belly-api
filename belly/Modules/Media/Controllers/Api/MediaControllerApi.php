<?php

namespace Belly\Media\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Media\Models\GrupoGaleriaModel;

class MediaControllerApi extends BaseController{
    

    public function listarImagenes()
    {
        try {
            // Obtener parámetros de paginación
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('per_page') ?? 20;
            $search = $this->request->getGet('search') ?? null;
            
            $resultado = media_galeria()->listarImagenes($page, $perPage, $search);
            return $this->response->setJSON($resultado);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function listarVideos()
    {
        try {
            // Obtener parámetros de paginación
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('per_page') ?? 20;
            $search = $this->request->getGet('search') ?? null;
            
            $resultado = media_galeria()->listarVideos($page, $perPage, $search);
            return $this->response->setJSON($resultado);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function listarGruposGaleria()
    {
        try {
            $grupoModel = new GrupoGaleriaModel();
            $grupos = $grupoModel
                ->select('id, nombre')
                ->where('activo', 1)
                ->orderBy('nombre', 'ASC')
                ->findAll();

            return $this->response->setJSON([
                'success' => true,
                'data' => $grupos,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Campañas Meta para asociar video/imagen al subir galería (incluye opción General).
     */
    public function listarCampaniasGaleria()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => media_galeria()->listarCampaniasParaGaleria(),
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
  
    public function subirGaleria()
    {
        try {
            $file = $this->request->getFile('archivo');
            
            if (!$file) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibió el archivo. Usa el campo "archivo" en form-data.'
                ])->setStatusCode(400);
            }
            
            // Obtener parámetros obligatorios
            $nombre = $this->request->getPost('nombre');
            if (!$nombre) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El campo "nombre" es obligatorio.'
                ])->setStatusCode(400);
            }
            
            $tipo = $this->request->getPost('tipo');
            if (!$tipo) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El campo "tipo" es obligatorio. Debe ser "image" o "video".'
                ])->setStatusCode(400);
            }
            
            if (!in_array($tipo, ['image', 'video'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El campo "tipo" debe ser "image" o "video".'
                ])->setStatusCode(400);
            }
            
            // Parámetros opcionales
            $descripcion = $this->request->getPost('descripcion') ?? null;
            $tags = $this->request->getPost('tags') ?? null;
            $fecha_inicio = $this->request->getPost('fecha_inicio') ?? null;
            $fecha_fin = $this->request->getPost('fecha_fin') ?? null;
            $bloque = $this->request->getPost('bloque') ?? null;
            $grupo = $this->request->getPost('grupo') ?? null;
            $campaignIds = $this->parseCampaignIdsFromRequest();
            
            $resultado = media_galeria()->subirGaleria(
                $file,
                $nombre,
                $descripcion,
                $tipo,
                $tags,
                $fecha_inicio,
                $fecha_fin,
                $bloque,
                $grupo,
                null,
                $campaignIds
            );

            if (!$resultado['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error']
                ])->setStatusCode(400);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Archivo subido exitosamente',
                'data' => $resultado
            ]);

        } catch (\Exception $e) {
            log_message('error', 'MediaControllerApi::subirGaleria - Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function listarDocumentos()
    {
        try {
            // Obtener parámetros de paginación
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('per_page') ?? 20;
            $search = $this->request->getGet('search') ?? null;
            
            $resultado = media_documento()->listarDocumentos($page, $perPage, $search);
            return $this->response->setJSON($resultado);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function subirDocumento()
    {
        try {
            $file = $this->request->getFile('archivo');
            
            if (!$file) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibió el archivo. Usa el campo "archivo" en form-data.'
                ])->setStatusCode(400);
            }
            
            // Obtener parámetros obligatorios
            $nombre = $this->request->getPost('nombre');
            if (!$nombre) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El campo "nombre" es obligatorio.'
                ])->setStatusCode(400);
            }
            
            // Parámetros opcionales
            $tags = $this->request->getPost('tags') ?? null;
            $fecha_inicio = $this->request->getPost('fecha_inicio') ?? null;
            $fecha_fin = $this->request->getPost('fecha_fin') ?? null;
            
            $resultado = media_documento()->subirDocumento($file, $nombre, $tags, $fecha_inicio, $fecha_fin);

            if (!$resultado['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error']
                ])->setStatusCode(400);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Documento subido exitosamente',
                'data' => $resultado
            ]);

        } catch (\Exception $e) {
            log_message('error', 'MediaControllerApi::subirDocumento - Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }


    public function eliminarGaleria($id)
    {
        try {
            $id = (int) $id;
            
            if ($id <= 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'ID inválido'
                ])->setStatusCode(400);
            }
            
            // Extraer datos del request
            $datos = [];
            $datos['eliminar_archivo'] = $this->request->getPost('eliminar_archivo') ?? $this->request->getVar('eliminar_archivo') ?? true;
            
            $resultado = media_galeria()->eliminarGaleria($id, $datos);

            if (!$resultado['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error']
                ])->setStatusCode(400);
            }

            return $this->response->setJSON($resultado);

        } catch (\Exception $e) {
            log_message('error', 'MediaControllerApi::eliminarGaleria - Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function editarGaleria($id)
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'ID inválido'
                ])->setStatusCode(400);
            }

            $datos = $this->request->getJSON(true);
            if (empty($datos)) {
                $datos = $this->request->getRawInput();
            }
            if (empty($datos)) {
                $datos = $this->request->getPost();
            }

            $resultado = media_galeria()->editarGaleria($id, is_array($datos) ? $datos : []);

            if (!$resultado['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error']
                ])->setStatusCode(400);
            }

            return $this->response->setJSON($resultado);
        } catch (\Exception $e) {
            log_message('error', 'MediaControllerApi::editarGaleria - Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }


    public function eliminarDocumento($id)
    {
        try {
            $id = (int) $id;
            
            if ($id <= 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'ID inválido'
                ])->setStatusCode(400);
            }
            
            // Extraer datos del request
            $datos = [];
            $datos['eliminar_archivo'] = $this->request->getPost('eliminar_archivo') ?? $this->request->getVar('eliminar_archivo') ?? true;
            
            $resultado = media_documento()->eliminarDocumento($id, $datos);

            if (!$resultado['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error']
                ])->setStatusCode(400);
            }

            return $this->response->setJSON($resultado);

        } catch (\Exception $e) {
            log_message('error', 'MediaControllerApi::eliminarDocumento - Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * @return list<string>|null
     */
    private function parseCampaignIdsFromRequest(): ?array
    {
        $raw = $this->request->getPost('campaign_ids') ?? $this->request->getGet('campaign_ids');
        if ($raw === null) {
            $json = $this->request->getJSON(true);
            if (is_array($json) && array_key_exists('campaign_ids', $json)) {
                $raw = $json['campaign_ids'];
            }
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            $parts = array_map('trim', explode(',', $raw));

            return $parts === [''] ? [] : $parts;
        }

        if (is_array($raw)) {
            return $raw;
        }

        $single = $this->request->getPost('campaign_id');
        if ($single !== null && trim((string) $single) !== '') {
            return [trim((string) $single)];
        }

        return [];
    }

}
