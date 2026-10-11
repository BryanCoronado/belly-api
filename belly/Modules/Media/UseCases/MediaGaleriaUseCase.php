<?php
namespace Belly\Media\UseCases;

use Belly\Media\Config\MediaConfig;
use Belly\Media\Models\GaleriaCampaniaModel;
use Belly\Media\Models\GaleriaModel;
use Config\App;


class MediaGaleriaUseCase 
{
    protected MediaConfig $config;
    protected App $appConfig;
    protected ?GaleriaModel $galeria_provider = null;
    protected ?GaleriaCampaniaModel $galeria_campania_provider = null;

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

    public function getGaleriaCampaniaProvider(): GaleriaCampaniaModel
    {
        if ($this->galeria_campania_provider !== null) {
            return $this->galeria_campania_provider;
        }

        $this->galeria_campania_provider = new GaleriaCampaniaModel();

        return $this->galeria_campania_provider;
    }

    public function subirGaleria($file, string $nombre, ?string $descripcion = null, string $tipo, ?string $tags = null, ?string $fecha_inicio = null, ?string $fecha_fin = null, ?string $bloque = null, $grupo = null, ?string $campaignId = null, ?array $campaignIds = null): array
    {
        try {
            if (!in_array($tipo, ['image', 'video'])) {
                throw new \Exception("El tipo debe ser 'image' o 'video'");
            }

            $campaignIdsNorm = $this->normalizarCampaignIdsLista($campaignIds, $campaignId);
            $campaignIdLegacy = $campaignIdsNorm[0] ?? null;

            $grupoId = null;
            if ($grupo !== null && $grupo !== '') {
                $grupoId = (int) $grupo;
                if ($grupoId <= 0) {
                    throw new \Exception("El campo grupo debe ser un ID válido");
                }
            }

            if ($bloque !== null && $bloque !== '' && !in_array($bloque, ['primero', 'segundo', 'tercero'], true)) {
                throw new \Exception("El campo bloque debe ser 'primero', 'segundo' o 'tercero'");
            }
            
            $this->validarArchivo($file, $tipo);
            
            $mimeType = $file->getMimeType();
            $extension = $file->getClientExtension() ?? $this->obtenerExtensionPorMimeType($mimeType, $tipo);
            $prefijo = $tipo === 'image' ? 'img_' : 'vid_';
            $nombreUnico = uniqid($prefijo, true) . '.' . $extension;
            $filename = "galeria/" . date('Y-m-d') . "/{$tipo}/{$nombreUnico}";
            $rutaLocalDestino = WRITEPATH . 'uploads/' . $filename;
            $directorioDestino = dirname($rutaLocalDestino);
            
            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0777, true);
            }
            
            if (!$file->move($directorioDestino, $nombreUnico)) {
                throw new \Exception("Error al guardar archivo: " . implode(', ', $file->getErrors()));
            }
            
            $publicUrl = $this->construirUrlPublica($filename);

            $galeriaId = $this->guardarMetadatos($file, $mimeType, $nombreUnico, $filename, $publicUrl, $nombre, $descripcion, $tipo, $tags, $fecha_inicio, $fecha_fin, $bloque, $grupoId, $campaignIdLegacy);

            if ($galeriaId) {
                $this->sincronizarCampaniasGaleria((int) $galeriaId, $campaignIdsNorm);
            }
            
