<?php



namespace Belly\Mensajes\UseCases;



use Belly\Mensajes\Config\MensajesConfig;

use Belly\Mensajes\Entities\PlantillaRapidaEntity;

use Belly\Mensajes\Models\PlantillaRapidaModel;

use CodeIgniter\HTTP\Files\UploadedFile;



class PlantillaRapidaUseCase

{

    protected MensajesConfig $config;



    protected ?PlantillaRapidaModel $plantilla_rapida_provider = null;



    public function __construct(MensajesConfig $config)

    {

        $this->config = $config;

    }



    public function getPlantillaRapidaProvider(): PlantillaRapidaModel

    {

        if ($this->plantilla_rapida_provider !== null) {

            return $this->plantilla_rapida_provider;

        }



        $class_name = $this->config->plantilla_rapida_provider;

        $this->plantilla_rapida_provider = new $class_name();



        return $this->plantilla_rapida_provider;

    }



    public function getById(int $id)

    {

        $plantilla_rapida = $this->getPlantillaRapidaProvider()->find($id);



        if (isset($plantilla_rapida) && ! empty($plantilla_rapida)) {

            return $plantilla_rapida;

        }



        return null;

    }



    /**

     * @param array<string, mixed> $datos

     */

    public function crearPlantillaRapida(array $datos, ?UploadedFile $imagen = null): array

    {

        $nombre = trim((string) ($datos['nombre'] ?? ''));

        $contenido = trim((string) ($datos['contenido'] ?? ''));



        if ($nombre === '') {

            throw new \InvalidArgumentException('El nombre es obligatorio.');

        }



        $tieneImagen = $this->archivoImagenValido($imagen);

        if ($contenido === '' && ! $tieneImagen) {

            throw new \InvalidArgumentException('Ingrese el texto del mensaje o adjunte una imagen.');

        }



        $usuario = auth()->getUsuario();

        if (! $usuario || empty($usuario->id)) {

            throw new \Exception('Usuario no autenticado.');

        }



        $idUsuario = (int) $usuario->id;

        $model = $this->getPlantillaRapidaProvider();



        $existente = $model->where('nombre', $nombre)->where('id_usuario', $idUsuario)->first();

        if ($existente) {

            return [

                'id'         => $existente->id,

                'nombre'     => $existente->nombre,

                'contenido'  => $existente->contenido,

                'imagen_url' => $existente->imagen_url ?? null,

                'mensaje'    => 'Ya tienes un mensaje rápido con ese nombre.',

            ];

        }



        $imagenDatos = $tieneImagen ? $this->procesarImagenSubida($imagen, $idUsuario) : null;



        $entity = new PlantillaRapidaEntity();

        $entity->nombre = $nombre;

        $entity->contenido = $contenido;

        $entity->id_usuario = $idUsuario;

        $entity->imagen_path = $imagenDatos['media_path'] ?? null;

        $entity->imagen_url = $imagenDatos['media_url'] ?? null;



        if (! $model->save($entity)) {

            if ($imagenDatos !== null) {

                $this->eliminarImagenDisco($imagenDatos['media_path'] ?? null);

            }

            throw new \Exception(json_encode($model->errors()));

        }



        $entity->id = $model->getInsertID();



        return $this->respuestaPlantilla($entity, 'Plantilla rápida creada correctamente.');

    }



    public function listarPlantillasRapidasPorUsuario($page = 1, $perPage = 10, $search = null)

    {

        $usuario = auth()->getUsuario();

        if (! $usuario || empty($usuario->id)) {

            throw new \Exception('Usuario no autenticado.');

        }

        $model = $this->getPlantillaRapidaProvider();

        $model->where('id_usuario', (int) $usuario->id);

        if (! empty($search)) {

            $model->groupStart()

                ->like('nombre', $search)

                ->orLike('contenido', $search)

            ->groupEnd();

        }

        $model->orderBy('id', 'DESC');



        return $this->paginar($model, $page, $perPage);

    }



    public function listarTodasPlantillasRapidas($page = 1, $perPage = 10, $search = null)

    {

        $usuario = auth()->getUsuario();

        if (! $usuario || empty($usuario->id)) {

            throw new \Exception('Usuario no autenticado.');

        }

        if ((int) ($usuario->sudo ?? 0) !== 1) {

            throw new \Exception('No tienes permisos para listar todas las plantillas rápidas.');

        }



        $model = $this->getPlantillaRapidaProvider();

        if (! empty($search)) {

            $model->groupStart()

                ->like('nombre', $search)

                ->orLike('contenido', $search)

            ->groupEnd();

        }

        $model->orderBy('id', 'DESC');



        return $this->paginar($model, $page, $perPage);

    }



    /**

     * @param array<string, mixed> $datos

     */

    public function editarPlantillaRapida(array $datos, int $id, ?UploadedFile $imagen = null): array

