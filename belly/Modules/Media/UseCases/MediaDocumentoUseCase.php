<?php
namespace Belly\Media\UseCases;

use Belly\Media\Config\MediaConfig;
use Belly\Media\Models\GaleriaModel;
use Config\App;

class MediaDocumentoUseCase 
{
    protected MediaConfig $config;
    protected App $appConfig;
    protected ?GaleriaModel $galeria_provider = null;

    public function __construct(MediaConfig $config)
    {
        $this->config = $config;
        $this->appConfig = config('App');
    }

    public function getGaleriaProvider(): GaleriaModel
    {
        if ($this->galeria_provider !== null) {
            return $this->galeria_provider;
        }

        $class_name = $this->config->galeria_provider;
        $this->galeria_provider = new $class_name();

        return $this->galeria_provider;
    }

    public function subirDocumento($file, string $nombre, ?string $tags = null, ?string $fecha_inicio = null, ?string $fecha_fin = null): array
    {
        try {
            $this->validarArchivo($file);
            
            $mimeType = $file->getMimeType();
            $extension = $file->getClientExtension() ?? $this->obtenerExtensionPorMimeType($mimeType);
            $nombreUnico = uniqid('doc_', true) . '.' . $extension;
            $filename = "documents/" . date('Y-m-d') . "/{$nombreUnico}";
            $rutaLocalDestino = WRITEPATH . 'uploads/' . $filename;
            $directorioDestino = dirname($rutaLocalDestino);
            
            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0777, true);
            }
            
            if (!$file->move($directorioDestino, $nombreUnico)) {
                throw new \Exception("Error al guardar archivo: " . implode(', ', $file->getErrors()));
            }
            
            $publicUrl = $this->construirUrlPublica($filename);

            // No persistimos en `galeria`: esa tabla es para catálogo por grupo (imagen/video).
            // El documento queda solo en disco; el cliente envía el mensaje con media_url/media_path.

