<?php

namespace Belly\Mensajes\Controllers\Api;

use App\Controllers\BaseController;

class PlantillaMensajeController extends BaseController
{

    public function listarTodas()
    {
        try {
            $opciones = [
                'id_app' => $this->request->getGet('id_app'),
                'fields' => $this->request->getGet('fields'),
                'per_page' => $this->request->getGet('per_page') ?? $this->request->getGet('limit'),
                'limit' => $this->request->getGet('limit'),
                'page' => $this->request->getGet('page'),
                'after' => $this->request->getGet('after'),
                'before' => $this->request->getGet('before'),
                'status' => $this->request->getGet('status'),
                'name' => $this->request->getGet('name'),
                'category' => $this->request->getGet('category'),
                'language' => $this->request->getGet('language'),
            ];
            $opciones = array_filter($opciones, function($value) {
                return $value !== null && $value !== '';
            });

            $resultado = plantilla()->obtenerPlantillasTodas($opciones);

            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado['data'],
                'paging' => $resultado['paging'],
                'pagination' => $resultado['pagination'],
                'total' => $resultado['total'],
                'filters' => $resultado['filters']
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function listarActivas()
    {
        try {
            $opciones = [];
            $id_app = $this->request->getGet('id_app');
            if ($id_app) $opciones['id_app'] = $id_app;

            $resultado = plantilla()->obtenerPlantillasActivas($opciones);

            return $this->response->setJSON($resultado);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function crear()
    {
        try {
            $contentType = $this->request->getHeaderLine('Content-Type');
            $is_multipart = strpos($contentType, 'multipart/form-data') !== false;
            $datos = null;
            
            // Intentar obtener JSON solo si NO es multipart/form-data
            if (!$is_multipart) {
                try {
                    $json_data = $this->request->getJSON(true);
                    if (!empty($json_data)) {
                        $datos = $json_data;
                    }
                } catch (\Exception $e) {
                    // Ignorar error si es form-data
                }
            }
            
            // Si no hay datos JSON, procesar como form-data
            if (empty($datos)) {
                $datos = [
                    'id_app' => $this->request->getPost('id_app'),
                    'name' => $this->request->getPost('name'),
                    'category' => $this->request->getPost('category'),
                    'language' => $this->request->getPost('language'),
                    'parameter_format' => $this->request->getPost('parameter_format'),
                    'allow_category_change' => $this->request->getPost('allow_category_change'),
                ];
                
                // Procesar components desde form-data
                $components_json = $this->request->getPost('components') 
                                ?? $this->request->getVar('components')
                                ?? ($_POST['components'] ?? null);
                
                if ($components_json) {
                    if (is_string($components_json)) {
                        $components_json = trim($components_json);
                        $components = json_decode($components_json, true);
                        
                        // Si falla, intentar limpiar el JSON
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $cleaned_json = preg_replace('/^\xEF\xBB\xBF/', '', $components_json);
                            $cleaned_json = trim($cleaned_json);
                            if (strpos($cleaned_json, '\\') !== false) {
                                $cleaned_json = stripslashes($cleaned_json);
                            }
                            $components = json_decode($cleaned_json, true);
                        }
                        
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            return $this->response->setJSON([
                                'success' => false,
                                'error' => "Error al procesar el campo 'components': " . json_last_error_msg()
                            ])->setStatusCode(400);
                        }
                        
                        if (!is_array($components)) {
                            return $this->response->setJSON([
                                'success' => false,
                                'error' => "El campo 'components' debe ser un array válido"
                            ])->setStatusCode(400);
                        }
                        
                        $datos['components'] = $components;
                    } elseif (is_array($components_json)) {
                        $datos['components'] = $components_json;
                    } else {
                        return $this->response->setJSON([
                            'success' => false,
                            'error' => "El campo 'components' debe ser un array o un JSON string válido"
                        ])->setStatusCode(400);
                    }
                } else {
                    return $this->response->setJSON([
                        'success' => false,
                        'error' => "El campo 'components' es obligatorio"
                    ])->setStatusCode(400);
                }
            }
            
            if (empty($datos)) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibieron datos'
                ])->setStatusCode(400);
            }

            // Validar que tenga id_app
            if (empty($datos['id_app'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El campo id_app es obligatorio'
                ])->setStatusCode(400);
            }

            $id_app = (int)$datos['id_app'];
            unset($datos['id_app']); // Remover id_app del array de datos de la plantilla

            // Buscar archivos en el request (tanto form-data como JSON con archivos)
            $archivo_header = $this->request->getFile('header_image');
            $app_id_meta = $this->request->getPost('app_id_meta') ?? $this->request->getVar('app_id_meta'); // Opcional
            
            // Si hay un archivo, procesarlo automáticamente
            if ($archivo_header && $archivo_header->isValid()) {
                // Buscar el componente HEADER de tipo IMAGE y subir el archivo
                if (isset($datos['components']) && is_array($datos['components'])) {
                    foreach ($datos['components'] as &$component) {
                        if (isset($component['type']) && $component['type'] === 'HEADER' && 
                            isset($component['format']) && ($component['format'] === 'IMAGE' || $component['format'] === 'VIDEO' || $component['format'] === 'DOCUMENT')) {
                            
                            // Subir el archivo automáticamente
                            try {
                                $upload_result = plantilla()->subirArchivoReanudable($id_app, $archivo_header, $app_id_meta);
                                
                                if ($upload_result['success']) {
                                    // Asignar el handle al componente
                                    $component['example'] = [
                                        'header_handle' => [$upload_result['handle']]
                                    ];
                                } else {
                                    throw new \Exception("Error al subir archivo del HEADER: " . ($upload_result['error'] ?? 'Error desconocido'));
                                }
                            } catch (\Exception $e) {
                                throw new \Exception("Error al procesar archivo del HEADER: " . $e->getMessage());
                            }
                            break; // Solo procesar el primer HEADER de tipo media
                        }
                    }
                }
            }

            $resultado = plantilla()->crearPlantilla($id_app, $datos);

            // Mensaje según el estado
            $status = $resultado['status'] ?? 'PENDING';
            $message = 'Plantilla creada exitosamente.';
            $warning = null;
            $info = [];
            
            if ($status === 'PENDING') {
                $message .= ' Está en proceso de revisión (puede tardar hasta 24 horas).';
                $info[] = 'Recibirás una notificación cuando se complete la revisión.';
            } elseif ($status === 'REJECTED') {
                $message .= ' ⚠️ La plantilla fue RECHAZADA durante la revisión automática.';
                $warning = 'La plantilla fue rechazada. Revisa los detalles para ver las razones específicas.';
                $info[] = 'Las plantillas rechazadas aparecen en Meta Business Manager con el motivo del rechazo.';
                $info[] = 'Puedes solicitar una revisión o editar la plantilla y volver a enviarla.';
            } elseif ($status === 'APPROVED') {
                $message .= ' ✅ La plantilla fue APROBADA y está lista para usar.';
                $info[] = 'Puedes comenzar a enviarla a los clientes inmediatamente.';
            }

            $response_data = [
                'success' => true,
                'data' => $resultado['data'],
                'id' => $resultado['id'],
                'name' => $resultado['name'],
                'status' => $status,
                'message' => $message
            ];

            if ($warning) {
                $response_data['warning'] = $warning;
            }

            if (!empty($info)) {
                $response_data['info'] = $info;
            }

            return $this->response->setJSON($response_data)->setStatusCode(201);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }


    public function actualizarEstado()
    {
        try {
            $datos = $this->request->getJSON(true) ?: $this->request->getPost();

            if (empty($datos['template_id']) || !isset($datos['id_estado']) || empty($datos['id_app'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Campos requeridos: template_id, id_estado, id_app'
                ])->setStatusCode(400);
            }

            $resultado = plantilla()->actualizarEstadoPlantilla(
                $datos['template_id'], 
                (int)$datos['id_estado'],
                (int)$datos['id_app'],
                $datos['nombre'] ?? ''
            );

            return $this->response->setJSON($resultado);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function eliminar($id_app, $template_name)
    {
        try {
            $resultado = plantilla()->eliminarPlantilla($id_app, $template_name);

            return $this->response->setJSON([
                'success' => true,
                'message' => $resultado['message'],
                'data' => $resultado['data'] ?? null
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function subirArchivoReanudable()
    {
        try {
            $id_app = $this->request->getPost('id_app');
            $app_id_meta = $this->request->getPost('app_id_meta'); // Opcional
            
            if (empty($id_app)) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El campo id_app es obligatorio'
                ])->setStatusCode(400);
            }

            $id_app = (int)$id_app;
            $file = $this->request->getFile('archivo');

            if (!$file) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibió el archivo. Usa el campo "archivo" en form-data.'
                ])->setStatusCode(400);
            }

            if (!$file->isValid()) {
                $error = $file->getError();
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el formulario',
                    UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                    UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                    UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                    UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
                ];
                
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El archivo no es válido: ' . ($errorMessages[$error] ?? 'Error desconocido')
                ])->setStatusCode(400);
            }

            $resultado = plantilla()->subirArchivoReanudable($id_app, $file, $app_id_meta);

            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado,
                'handle' => $resultado['handle'],
                'message' => $resultado['message']
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }
}