    {

        $model = $this->getPlantillaRapidaProvider();

        $row = $model->find($id);

        if (! $row) {

            throw new \Exception('Plantilla rápida no encontrada.');

        }



        $this->assertPropietario($row);



        $nombre = trim((string) ($datos['nombre'] ?? $row->nombre));

        $contenido = array_key_exists('contenido', $datos)

            ? trim((string) $datos['contenido'])

            : (string) $row->contenido;



        $eliminarImagen = $this->esTruthy($datos['eliminar_imagen'] ?? false);

        $tieneImagenNueva = $this->archivoImagenValido($imagen);



        $imagenPath = $row->imagen_path ?? null;

        $imagenUrl = $row->imagen_url ?? null;



        if ($eliminarImagen && ! $tieneImagenNueva) {

            $this->eliminarImagenDisco($imagenPath);

            $imagenPath = null;

            $imagenUrl = null;

        }



        if ($tieneImagenNueva) {

            $nueva = $this->procesarImagenSubida($imagen, (int) $row->id_usuario);

            $this->eliminarImagenDisco($imagenPath);

            $imagenPath = $nueva['media_path'];

            $imagenUrl = $nueva['media_url'];

        }



        if ($contenido === '' && ($imagenUrl === null || $imagenUrl === '')) {

            throw new \InvalidArgumentException('Debe haber texto o una imagen en el mensaje rápido.');

        }



        $payload = [
            'nombre'      => $nombre,
            'contenido'   => $contenido,
            'imagen_path' => $imagenPath,
            'imagen_url'  => $imagenUrl,
        ];

        $model->set('updateOnlyChanged', false);
        $ok = $model->update($id, $payload);
        $model->set('updateOnlyChanged', true);

        if (! $ok) {
            throw new \Exception(json_encode($model->errors()));
        }

        $entity = new PlantillaRapidaEntity();
        $entity->id = $id;
        $entity->nombre = $nombre;
        $entity->contenido = $contenido;
        $entity->id_usuario = (int) $row->id_usuario;
        $entity->imagen_path = $imagenPath;
        $entity->imagen_url = $imagenUrl;

        return $this->respuestaPlantilla($entity, 'Plantilla rápida actualizada correctamente.');

    }



    public function verPlantillaRapida(int $id)

    {

        $row = $this->getPlantillaRapidaProvider()->find($id);

        if (! $row) {

            throw new \Exception('Plantilla rápida no encontrada.');

        }



        return $row;

    }



    public function eliminarPlantillaRapida(int $id): array

    {

        $model = $this->getPlantillaRapidaProvider();

        $row = $model->find($id);

        if (! $row) {

            throw new \Exception('Plantilla rápida no encontrada.');

        }



        $this->assertPropietario($row);



        if (! $model->delete($id)) {

            throw new \Exception(json_encode($model->errors()));

        }



        $this->eliminarImagenDisco($row->imagen_path ?? null);



        return [

            'id'      => $id,

            'mensaje' => 'Plantilla rápida eliminada correctamente.',

        ];

    }



    private function paginar(PlantillaRapidaModel $model, $page, $perPage): array

    {

        if ($perPage === 'all') {

            $rows = $model->findAll();

            $total = count($rows);



            return [

                'data'       => $rows,

                'pagination' => [

                    'total'       => $total,

                    'currentPage' => 1,

                    'from'        => $total > 0 ? 1 : 0,

                    'to'          => $total,

                    'hasNext'     => false,

                    'hasPrev'     => false,

                ],

            ];

        }



        $result = $model->paginate($perPage, 'default', $page);

        $pager = $model->pager;

        $total = $pager->getTotal('default');

        $currentPage = $pager->getCurrentPage('default');

        $perPageResult = $pager->getPerPage('default');

        $lastPage = $pager->getPageCount('default');

        $from = $total > 0 ? (($currentPage - 1) * $perPageResult) + 1 : 0;

        $to = $from + count($result) - 1;



        return [

            'data'       => $result,

            'pagination' => [

                'total'       => $total,

                'currentPage' => $currentPage,

                'from'        => $from,

                'to'          => $to,

                'hasNext'     => $currentPage < $lastPage,

                'hasPrev'     => $currentPage > 1,

            ],

        ];

    }



    private function procesarImagenSubida(UploadedFile $file, int $idUsuario): array

    {

        $resultado = media()->subirImagenPlantillaRapida($file, $idUsuario);

        if (empty($resultado['success'])) {

            throw new \Exception($resultado['error'] ?? 'No se pudo subir la imagen.');

        }



        return $resultado;

    }



    private function archivoImagenValido(?UploadedFile $file): bool

    {

        return $file !== null && $file->isValid() && ! $file->hasMoved();

    }



    private function eliminarImagenDisco(?string $path): void

    {

        if ($path === null || trim($path) === '') {

            return;

        }



        $rel = ltrim($path, '/');

        $full = WRITEPATH . $rel;

        if (is_file($full)) {

            @unlink($full);

        }

    }



    private function assertPropietario(PlantillaRapidaEntity $row): void

    {

        $usuario = auth()->getUsuario();

        if (! $usuario || empty($usuario->id)) {

            throw new \Exception('Usuario no autenticado.');

        }

        if ((int) ($usuario->sudo ?? 0) === 1) {

            return;

        }

        if ((int) $row->id_usuario !== (int) $usuario->id) {

            throw new \Exception('No tienes permiso para modificar este mensaje rápido.');

        }

    }



    private function esTruthy(mixed $value): bool

    {

        if (is_bool($value)) {

            return $value;

        }



        $v = strtolower(trim((string) $value));



        return in_array($v, ['1', 'true', 'yes', 'si', 'sí', 'on'], true);

    }



    private function respuestaPlantilla(PlantillaRapidaEntity $entity, string $mensaje): array

    {

        return [

            'id'          => $entity->id,

            'nombre'      => $entity->nombre,

            'contenido'   => $entity->contenido,

            'imagen_path' => $entity->imagen_path ?? null,

            'imagen_url'  => $entity->imagen_url ?? null,

            'id_usuario'  => $entity->id_usuario,

            'mensaje'     => $mensaje,

        ];

    }

}