            return [
                'success' => true,
                'nombre' => $nombre,
                'tipo' => 'document',
                'filename' => $nombreUnico,
                'path' => $filename,
                'url' => $publicUrl,
                'media_url' => $publicUrl,
                'media_path' => $filename,
                'media_type' => 'document',
                'size' => $file->getSize(),
                'mime_type' => $mimeType,
                'original_name' => $file->getClientName(),
                'tags' => $tags,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
            ];

        } catch (\Exception $e) {
            log_message('error', 'MediaDocumentoUseCase::subirDocumento - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Solo registros históricos en `galeria` con tipo=document.
     * Las subidas nuevas por subirDocumento ya no crean filas en `galeria`.
     */
    public function listarDocumentos($page = 1, $perPage = 20, ?string $search = null): array
    {
        try {
            $galeria_model = $this->getGaleriaProvider();
            $galeria_model->where('tipo', 'document');
            
            // Agregar filtro de búsqueda si existe
            if (!empty($search)) {
                $galeria_model->groupStart();
                $galeria_model->like('nombre', $search);
                $galeria_model->orLike('tags', $search);
                $galeria_model->orLike('original_name', $search);
                $galeria_model->groupEnd();
            }
            
            $galeria_model->orderBy('created_at', 'DESC');
            
            // Manejar paginación
            if ($perPage === 'all' || $perPage === 'ALL') {
                $resultados = $galeria_model->findAll();
                $documentos = $this->formatearDocumentos($resultados);
                $total = count($documentos);
                
                return [
                    'success' => true,
                    'data' => $documentos,
                    'pagination' => [
                        'total' => $total,
                        'currentPage' => 1,
                        'from' => $total > 0 ? 1 : 0,
                        'to' => $total,
                        'hasNext' => false,
                        'hasPrev' => false,
                        'lastPage' => 1,
                        'perPage' => $total
                    ]
                ];
            }
            
            // Validar y convertir parámetros
            $page = max(1, (int) $page);
            $perPage = max(1, (int) $perPage);
            
            $resultados = $galeria_model->paginate($perPage, 'default', $page);
            $pager = $galeria_model->pager;
            
            $total = $pager->getTotal('default');
            $currentPage = $pager->getCurrentPage('default');
            $perPage = $pager->getPerPage('default');
            $lastPage = $pager->getPageCount('default');
            $from = ($total > 0) ? (($currentPage - 1) * $perPage) + 1 : 0;
            $to = ($from + count($resultados)) - 1;
            
            $documentos = $this->formatearDocumentos($resultados);
            
            return [
                'success' => true,
                'data' => $documentos,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => $currentPage,
                    'from' => $from,
                    'to' => $to,
                    'hasNext' => $currentPage < $lastPage,
                    'hasPrev' => $currentPage > 1,
                    'lastPage' => $lastPage,
                    'perPage' => $perPage
                ]
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'MediaDocumentoUseCase::listarDocumentos - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function validarArchivo($file): void
    {
        if (!$file || !$file->isValid()) {
            throw new \Exception("Archivo no válido");
        }
        
        $mimeType = $file->getMimeType();
        if (!$this->esDocumentoValido($mimeType)) {
            throw new \Exception("El archivo debe ser un documento válido (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, etc.)");
        }
    }

    private function esDocumentoValido(string $mimeType): bool
    {
        $tiposPermitidos = [
            // PDF
            'application/pdf',
            // Word
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            // Excel
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            // PowerPoint
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Texto
            'text/plain',
            'text/csv',
            'text/html',
            'text/css',
            'text/xml',
            // Otros
            'application/json',
            'application/xml',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];
        
        return in_array($mimeType, $tiposPermitidos);
    }


    private function formatearDocumentos(array $resultados): array
    {
        $documentos = [];
        foreach ($resultados as $item) {
            $documentos[] = [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'tipo' => $item->tipo,
                'filename' => $item->filename,
                'path' => $item->path,
                'url' => $this->construirUrlPublica($item->path, $item->url ?? null),
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'tags' => $item->tags,
                'fecha_inicio' => $item->fecha_inicio,
                'fecha_fin' => $item->fecha_fin,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        }
        return $documentos;
    }

    private function obtenerExtensionPorMimeType(string $mimeType): string
    {
        return match($mimeType) {
            // PDF
            'application/pdf' => 'pdf',
            // Word
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            // Excel
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            // PowerPoint
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            // Texto
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'text/html' => 'html',
            'text/css' => 'css',
            'text/xml' => 'xml',
            // Otros
            'application/json' => 'json',
            'application/xml' => 'xml',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-zip-compressed' => 'zip',
            'application/octet-stream' => 'bin',
            default => 'bin'
        };
    }

    public function editarDocumento(int $id, array $datos, $nuevoArchivo = null): array
    {
        try {
            $galeria_model = $this->getGaleriaProvider();
            $item = $galeria_model->find($id);
            
            if (!$item) {
                throw new \Exception("No se encontró el documento con ID: {$id}");
            }
            
            if ($item->tipo !== 'document') {
                throw new \Exception("El elemento con ID {$id} no es un documento");
            }
            
            $actualizarArchivo = false;
            $nuevoFilename = $item->path;
            $nuevoUrl = $item->url;
            $nuevoNombreUnico = $item->filename;
            $nuevoMimeType = $item->mime_type;
            $nuevoSize = $item->size;
            $nuevoOriginalName = $item->original_name;
            
            if ($nuevoArchivo && $nuevoArchivo->isValid()) {
                $this->validarArchivo($nuevoArchivo);
                $nuevoMimeType = $nuevoArchivo->getMimeType();
                $extension = $nuevoArchivo->getClientExtension() ?? $this->obtenerExtensionPorMimeType($nuevoMimeType);
                
                $nuevoNombreUnico = uniqid('doc_', true) . '.' . $extension;
                $nuevoFilename = "documents/" . date('Y-m-d') . "/{$nuevoNombreUnico}";
                $rutaLocalDestino = WRITEPATH . 'uploads/' . $nuevoFilename;
                $directorioDestino = dirname($rutaLocalDestino);
                
                if (!is_dir($directorioDestino)) {
                    mkdir($directorioDestino, 0777, true);
                }
                
                if (!$nuevoArchivo->move($directorioDestino, $nuevoNombreUnico)) {
                    throw new \Exception("Error al guardar archivo: " . implode(', ', $nuevoArchivo->getErrors()));
                }
                
                $nuevoUrl = $this->construirUrlPublica($nuevoFilename);
                $nuevoSize = $nuevoArchivo->getSize();
                $nuevoOriginalName = $nuevoArchivo->getClientName();
                
                $rutaAnterior = WRITEPATH . 'uploads/' . $item->path;
                if (file_exists($rutaAnterior)) {
                    @unlink($rutaAnterior);
                }
                
                $actualizarArchivo = true;
            }
            
            $datosActualizar = [];
            
            if (isset($datos['nombre']) && $datos['nombre'] !== null && trim($datos['nombre']) !== '') {
                $datosActualizar['nombre'] = trim($datos['nombre']);
            }
            
            if (isset($datos['tags'])) {
                $datosActualizar['tags'] = ($datos['tags'] !== null && trim($datos['tags']) !== '') ? trim($datos['tags']) : null;
            }
            if (isset($datos['fecha_inicio'])) {
                if ($datos['fecha_inicio'] === null || trim($datos['fecha_inicio']) === '') {
                    $datosActualizar['fecha_inicio'] = null;
                } else {
                    $timestamp = strtotime(trim($datos['fecha_inicio']));
                    if ($timestamp !== false) {
                        $datosActualizar['fecha_inicio'] = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }
            
            if (isset($datos['fecha_fin'])) {
                if ($datos['fecha_fin'] === null || trim($datos['fecha_fin']) === '') {
                    $datosActualizar['fecha_fin'] = null;
                } else {
                    $timestamp = strtotime(trim($datos['fecha_fin']));
                    if ($timestamp !== false) {
                        $datosActualizar['fecha_fin'] = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }
            
            if ($actualizarArchivo) {
                $datosActualizar['filename'] = $nuevoNombreUnico;
                $datosActualizar['path'] = $nuevoFilename;
                $datosActualizar['url'] = $nuevoUrl;
                $datosActualizar['mime_type'] = $nuevoMimeType;
                $datosActualizar['size'] = $nuevoSize;
                $datosActualizar['original_name'] = $nuevoOriginalName;
            }
            
            if (empty($datosActualizar)) {
                throw new \Exception("No se proporcionaron datos para actualizar");
            }
            
            $db = \Config\Database::connect();
            $resultado = $db->table('galeria')->where('id', $id)->update($datosActualizar);
            
            if (!$resultado) {
                throw new \Exception("Error al actualizar el documento");
            }
            
            $itemActualizado = $galeria_model->find($id);
            $documentoFormateado = $this->formatearDocumentos([$itemActualizado]);
            
            return [
                'success' => true,
                'message' => 'Documento actualizado exitosamente',
                'data' => $documentoFormateado[0]
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'MediaDocumentoUseCase::editarDocumento - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function eliminarDocumento(int $id, array $datos = []): array
    {
        try {
            $galeria_model = $this->getGaleriaProvider();
            $item = $galeria_model->find($id);
            
            if (!$item) {
                throw new \Exception("No se encontró el documento con ID: {$id}");
            }
            
            if ($item->tipo !== 'document') {
                throw new \Exception("El elemento con ID {$id} no es un documento");
            }
            
            // Eliminar archivo físico si se solicita (por defecto true)
            $eliminarArchivo = isset($datos['eliminar_archivo']) ? (bool) $datos['eliminar_archivo'] : true;
            if ($eliminarArchivo) {
                $rutaArchivo = WRITEPATH . 'uploads/' . $item->path;
                if (file_exists($rutaArchivo)) {
                    @unlink($rutaArchivo);
                }
            }
            
            // Eliminar registro (soft delete)
            if (!$galeria_model->delete($id)) {
                throw new \Exception("Error al eliminar el documento");
            }
            
            return [
                'success' => true,
                'message' => 'Documento eliminado exitosamente'
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'MediaDocumentoUseCase::eliminarDocumento - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function construirUrlPublica(string $path, ?string $fallbackUrl = null): string
    {
        $baseUploadsUrl = trim((string) env('MEDIA_UPLOADS_BASE_URL', ''));
        if ($baseUploadsUrl !== '') {
            return rtrim($baseUploadsUrl, '/') . '/uploads/' . ltrim($path, '/');
        }

        if (!empty($fallbackUrl)) {
            return $fallbackUrl;
        }

        return rtrim($this->appConfig->baseURL, '/') . '/uploads/' . ltrim($path, '/');
    }
}