            return [
                'success' => true,
                'id' => $galeriaId,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'bloque' => $bloque,
                'campaign_id' => $campaignIdLegacy,
                'campaign_ids' => $campaignIdsNorm,
                'id_grupo_galeria' => $grupoId,
                'grupo' => $grupoId,
                'descripcion' => $descripcion,
                'filename' => $nombreUnico,
                'path' => $filename,
                'url' => $publicUrl,
                'size' => $file->getSize(),
                'mime_type' => $mimeType,
                'original_name' => $file->getClientName(),
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'MediaGaleriaUseCase::subirGaleria - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function listarImagenes($page = 1, $perPage = 20, ?string $search = null): array
    {
        try {
            $galeria_model = $this->getGaleriaProvider();
            $galeria_model->select('galeria.*, grupo_galeria.nombre as grupo');
            $galeria_model->join('grupo_galeria', 'grupo_galeria.id = galeria.id_grupo_galeria', 'left');
            $galeria_model->where('tipo', 'image');
            
            // Agregar filtro de búsqueda si existe
            if (!empty($search)) {
                $galeria_model->groupStart();
                $galeria_model->like('nombre', $search);
                $galeria_model->orLike('tags', $search);
                $galeria_model->orLike('descripcion', $search);
                $galeria_model->orLike('original_name', $search);
                $galeria_model->groupEnd();
            }
            
            $galeria_model->orderBy('created_at', 'DESC');
            
            // Manejar paginación
            if ($perPage === 'all' || $perPage === 'ALL') {
                $resultados = $galeria_model->findAll();
                $imagenes = $this->formatearGaleria($resultados);
                $total = count($imagenes);
                
                return [
                    'success' => true,
                    'data' => $imagenes,
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
            
            $imagenes = $this->formatearGaleria($resultados);
            
            return [
                'success' => true,
                'data' => $imagenes,
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
            log_message('error', 'MediaGaleriaUseCase::listarImagenes - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function listarVideos($page = 1, $perPage = 20, ?string $search = null): array
    {
        try {
            $galeria_model = $this->getGaleriaProvider();
            $galeria_model->select('galeria.*, grupo_galeria.nombre as grupo');
            $galeria_model->join('grupo_galeria', 'grupo_galeria.id = galeria.id_grupo_galeria', 'left');
            $galeria_model->where('tipo', 'video');
            
            // Agregar filtro de búsqueda si existe
            if (!empty($search)) {
                $galeria_model->groupStart();
                $galeria_model->like('nombre', $search);
                $galeria_model->orLike('tags', $search);
                $galeria_model->orLike('descripcion', $search);
                $galeria_model->orLike('original_name', $search);
                $galeria_model->groupEnd();
            }
            
            $galeria_model->orderBy('created_at', 'DESC');
            
            // Manejar paginación
            if ($perPage === 'all' || $perPage === 'ALL') {
                $resultados = $galeria_model->findAll();
                $videos = $this->formatearGaleria($resultados);
                $total = count($videos);
                
                return [
                    'success' => true,
                    'data' => $videos,
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
            
            $videos = $this->formatearGaleria($resultados);
            
            return [
                'success' => true,
                'data' => $videos,
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
            log_message('error', 'MediaGaleriaUseCase::listarVideos - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }


    
    private function validarArchivo($file, string $tipo): void
    {
        if (!$file || !$file->isValid()) {
            throw new \Exception("Archivo no válido");
        }
        
        $mimeType = $file->getMimeType();

        if($tipo === 'image') {
            if($file->getSize() > 5 * 1024 * 1024) {
                throw new \Exception("El archivo debe ser menor a 5MB");
            }
        }
        if($tipo === 'video') {
            if($file->getSize() > 16 * 1024 * 1024) {
                throw new \Exception("El archivo debe ser menor a 16MB");
            }
        }

        
        if ($tipo === 'image') {
            if (!str_starts_with($mimeType, 'image/')) {
                throw new \Exception("El archivo debe ser una imagen");
            }
        } elseif ($tipo === 'video') {
            if (!str_starts_with($mimeType, 'video/')) {
                throw new \Exception("El archivo debe ser un video");
            }
        }
    }

    
    /**
     * Campañas Meta conocidas + opción general para el selector al subir galería.
     */
    public function listarCampaniasParaGaleria(): array
    {
        $campanias = [
            [
                'campaign_id' => null,
                'nombre_campania' => 'General (sin campaña)',
            ],
        ];

        if (function_exists('conversaciones')) {
            foreach (conversaciones()->listarCampaniasMetaFiltroContactCenter(false) as $row) {
                $campanias[] = $row;
            }
        }

        return $campanias;
    }

    private function normalizarCampaignIdGaleria(?string $campaignId): ?string
    {
        if ($campaignId === null) {
            return null;
        }

        $valor = trim((string) $campaignId);
        if ($valor === '' || strtolower($valor) === 'general' || strtolower($valor) === 'null') {
            return null;
        }

        return $valor;
    }

    /**
     * @param array<int, string>|string|null $campaignIds
     * @return list<string>
     */
    private function normalizarCampaignIdsLista($campaignIds, ?string $campaignIdFallback = null): array
    {
        $raw = [];

        if (is_string($campaignIds)) {
            $decoded = json_decode($campaignIds, true);
            if (is_array($decoded)) {
                $campaignIds = $decoded;
            } else {
                $campaignIds = array_map('trim', explode(',', $campaignIds));
            }
        }

        if (is_array($campaignIds)) {
            foreach ($campaignIds as $item) {
                if (is_array($item) && isset($item['campaign_id'])) {
                    $raw[] = (string) $item['campaign_id'];
                } else {
                    $raw[] = (string) $item;
                }
            }
        }

        if ($raw === [] && $campaignIdFallback !== null) {
            $raw[] = $campaignIdFallback;
        }

        $out = [];
        foreach ($raw as $valor) {
            $norm = $this->normalizarCampaignIdGaleria($valor);
            if ($norm !== null) {
                $out[$norm] = $norm;
            }
        }

        return array_values($out);
    }

    /**
     * @param list<string> $campaignIds
     */
    private function sincronizarCampaniasGaleria(int $idGaleria, array $campaignIds): void
    {
        $db = \Config\Database::connect();
        $db->table('galeria_campania')->where('id_galeria', $idGaleria)->delete();

        $mapNombres = $this->mapNombresCampaniaMeta($campaignIds);
        $now = date('Y-m-d H:i:s');

        foreach ($campaignIds as $campaignId) {
            $db->table('galeria_campania')->insert([
                'id_galeria' => $idGaleria,
                'campaign_id' => $campaignId,
                'nombre_campania' => $mapNombres[$campaignId] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $db->table('galeria')->where('id', $idGaleria)->update([
            'campaign_id' => $campaignIds[0] ?? null,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param list<int> $galeriaIds
     * @return array<int, list<array{campaign_id: string, nombre_campania: ?string}>>
     */
    private function obtenerCampaniasPorGaleriaIds(array $galeriaIds): array
    {
        $galeriaIds = array_values(array_unique(array_filter(array_map('intval', $galeriaIds), static fn ($id) => $id > 0)));
        if ($galeriaIds === []) {
            return [];
        }

        $rows = $this->getGaleriaCampaniaProvider()
            ->whereIn('id_galeria', $galeriaIds)
            ->orderBy('id', 'ASC')
            ->findAll();

        $out = [];
        foreach ($rows as $row) {
            $gid = (int) ($row->id_galeria ?? 0);
            $cid = trim((string) ($row->campaign_id ?? ''));
            if ($gid <= 0 || $cid === '') {
                continue;
            }
            $out[$gid][] = [
                'campaign_id' => $cid,
                'nombre_campania' => $row->nombre_campania ?? null,
            ];
        }

        return $out;
    }

    private function guardarMetadatos($file, string $mimeType, string $nombreUnico, string $filename, string $public_url, string $nombre, ?string $descripcion, string $tipo, ?string $tags = null, ?string $fecha_inicio = null, ?string $fecha_fin = null, ?string $bloque = null, ?int $grupoId = null, ?string $campaignId = null): ?int
    {
        // Convertir fechas de string a formato datetime para BD
        $fechaInicioFormateada = null;
        $fechaFinFormateada = null;
        
        if (!empty($fecha_inicio) && trim($fecha_inicio) !== '') {
            try {
                $timestamp = strtotime(trim($fecha_inicio));
                if ($timestamp !== false) {
                    $fechaInicioFormateada = date('Y-m-d H:i:s', $timestamp);
                }
            } catch (\Exception $e) {
                log_message('warning', 'Error al convertir fecha_inicio: ' . $e->getMessage());
            }
        }
        
        if (!empty($fecha_fin) && trim($fecha_fin) !== '') {
            try {
                $timestamp = strtotime(trim($fecha_fin));
                if ($timestamp !== false) {
                    $fechaFinFormateada = date('Y-m-d H:i:s', $timestamp);
                }
            } catch (\Exception $e) {
                log_message('warning', 'Error al convertir fecha_fin: ' . $e->getMessage());
            }
        }
        
        $galeria_model = $this->getGaleriaProvider();
        
        // Insertar usando query builder directamente para evitar problemas con el cast
        $galeriaData = [
            'id_grupo_galeria' => $grupoId,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo' => $tipo,
            'bloque' => $bloque,
            'campaign_id' => $campaignId,
            'filename' => $nombreUnico,
            'path' => $filename,
            'url' => $public_url,
            'original_name' => $file->getClientName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'tags' => $tags,
        ];
        
        // Agregar fechas solo si tienen valor
        if ($fechaInicioFormateada !== null) {
            $galeriaData['fecha_inicio'] = $fechaInicioFormateada;
        }
        if ($fechaFinFormateada !== null) {
            $galeriaData['fecha_fin'] = $fechaFinFormateada;
        }
        
        // Usar el query builder directamente para evitar el cast problemático
        $db = \Config\Database::connect();
        $db->table('galeria')->insert($galeriaData);
        $galeriaId = $db->insertID();
        
        if (!$galeriaId) {
            log_message('error', 'Error al guardar metadatos en BD');
            throw new \Exception('Error al guardar los metadatos');
        }
        
        return $galeriaId;
    }


  
    private function formatearGaleria(array $resultados): array
    {
        $galeriaIds = array_map(static fn ($item) => (int) ($item->id ?? 0), $resultados);
        $campaniasPorGaleria = $this->obtenerCampaniasPorGaleriaIds($galeriaIds);

        $idsCampania = [];
        foreach ($campaniasPorGaleria as $lista) {
            foreach ($lista as $c) {
                $idsCampania[] = $c['campaign_id'];
            }
        }
        foreach ($resultados as $item) {
            $cid = $item->campaign_id ?? null;
            if ($cid !== null && trim((string) $cid) !== '') {
                $idsCampania[] = trim((string) $cid);
            }
        }
        $mapNombres = $this->mapNombresCampaniaMeta($idsCampania);

        $galeria = [];
        foreach ($resultados as $item) {
            $gid = (int) ($item->id ?? 0);
            $campanias = $campaniasPorGaleria[$gid] ?? [];
            $campaignIds = array_values(array_map(static fn ($c) => $c['campaign_id'], $campanias));

            if ($campaignIds === []) {
                $cidRaw = $item->campaign_id ?? null;
                $cidLegacy = ($cidRaw !== null && trim((string) $cidRaw) !== '') ? trim((string) $cidRaw) : null;
                if ($cidLegacy !== null) {
                    $campaignIds = [$cidLegacy];
                    $campanias = [[
                        'campaign_id' => $cidLegacy,
                        'nombre_campania' => $mapNombres[$cidLegacy] ?? null,
                    ]];
                }
            }

            $cid = $campaignIds[0] ?? null;
            $nombresCampania = [];
            foreach ($campanias as $c) {
                $nombresCampania[] = $c['nombre_campania'] ?? $mapNombres[$c['campaign_id']] ?? $c['campaign_id'];
            }
            $nombreCampania = $nombresCampania !== [] ? implode(', ', $nombresCampania) : null;

            $galeria[] = [
                'id' => $item->id,
                'id_grupo_galeria' => $item->id_grupo_galeria ?? null,
                'grupo' => $item->grupo ?? null,
                'nombre' => $item->nombre,
                'descripcion' => $item->descripcion ?? null,
                'tipo' => $item->tipo,
                'bloque' => $item->bloque ?? null,
                'campaign_id' => $cid,
                'campaign_ids' => $campaignIds,
                'campanias' => $campanias,
                'nombre_campania' => $nombreCampania,
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

        return $galeria;
    }

    /**
     * @param list<string> $campaignIds valores TRIM de campaign_id
     * @return array<string, string> campaign_id => nombre_campania
     */
    private function mapNombresCampaniaMeta(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $campaignIds
        ), static fn ($id) => $id !== '')));

        if ($campaignIds === []) {
            return [];
        }

        $db = \Config\Database::connect();
        $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
        $sql = 'SELECT TRIM(ccm.campaign_id) AS campaign_id, MAX(ccm.nombre_campania) AS nombre_campania
            FROM conversacion_campania_meta ccm
            WHERE ccm.deleted_at IS NULL
              AND NULLIF(TRIM(ccm.campaign_id), \'\') IS NOT NULL
              AND TRIM(ccm.campaign_id) IN (' . $placeholders . ')
            GROUP BY TRIM(ccm.campaign_id)';

        $rows = $db->query($sql, $campaignIds)->getResult();
        $out = [];
        foreach ($rows as $row) {
            $k = trim((string) ($row->campaign_id ?? ''));
            if ($k !== '' && !empty($row->nombre_campania)) {
                $out[$k] = (string) $row->nombre_campania;
            }
        }

        return $out;
    }

 
    private function obtenerExtensionPorMimeType(string $mimeType, string $tipo): string
    {
        if ($tipo === 'image') {
            return match($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                'image/bmp' => 'bmp',
                'image/x-icon' => 'ico',
                default => 'jpg'
            };
        } else { // video
            return match($mimeType) {
                'video/mp4' => 'mp4',
                'video/mpeg' => 'mpeg',
                'video/quicktime' => 'mov',
                'video/x-msvideo' => 'avi',
                'video/webm' => 'webm',
                'video/ogg' => 'ogv',
                'video/x-ms-wmv' => 'wmv',
                'video/x-flv' => 'flv',
                default => 'mp4'
            };
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

    public function editarGaleria(int $id, array $datos): array
    {
        try {
            $galeria_model = $this->getGaleriaProvider();
            $item = $galeria_model->find($id);

            if (!$item) {
                throw new \Exception("No se encontró el elemento con ID: {$id}");
            }

            if (!in_array($item->tipo, ['image', 'video'], true)) {
                throw new \Exception("El elemento con ID {$id} no corresponde a galería");
            }

            $datosActualizar = [];

            if (isset($datos['nombre']) && trim((string) $datos['nombre']) !== '') {
                $datosActualizar['nombre'] = trim((string) $datos['nombre']);
            }

            if (array_key_exists('descripcion', $datos)) {
                $valorDescripcion = is_string($datos['descripcion']) ? trim($datos['descripcion']) : '';
                $datosActualizar['descripcion'] = $valorDescripcion !== '' ? $valorDescripcion : null;
            }

            if (array_key_exists('tags', $datos)) {
                $valorTags = is_string($datos['tags']) ? trim($datos['tags']) : '';
                $datosActualizar['tags'] = $valorTags !== '' ? $valorTags : null;
            }

            if (array_key_exists('grupo', $datos)) {
                $grupo = $datos['grupo'];
                if ($grupo === null || $grupo === '') {
                    $datosActualizar['id_grupo_galeria'] = null;
                } else {
                    $grupoId = (int) $grupo;
                    if ($grupoId <= 0) {
                        throw new \Exception('El campo grupo debe ser un ID válido');
                    }
                    $datosActualizar['id_grupo_galeria'] = $grupoId;
                }
            }

            if (array_key_exists('bloque', $datos)) {
                $bloque = is_string($datos['bloque']) ? trim($datos['bloque']) : '';
                if ($bloque === '') {
                    $datosActualizar['bloque'] = null;
                } else {
                    if (!in_array($bloque, ['primero', 'segundo', 'tercero'], true)) {
                        throw new \Exception("El campo bloque debe ser 'primero', 'segundo' o 'tercero'");
                    }
                    $datosActualizar['bloque'] = $bloque;
                }
            }

            $campaignIdsActualizar = null;
            if (array_key_exists('campaign_ids', $datos)) {
                $campaignIdsActualizar = $this->normalizarCampaignIdsLista($datos['campaign_ids']);
            } elseif (array_key_exists('campaign_id', $datos)) {
                $campaignIdsActualizar = $this->normalizarCampaignIdsLista(
                    null,
                    is_string($datos['campaign_id']) || is_numeric($datos['campaign_id'])
                        ? (string) $datos['campaign_id']
                        : null
                );
            }

            if ($campaignIdsActualizar !== null) {
                $datosActualizar['campaign_id'] = $campaignIdsActualizar[0] ?? null;
            }

            if (array_key_exists('fecha_inicio', $datos)) {
                if ($datos['fecha_inicio'] === null || trim((string) $datos['fecha_inicio']) === '') {
                    $datosActualizar['fecha_inicio'] = null;
                } else {
                    $timestamp = strtotime(trim((string) $datos['fecha_inicio']));
                    if ($timestamp !== false) {
                        $datosActualizar['fecha_inicio'] = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }

            if (array_key_exists('fecha_fin', $datos)) {
                if ($datos['fecha_fin'] === null || trim((string) $datos['fecha_fin']) === '') {
                    $datosActualizar['fecha_fin'] = null;
                } else {
                    $timestamp = strtotime(trim((string) $datos['fecha_fin']));
                    if ($timestamp !== false) {
                        $datosActualizar['fecha_fin'] = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }

            if (empty($datosActualizar) && $campaignIdsActualizar === null) {
                throw new \Exception('No se proporcionaron datos para actualizar');
            }

            if (! empty($datosActualizar)) {
                $db = \Config\Database::connect();
                $resultado = $db->table('galeria')->where('id', $id)->update($datosActualizar);

                if (! $resultado) {
                    throw new \Exception('Error al actualizar el elemento de galería');
                }
            }

            if ($campaignIdsActualizar !== null) {
                $this->sincronizarCampaniasGaleria($id, $campaignIdsActualizar);
            }

            $actualizado = $galeria_model
                ->select('galeria.*, grupo_galeria.nombre as grupo')
                ->join('grupo_galeria', 'grupo_galeria.id = galeria.id_grupo_galeria', 'left')
                ->where('galeria.id', $id)
                ->first();

            if (!$actualizado) {
                throw new \Exception('No se pudo obtener el elemento actualizado');
            }

            $formateado = $this->formatearGaleria([$actualizado]);

            return [
                'success' => true,
                'message' => 'Elemento de galería actualizado exitosamente',
                'data' => $formateado[0],
            ];
        } catch (\Exception $e) {
            log_message('error', 'MediaGaleriaUseCase::editarGaleria - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function eliminarGaleria(int $id, array $datos = []): array
    {
        try {
            $galeria_model = $this->getGaleriaProvider();
            $item = $galeria_model->find($id);
            
            if (!$item) {
                throw new \Exception("No se encontró el elemento con ID: {$id}");
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
                throw new \Exception("Error al eliminar el elemento");
            }
            
            return [
                'success' => true,
                'message' => 'Elemento eliminado exitosamente'
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'MediaGaleriaUseCase::eliminarGaleria - ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}