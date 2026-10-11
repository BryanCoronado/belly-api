<?php
namespace Belly\Mensajes\UseCases;

use Belly\Mensajes\Config\MensajesConfig;
use Belly\Mensajes\Models\PlantillaMetaModel;
use Belly\Conversacion\UseCases\ConversacionUseCase;
use Belly\Conversacion\Config\ConversacionConfig;

class PlantillaMensajeUseCase {
    protected MensajesConfig $config;
    protected ?PlantillaMetaModel $plantilla_meta_provider = NULL;
    public function __construct(MensajesConfig $config)
    {
        $this->config = $config;
    }
    
    public function getPlantillaMetaProvider(): PlantillaMetaModel{
        if ($this->plantilla_meta_provider !== null) {
            return $this->plantilla_meta_provider;
        }

        $class_name = $this->config->plantilla_meta_provider;
        $this->plantilla_meta_provider = new $class_name();

        return $this->plantilla_meta_provider;
    }

    
    public function obtenerPlantillasPorApp($id_app, $opciones = [])
    {
        try {
            // Obtener la app directamente
            $app = app()->getAppProvider()->find($id_app);
            
            if (!$app) {
                throw new \Exception("No se encontró la app con ID: $id_app");
            }

            if (!$app->whatsapp_business_account_id) {
                throw new \Exception("La app no tiene whatsapp_business_account_id configurado");
            }

            if (!$app->access_token) {
                throw new \Exception("La app no tiene access_token configurado");
            }

            $waba_id = $app->whatsapp_business_account_id;
            $access_token = $app->access_token;

            // Configurar valores por defecto
            $per_page = isset($opciones['per_page']) ? (int)$opciones['per_page'] : (isset($opciones['limit']) ? (int)$opciones['limit'] : 20);
            $per_page = max(1, min(100, $per_page)); // Limitar entre 1 y 100
            
            $version_api = 'v19.0';
            $endpoint = "https://graph.facebook.com/{$version_api}/{$waba_id}/message_templates";
            
            $params = [];
            
            // Campos a retornar
            if (isset($opciones['fields'])) {
                $params['fields'] = $opciones['fields'];
            }
            
            // Paginación
            $params['limit'] = $per_page;
            
            // Cursor de paginación (after o before)
            if (isset($opciones['after'])) {
                $params['after'] = $opciones['after'];
            } elseif (isset($opciones['before'])) {
                $params['before'] = $opciones['before'];
            }
            
            // Filtros
            if (isset($opciones['name']) && !empty($opciones['name'])) {
                $params['name'] = $opciones['name'];
            }
            
            if (isset($opciones['status']) && !empty($opciones['status'])) {
                $params['status'] = $opciones['status'];
            }
            
            if (isset($opciones['category']) && !empty($opciones['category'])) {
                $params['category'] = $opciones['category'];
            }
            
            if (isset($opciones['language']) && !empty($opciones['language'])) {
                $params['language'] = $opciones['language'];
            }
            
            if (!empty($params)) {
                $endpoint .= '?' . http_build_query($params);
            }

            $headers = [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json"
            ];

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("Error de cURL: $error");
            }

            $responseData = json_decode($response, true);

            if ($http_code === 200 || $http_code === 201) {
                log_message('debug', "Plantillas obtenidas exitosamente para app {$app->id}");
                
                $data = $responseData['data'] ?? [];
                $paging = $responseData['paging'] ?? null;
                
                // Obtener datos de la BD para cada plantilla
                $plantilla_meta_model = $this->getPlantillaMetaProvider();
                $template_ids = array_column($data, 'id');
                
                $plantillas_bd = [];
                if (!empty($template_ids)) {
                    $plantillas_bd = $plantilla_meta_model
                        ->whereIn('template_id', $template_ids)
                        ->findAll();
                    
                    // Crear un mapa por template_id para acceso rápido
                    $plantillas_bd_map = [];
                    foreach ($plantillas_bd as $pm) {
                        $plantillas_bd_map[$pm->template_id] = $pm;
                    }
                }
                
                // Agregar id_app y datos de BD a cada plantilla
                foreach ($data as &$plantilla) {
                    $plantilla['id_app'] = $id_app;
                    $plantilla['app_nombre'] = $app->nombre ?? null;
                    
                    // Agregar datos de la BD si existe
                    if (isset($plantilla['id']) && isset($plantillas_bd_map[$plantilla['id']])) {
                        $pm = $plantillas_bd_map[$plantilla['id']];
                        $plantilla['id_estado_sistema'] = $pm->id_estado ?? null;
                        $plantilla['id_plantilla_meta'] = $pm->id ?? null;
                    } else {
                        $plantilla['id_estado_sistema'] = null;
                        $plantilla['id_plantilla_meta'] = null;
                    }
                }
                
                // Construir información de paginación mejorada
                $pagination = [
                    'per_page' => $per_page,
                    'current_count' => count($data),
                    'has_next' => !empty($paging['cursors']['after']),
                    'has_prev' => !empty($paging['cursors']['before']),
                    'next_cursor' => $paging['cursors']['after'] ?? null,
                    'prev_cursor' => $paging['cursors']['before'] ?? null,
                ];
                
                return [
                    'success' => true,
                    'data' => $data,
                    'paging' => $paging,
                    'pagination' => $pagination,
                    'total' => count($data),
                    'filters' => [
                        'id_app' => $id_app,
                        'status' => $opciones['status'] ?? null,
                        'name' => $opciones['name'] ?? null,
                        'category' => $opciones['category'] ?? null,
                        'language' => $opciones['language'] ?? null,
                    ]
                ];
            } else {
                $error_message = $responseData['error']['message'] ?? 'Error desconocido';
                $error_code = $responseData['error']['code'] ?? $http_code;
                log_message('error', "Error al obtener plantillas: HTTP $http_code - $error_message");
                throw new \Exception("Error al obtener plantillas: $error_message (Código: $error_code)");
            }

        } catch (\Exception $e) {
            log_message('error', "Error en obtenerPlantillasPorApp: " . $e->getMessage());
            throw $e;
        }
    }


 
    public function obtenerPlantillasTodas($opciones = [])
    {
        try {
            $id_app = isset($opciones['id_app']) ? (int)$opciones['id_app'] : null;
            
            if ($id_app) {
                return $this->obtenerPlantillasPorApp($id_app, $opciones);
            }
            $app_model = app()->getAppProvider();
            $apps = $app_model
                ->where('estado', 1)
                ->where('deleted_at', null)
                ->where('whatsapp_business_account_id IS NOT NULL')
                ->where('access_token IS NOT NULL')
                ->where('whatsapp_business_account_id !=', '')
                ->where('access_token !=', '')
                ->findAll();
            
            if (empty($apps)) {
                return [
                    'success' => true,
                    'data' => [],
                    'paging' => null,
                    'pagination' => [
                        'per_page' => $opciones['per_page'] ?? 20,
                        'current_count' => 0,
                        'has_next' => false,
                        'has_prev' => false,
                    ],
                    'total' => 0,
                    'filters' => $opciones
                ];
            }
            
            $todas_plantillas = [];
            $errores = [];
            
            foreach ($apps as $app) {
                try {
                    $resultado = $this->obtenerPlantillasPorApp($app->id, $opciones);
                    if ($resultado['success'] && !empty($resultado['data'])) {
                        $todas_plantillas = array_merge($todas_plantillas, $resultado['data']);
                    }
                } catch (\Exception $e) {
                    $errores[] = "App {$app->id} ({$app->nombre}): " . $e->getMessage();
                    log_message('warning', "Error obteniendo plantillas de app {$app->id}: " . $e->getMessage());
                }
            }
            
            usort($todas_plantillas, function($a, $b) {
                $nombreA = $a['name'] ?? '';
                $nombreB = $b['name'] ?? '';
                return strcmp($nombreA, $nombreB);
            });
            
            $per_page = isset($opciones['per_page']) ? (int)$opciones['per_page'] : (isset($opciones['limit']) ? (int)$opciones['limit'] : 20);
            $per_page = max(1, min(100, $per_page));
            $page = isset($opciones['page']) ? (int)$opciones['page'] : 1;
            $page = max(1, $page);
            
            $total = count($todas_plantillas);
            $offset = ($page - 1) * $per_page;
            $paginated_data = array_slice($todas_plantillas, $offset, $per_page);
            
            $total_pages = ceil($total / $per_page);
            
            return [
                'success' => true,
                'data' => $paginated_data,
                'paging' => null, // No hay paging de Facebook cuando se combinan múltiples apps
                'pagination' => [
                    'per_page' => $per_page,
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'current_count' => count($paginated_data),
                    'total' => $total,
                    'has_next' => $page < $total_pages,
                    'has_prev' => $page > 1,
                ],
                'total' => $total,
                'filters' => $opciones,
                'apps_consultadas' => count($apps),
                'errores' => !empty($errores) ? $errores : null
            ];
            
        } catch (\Exception $e) {
            log_message('error', "Error en obtenerPlantillasTodas: " . $e->getMessage());
            throw $e;
        }
    }


    public function obtenerPlantillasActivas($opciones = [])
    {
        try {
            // Forzar per_page alto para traer todas las plantillas
            $opcionesTodas = $opciones;
            $opcionesTodas['per_page'] = 1000;
            $opcionesTodas['page'] = 1;
            
            $resultadoCompleto = $this->obtenerPlantillasTodas($opcionesTodas);
            $todas = $resultadoCompleto['data'] ?? [];

            // Filtrar solo activas (id_estado_sistema = 1)
            $filtradas = array_values(array_filter($todas, function($plantilla) {
                return isset($plantilla['id_estado_sistema']) && (int)$plantilla['id_estado_sistema'] === 1;
            }));
            
            return [
                'success' => true,
                'data' => $filtradas,
                'total' => count($filtradas)
            ];
            
        } catch (\Exception $e) {
            log_message('error', "Error en obtenerPlantillasActivas: " . $e->getMessage());
            throw $e;
        }
    }

    public function crearPlantilla($id_app, $datos)
    {
        try {
            if (empty($datos['name'])) {
                throw new \Exception("El campo 'name' es obligatorio");
            }

            if (empty($datos['category'])) {
                throw new \Exception("El campo 'category' es obligatorio");
            }

            if (!in_array($datos['category'], ['AUTHENTICATION', 'MARKETING', 'UTILITY'])) {
                throw new \Exception("La categoría debe ser: AUTHENTICATION, MARKETING o UTILITY");
            }

            if (empty($datos['language'])) {
                throw new \Exception("El campo 'language' es obligatorio");
            }

            if (empty($datos['components']) || !is_array($datos['components'])) {
                throw new \Exception("El campo 'components' es obligatorio y debe ser un array");
            }

            // Validar nombre (máximo 512 caracteres alfanuméricos en minúscula y guiones bajos)
            $name = strtolower($datos['name']);
            if (!preg_match('/^[a-z0-9_]+$/', $name)) {
                throw new \Exception("El nombre solo puede contener letras minúsculas, números y guiones bajos");
            }

            if (strlen($name) > 512) {
                throw new \Exception("El nombre no puede exceder 512 caracteres");
            }

            // Obtener la app
            $app = app()->getAppProvider()->find($id_app);
            
            if (!$app) {
                throw new \Exception("No se encontró la app con ID: $id_app");
            }

            if (!$app->whatsapp_business_account_id) {
                throw new \Exception("La app no tiene whatsapp_business_account_id configurado");
            }

            if (!$app->access_token) {
                throw new \Exception("La app no tiene access_token configurado");
            }

            $waba_id = $app->whatsapp_business_account_id;
            $access_token = $app->access_token;

            // Construir el payload base
            $payload = [
                'name' => $name,
                'category' => $datos['category'],
                'language' => $datos['language'],
            ];

            // Agregar parameter_format si se especifica (NAMED o POSITIONAL)
            if (isset($datos['parameter_format'])) {
                if (!in_array($datos['parameter_format'], ['NAMED', 'POSITIONAL'])) {
                    throw new \Exception("El parameter_format debe ser NAMED o POSITIONAL");
                }
                $payload['parameter_format'] = $datos['parameter_format'];
            }

            // Procesar componentes
            $components = [];
            foreach ($datos['components'] as $component) {
                $processed_component = $this->procesarComponente($component, $datos['parameter_format'] ?? 'POSITIONAL');
                if ($processed_component) {
                    $components[] = $processed_component;
                }
            }

            if (empty($components)) {
                throw new \Exception("Debe haber al menos un componente válido");
            }

            $payload['components'] = $components;

            // Agregar allow_category_change si se especifica
            if (isset($datos['allow_category_change'])) {
                $payload['allow_category_change'] = (bool)$datos['allow_category_change'];
            }

            // Log del payload antes de enviar
            log_message('debug', "Payload a enviar a Facebook: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // PRIMERO: Realizar la petición POST a Meta
            // Solo si Meta es exitoso, se creará en BD
            $version_api = 'v19.0';
            $endpoint = "https://graph.facebook.com/{$version_api}/{$waba_id}/message_templates";

            $headers = [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json"
            ];

            $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("Error de cURL: $error");
            }

            $responseData = json_decode($response, true);
            
            // Log de la respuesta
            log_message('debug', "Respuesta de Facebook (HTTP $http_code): " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($http_code === 200 || $http_code === 201) {
                log_message('debug', "Plantilla creada exitosamente para app {$app->id}: {$name}");
                
                $status = $responseData['status'] ?? 'PENDING';
                $template_id = $responseData['id'] ?? null;
                
                // Guardar en la base de datos solo si Meta fue exitoso
                if ($template_id) {
                    try {
                        $plantilla_meta_model = $this->getPlantillaMetaProvider();
                        
                        // Verificar si ya existe una plantilla con ese template_id
                        $idEmpresa = (int) ($app->id_empresa ?? 0);
                        $plantilla_existente = $plantilla_meta_model
                            ->where('id_empresa', $idEmpresa)
                            ->where('template_id', $template_id)
                            ->first();
                        
                        if (!$plantilla_existente) {
                            $plantilla_meta_model->insert([
                                'id_empresa' => $idEmpresa,
                                'template_id' => $template_id,
                                'id_app' => $id_app,
                                'nombre' => $name,
                                'id_estado' => 2 // Estado 2 = desactivado (porque normalmente estará en pendiente)
                            ]);
                            log_message('debug', "Plantilla guardada en base de datos con template_id: {$template_id}");
                        } else {
                            log_message('warning', "La plantilla con template_id {$template_id} ya existe en la base de datos");
                        }
                    } catch (\Exception $e) {
                        log_message('error', "Error al guardar plantilla en base de datos: " . $e->getMessage());
                    }
                }
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'id' => $template_id,
                    'name' => $name,
                    'status' => $status
                ];
            } else {
                $error_message = $responseData['error']['message'] ?? 'Error desconocido';
                $error_code = $responseData['error']['code'] ?? $http_code;
                $error_type = $responseData['error']['type'] ?? 'Unknown';
                $error_subcode = $responseData['error']['error_subcode'] ?? null;
                
                log_message('error', "Error al crear plantilla: HTTP $http_code - $error_message (Tipo: $error_type, Subcode: $error_subcode)");
                
                $user_message = $responseData['error']['error_user_msg'] ?? null;
                $user_title = $responseData['error']['error_user_title'] ?? null;
                
                $error_msg = "Error al crear plantilla: $error_message";
                if ($user_message) {
                    $error_msg .= " - $user_message";
                }
                if ($user_title) {
                    $error_msg .= " ($user_title)";
                }
                
                throw new \Exception($error_msg);
            }

        } catch (\Exception $e) {
            log_message('error', "Error en crearPlantilla: " . $e->getMessage());
            throw $e;
        }
    }

    private function procesarComponente($component, $parameter_format = 'POSITIONAL')
    {
        $type = $component['type'] ?? null;
        
        if (!$type) {
            return null;
        }

        $processed = ['type' => $type];

        switch ($type) {
            case 'HEADER':
                $processed = $this->procesarHeader($component, $processed);
                break;
            
            case 'BODY':
                $processed = $this->procesarBody($component, $processed, $parameter_format);
                break;
            
            case 'FOOTER':
                $processed = $this->procesarFooter($component, $processed);
                break;
            
            case 'BUTTONS':
                $processed = $this->procesarButtons($component, $processed);
                break;
            
            default:
                log_message('warning', "Tipo de componente desconocido: $type");
                return null;
        }

        return $processed;
    }

    private function procesarHeader($component, $processed)
    {
        $format = $component['format'] ?? 'TEXT';
        
        $processed['format'] = $format;

        switch ($format) {
            case 'TEXT':
                if (!isset($component['text'])) {
                    throw new \Exception("El HEADER de tipo TEXT debe tener un campo 'text'");
                }
                $processed['text'] = $component['text'];
                
                // Para TEXT, el example es opcional pero si existe debe tener header_text
                if (isset($component['example'])) {
                    if (isset($component['example']['header_text'])) {
                        $processed['example'] = [
                            'header_text' => $component['example']['header_text']
                        ];
                    } else {
                        // Si viene como array directo, mantenerlo
                        $processed['example'] = $component['example'];
                    }
                }
                break;
            
            case 'IMAGE':
            case 'VIDEO':
            case 'DOCUMENT':
                // Para media, el example es obligatorio y debe contener header_handle
                if (!isset($component['example'])) {
                    throw new \Exception("El HEADER de tipo $format debe tener un campo 'example' con header_handle");
                }
                
                if (isset($component['example']['header_handle'])) {
                    $processed['example'] = [
                        'header_handle' => $component['example']['header_handle']
                    ];
                } else {
                    // Si viene como array directo, mantenerlo
                    $processed['example'] = $component['example'];
                }
                break;
            
            default:
                throw new \Exception("Formato de HEADER no válido: $format");
        }

        return $processed;
    }

    private function procesarBody($component, $processed, $parameter_format)
    {
        if (!isset($component['text'])) {
            throw new \Exception("El componente BODY debe tener un campo 'text'");
        }

        $text = $component['text'];
        
        // Validar que las variables no estén al principio ni al final del texto
        $this->validarPosicionParametros($text, $parameter_format);

        $processed['text'] = $text;

        // Procesar ejemplo según el formato de parámetros
        // El example solo es necesario si hay parámetros en el texto
        if (isset($component['example'])) {
            if ($parameter_format === 'NAMED') {
                // Formato NAMED: body_text_named_params
                if (isset($component['example']['body_text_named_params'])) {
                    $processed['example'] = [
                        'body_text_named_params' => $component['example']['body_text_named_params']
                    ];
                } else {
                    // Si viene directamente como array, mantenerlo
                    $processed['example'] = $component['example'];
                }
            } else {
                // Formato POSITIONAL: body_text (array de arrays)
                if (isset($component['example']['body_text'])) {
                    // Asegurar que body_text sea un array de arrays
                    $body_text = $component['example']['body_text'];
                    if (is_array($body_text) && !empty($body_text)) {
                        // Si el primer elemento no es un array, envolverlo
                        if (!is_array($body_text[0])) {
                            $body_text = [$body_text];
                        }
                        $processed['example'] = [
                            'body_text' => $body_text
                        ];
                    } else {
                        $processed['example'] = [
                            'body_text' => $body_text
                        ];
                    }
                } else {
                    // Si viene directamente como array, intentar procesarlo
                    $example = $component['example'];
                    if (is_array($example)) {
                        // Si es un array de arrays, usar body_text
                        if (isset($example[0]) && is_array($example[0])) {
                            $processed['example'] = [
                                'body_text' => $example
                            ];
                        } else {
                            // Si es un array simple, envolverlo
                            $processed['example'] = [
                                'body_text' => [$example]
                            ];
                        }
                    } else {
                        $processed['example'] = $component['example'];
                    }
                }
            }
        }

        return $processed;
    }

    private function procesarFooter($component, $processed)
    {
        if (isset($component['text'])) {
            $processed['text'] = $component['text'];
        } else {
            // Footer puede ser opcional, pero si se especifica debería tener text
            log_message('warning', "Componente FOOTER sin campo 'text'");
        }

        return $processed;
    }

    private function procesarButtons($component, $processed)
    {
        if (!isset($component['buttons']) || !is_array($component['buttons'])) {
            throw new \Exception("El componente BUTTONS debe tener un array 'buttons'");
        }

        $buttons = [];
        foreach ($component['buttons'] as $button) {
            $button_type = $button['type'] ?? null;
            
            if (!$button_type) {
                continue;
            }

            $processed_button = ['type' => $button_type];

            switch ($button_type) {
                case 'QUICK_REPLY':
                    if (isset($button['text'])) {
                        $processed_button['text'] = $button['text'];
                    }
                    break;
                
                case 'URL':
                    if (isset($button['text'])) {
                        $processed_button['text'] = $button['text'];
                    }
                    if (isset($button['url'])) {
                        $processed_button['url'] = $button['url'];
                    }
                    if (isset($button['example'])) {
                        $processed_button['example'] = $button['example'];
                    }
                    break;
                
                case 'PHONE_NUMBER':
                    if (isset($button['text'])) {
                        $processed_button['text'] = $button['text'];
                    }
                    if (isset($button['phone_number'])) {
                        $processed_button['phone_number'] = $button['phone_number'];
                    }
                    break;
                
                default:
                    log_message('warning', "Tipo de botón desconocido: $button_type");
                    continue 2;
            }

            $buttons[] = $processed_button;
        }

        if (!empty($buttons)) {
            $processed['buttons'] = $buttons;
        }

        return $processed;
    }

    private function validarPosicionParametros($text, $parameter_format)
    {
        if ($parameter_format === 'NAMED') {
            // Patrón para parámetros con nombre: {{nombre_parametro}}
            $pattern = '/\{\{([a-z0-9_]+)\}\}/';
        } else {
            // Patrón para parámetros posicionales: {{1}}, {{2}}, etc.
            $pattern = '/\{\{(\d+)\}\}/';
        }

        // Encontrar todas las coincidencias con sus posiciones
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        
        if (empty($matches[0])) {
            return; // No hay parámetros, no hay problema
        }
        
        foreach ($matches[0] as $match) {
            $paramStart = $match[1]; // Posición donde empieza el parámetro
            $paramLength = strlen($match[0]); // Longitud del parámetro
            $paramEnd = $paramStart + $paramLength;
            
            // Verificar si el parámetro está al principio (después de espacios en blanco)
            $textBefore = trim(substr($text, 0, $paramStart));
            if (empty($textBefore)) {
                throw new \Exception("Los parámetros no pueden estar al principio del texto. El texto debe empezar con contenido antes del primer parámetro.");
            }
            
            // Verificar si el parámetro está al final (antes de espacios en blanco)
            $textAfter = trim(substr($text, $paramEnd));
            if (empty($textAfter)) {
                throw new \Exception("Los parámetros no pueden estar al final del texto. El texto debe tener contenido después del último parámetro.");
            }
        }
    }

    public function actualizarEstadoPlantilla($template_id, $id_estado, $id_app, $nombre = '')
    {
        if (!in_array($id_estado, [1, 2])) {
            throw new \Exception("El id_estado debe ser 1 o 2");
        }

        $model = $this->getPlantillaMetaProvider();
        $app = app()->getAppProvider()->find($id_app);
        $idEmpresa = (int) ($app->id_empresa ?? 0);
        $plantilla = $model->where('id_empresa', $idEmpresa)->where('template_id', $template_id)->first();
        
        if ($plantilla) {
            $model->update($plantilla->id, ['id_estado' => $id_estado]);
        } else {
            $model->insert([
                'id_empresa' => $idEmpresa,
                'template_id' => $template_id,
                'id_app' => $id_app,
                'nombre' => $nombre,
                'id_estado' => $id_estado
            ]);
        }
        
        return ['success' => true, 'message' => 'Estado actualizado'];
    }

    public function eliminarPlantilla($id_app, $template_name)
    {
        try {
            $app = app()->getAppProvider()->find($id_app);
            if (!$app || !$app->whatsapp_business_account_id || !$app->access_token) {
                throw new \Exception("App no válida o sin credenciales configuradas");
            }

            if (empty($template_name)) {
                throw new \Exception("El nombre de la plantilla es obligatorio");
            }

            $endpoint = "https://graph.facebook.com/v19.0/{$app->whatsapp_business_account_id}/message_templates?" . http_build_query([
                'name' => $template_name
            ]);
            
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$app->access_token}",
                    "Content-Type: application/json"
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("Error de cURL: $error");
            }

            $responseData = json_decode($response, true);

            if ($http_code === 200) {
                // Eliminar también de BD local si existe
                $plantilla_meta_model = $this->getPlantillaMetaProvider();
                $plantilla_bd = $plantilla_meta_model
                    ->where('nombre', $template_name)
                    ->where('id_app', $id_app)
                    ->first();
                
                if ($plantilla_bd) {
                    $plantilla_meta_model->delete($plantilla_bd->id);
                }
                
                return [
                    'success' => true,
                    'message' => "Plantilla eliminada exitosamente",
                    'template_name' => $template_name
                ];
            }

            $error_msg = $responseData['error']['message'] ?? 'Error desconocido';
            $error_code = $responseData['error']['code'] ?? $http_code;
            
            if ($http_code === 404 || $error_code == 100) {
                throw new \Exception("No se encontró la plantilla o no tienes permisos para eliminarla");
            } elseif ($http_code === 403) {
                throw new \Exception("No tienes permisos para eliminar esta plantilla");
            }
            
            throw new \Exception("Error al eliminar: $error_msg (Código: $error_code)");

        } catch (\Exception $e) {
            log_message('error', "Error en eliminarPlantilla: " . $e->getMessage());
            throw $e;
        }
    }


    public function subirArchivoReanudable($id_app, $archivo, $app_id_meta = null)
    {
        try {
            $app = app()->getAppProvider()->find($id_app);
            
            if (!$app) {
                throw new \Exception("No se encontró la app con ID: $id_app");
            }

            if (!$app->access_token) {
                throw new \Exception("La app no tiene access_token configurado");
            }

            $access_token = $app->access_token;

            // Obtener información del archivo
            $file_path = null;
            $file_size = 0;
            $file_type = 'application/pdf';
            $file_name = 'file.pdf';

            if (is_string($archivo)) {
                // Es una ruta al archivo
                if (!file_exists($archivo)) {
                    throw new \Exception("El archivo no existe: $archivo");
                }
                $file_path = $archivo;
                $file_size = filesize($archivo);
                $file_name = basename($archivo);
                
                // Detectar tipo MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detected_type = finfo_file($finfo, $archivo);
                finfo_close($finfo);
                
                if ($detected_type) {
                    $file_type = $detected_type;
                }
            } elseif (is_object($archivo) && method_exists($archivo, 'getTempName')) {
                // Es un UploadedFile de CodeIgniter
                $file_path = $archivo->getTempName();
                $file_size = $archivo->getSize();
                $file_type = $archivo->getMimeType();
                $file_name = $archivo->getName();
            } else {
                throw new \Exception("Formato de archivo no válido. Debe ser una ruta o un UploadedFile");
            }

            // Validar tipos de archivo permitidos
            $allowed_types = [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png',
                'video/mp4'
            ];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new \Exception("Tipo de archivo no permitido. Solo se permiten: PDF, JPEG, JPG, PNG, MP4");
            }

            if ($file_size === 0) {
                throw new \Exception("El archivo está vacío");
            }

            // Obtener APP_ID de Meta si no se proporcionó
            if (!$app_id_meta) {
                $app_id_meta = $this->obtenerAppIdMeta($access_token);
                if (!$app_id_meta) {
                    throw new \Exception("No se pudo obtener el APP_ID de Meta. Por favor, proporciona el app_id_meta como parámetro.");
                }
            }

            $version_api = 'v24.0';

            // Paso 1: Iniciar sesión de subida
            $init_endpoint = "https://graph.facebook.com/{$version_api}/{$app_id_meta}/uploads";
            $init_params = [
                'file_name' => $file_name,
                'file_length' => $file_size,
                'file_type' => $file_type,
                'access_token' => $access_token
            ];

            $ch_init = curl_init($init_endpoint . '?' . http_build_query($init_params));
            curl_setopt($ch_init, CURLOPT_POST, true);
            curl_setopt($ch_init, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_init, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch_init, CURLOPT_SSL_VERIFYHOST, false);

            $init_response = curl_exec($ch_init);
            $init_http_code = curl_getinfo($ch_init, CURLINFO_HTTP_CODE);
            $init_error = curl_error($ch_init);
            curl_close($ch_init);

            if ($init_error) {
                throw new \Exception("Error de cURL al iniciar sesión de subida: $init_error");
            }

            $init_data = json_decode($init_response, true);

            if ($init_http_code !== 200) {
                $error_message = $init_data['error']['message'] ?? 'Error desconocido';
                $error_code = $init_data['error']['code'] ?? $init_http_code;
                throw new \Exception("Error al iniciar sesión de subida: $error_message (Código: $error_code)");
            }

            if (!isset($init_data['id'])) {
                throw new \Exception("Respuesta inválida de Facebook. No se recibió el ID de sesión de subida");
            }

            $upload_session_id = $init_data['id'];
            // Remover el prefijo "upload:" si existe
            $upload_session_id = str_replace('upload:', '', $upload_session_id);

            // Paso 2: Subir el archivo
            $upload_endpoint = "https://graph.facebook.com/{$version_api}/upload:{$upload_session_id}";
            
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                throw new \Exception("No se pudo leer el archivo");
            }

            $ch_upload = curl_init($upload_endpoint);
            curl_setopt($ch_upload, CURLOPT_POST, true);
            curl_setopt($ch_upload, CURLOPT_POSTFIELDS, $file_content);
            curl_setopt($ch_upload, CURLOPT_HTTPHEADER, [
                "Authorization: OAuth $access_token",
                "file_offset: 0"
            ]);
            curl_setopt($ch_upload, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_upload, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch_upload, CURLOPT_SSL_VERIFYHOST, false);

            $upload_response = curl_exec($ch_upload);
            $upload_http_code = curl_getinfo($ch_upload, CURLINFO_HTTP_CODE);
            $upload_error = curl_error($ch_upload);
            curl_close($ch_upload);

            if ($upload_error) {
                throw new \Exception("Error de cURL al subir archivo: $upload_error");
            }

            if ($upload_http_code !== 200 && $upload_http_code !== 201) {
                // Intentar reanudar si falló
                $resume_data = $this->reanudarSubida($upload_session_id, $access_token, $version_api);
                if ($resume_data) {
                    $file_offset = $resume_data['file_offset'] ?? 0;
                    
                    // Reintentar subida desde el offset
                    $ch_resume = curl_init($upload_endpoint);
                    curl_setopt($ch_resume, CURLOPT_POST, true);
                    curl_setopt($ch_resume, CURLOPT_POSTFIELDS, substr($file_content, $file_offset));
                    curl_setopt($ch_resume, CURLOPT_HTTPHEADER, [
                        "Authorization: OAuth $access_token",
                        "file_offset: $file_offset"
                    ]);
                    curl_setopt($ch_resume, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_resume, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch_resume, CURLOPT_SSL_VERIFYHOST, false);

                    $resume_response = curl_exec($ch_resume);
                    $resume_http_code = curl_getinfo($ch_resume, CURLINFO_HTTP_CODE);
                    curl_close($ch_resume);

                    if ($resume_http_code === 200 || $resume_http_code === 201) {
                        $upload_response = $resume_response;
                        $upload_http_code = $resume_http_code;
                    }
                }
            }

            if ($upload_http_code !== 200 && $upload_http_code !== 201) {
                $upload_data = json_decode($upload_response, true);
                $error_message = $upload_data['error']['message'] ?? 'Error desconocido al subir archivo';
                throw new \Exception("Error al subir archivo: $error_message (HTTP $upload_http_code)");
            }

            $upload_data = json_decode($upload_response, true);
            
            if (!isset($upload_data['h'])) {
                throw new \Exception("Respuesta inválida de Facebook. No se recibió el handle del archivo");
            }

            $file_handle = $upload_data['h'];

            return [
                'success' => true,
                'handle' => $file_handle,
                'file_name' => $file_name,
                'file_size' => $file_size,
                'file_type' => $file_type,
                'upload_session_id' => $upload_session_id,
                'message' => 'Archivo subido exitosamente usando la API de subida reanudable.'
            ];

        } catch (\Exception $e) {
            log_message('error', "Error en subirArchivoReanudable: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function obtenerAppIdMeta($access_token)
    {
        try {
            $token_data = $this->debugToken($access_token);
            return $token_data['app_id'] ?? null;
        } catch (\Exception $e) {
            log_message('warning', "No se pudo obtener APP_ID automáticamente: " . $e->getMessage());
            return null;
        }
    }


    private function debugToken($access_token)
    {
        try {
            $endpoint = "https://graph.facebook.com/v24.0/debug_token?input_token={$access_token}&access_token={$access_token}";
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $data = json_decode($response, true);
                return $data['data'] ?? [];
            }
            
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }


    private function reanudarSubida($upload_session_id, $access_token, $version_api)
    {
        try {
            $endpoint = "https://graph.facebook.com/{$version_api}/upload:{$upload_session_id}";
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: OAuth $access_token"
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                return json_decode($response, true);
            }
            
            return null;
        } catch (\Exception $e) {
            log_message('warning', "Error al reanudar subida: " . $e->getMessage());
            return null;
        }
    }

}
