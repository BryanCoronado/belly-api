<?php

namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Entities\HorarioGrupoUsuarioRolEntity;
use Belly\Usuarios\Models\HorarioGrupoUsuarioRolModel;

class HorarioGrupoUsuarioRolUseCase
{
    protected ?HorarioGrupoUsuarioRolModel $horario_provider = null;
    protected UsuarioConfig $config;

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
    }

    public function getHorarioProvider(): HorarioGrupoUsuarioRolModel
    {
        if ($this->horario_provider !== null) {
            return $this->horario_provider;
        }

        $className = $this->config->horario_grupo_usuario_rol_provider;
        $this->horario_provider = new $className();

        return $this->horario_provider;
    }

    public function listar(?int $id_grupo_usuario_rol = null, int $page = 1, $perPage = 10): array
    {
        $model = $this->getHorarioProvider();
        $model->select('horario_grupo_usuario_rol.*, grupo_usuario_rol.rol')
            ->join('grupo_usuario_rol', 'grupo_usuario_rol.id = horario_grupo_usuario_rol.id_grupo_usuario_rol', 'inner')
            ->orderBy('grupo_usuario_rol.rol', 'ASC')
            ->orderBy('FIELD(horario_grupo_usuario_rol.dia_semana, "lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo")', '', false);

        if ($id_grupo_usuario_rol !== null) {
            $model->where('horario_grupo_usuario_rol.id_grupo_usuario_rol', $id_grupo_usuario_rol);
        }

        if ($perPage === 'all') {
            $data = $model->findAll();
            $total = count($data);
            return [
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ],
            ];
        }

        $result = $model->paginate($perPage, 'default', $page);
        $pager = $model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPageNum = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = ($total > 0) ? (($currentPage - 1) * $perPageNum) + 1 : 0;
        $to = ($from + count($result)) - 1;

        return [
            'data' => $result,
            'pagination' => [
                'total' => $total,
                'currentPage' => $currentPage,
                'from' => $from,
                'to' => $to,
                'hasNext' => $currentPage < $lastPage,
                'hasPrev' => $currentPage > 1,
            ],
        ];
    }

    public function listarPorRol(int $id_grupo_usuario_rol): array
    {
        $model = $this->getHorarioProvider();
        return $model
            ->where('id_grupo_usuario_rol', $id_grupo_usuario_rol)
            ->orderBy('FIELD(dia_semana, "lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo")', '', false)
            ->findAll();
    }

    /**
     * Lista roles que aún no tienen ningún horario asignado.
     */
    public function listarRolesSinHorario(): array
    {
        // Solo devolver estos roles (sin validar si ya tienen o no horario)
        $rolesPermitidos = [7, 10, 12, 13, 14];

        $grupoRolModel = model('GrupoUsuarioRolModel');
        $roles = $grupoRolModel
            ->select('id, rol, alias, descripcion')
            ->whereIn('id', $rolesPermitidos)
            ->orderBy('rol', 'ASC')
            ->findAll();

        return array_map(fn($r) => (array) $r->toArray(), $roles);
    }

    public function obtener(int $id): ?object
    {
        $model = $this->getHorarioProvider();
        $entity = $model->find($id);
        if (!$entity) {
            throw new \Exception('Horario no encontrado.');
        }
        return $entity;
    }

    public function crear(array $datos): object
    {
        $model = $this->getHorarioProvider();
        $idRol = (int) ($datos['id_grupo_usuario_rol'] ?? 0);
        $diaSemana = $datos['dia_semana'] ?? '';

        $this->validarDatosCrear($datos);

        $entity = new HorarioGrupoUsuarioRolEntity();
        $entity->id_grupo_usuario_rol = $idRol;
        $entity->dia_semana = $diaSemana;
        $entity->hora_inicio = $this->normalizarHora($datos['hora_inicio']);
        $entity->hora_fin = $this->normalizarHora($datos['hora_fin']);
        $entity->activo = $datos['activo'] ?? 1;

        $this->validarRangoHoras($entity->hora_inicio, $entity->hora_fin);

        // Permitir múltiples rangos por rol/día mientras NO se crucen.
        // Solapamiento: new_inicio < old_fin AND new_fin > old_inicio
        $hayCruce = $model
            ->where('id_grupo_usuario_rol', $entity->id_grupo_usuario_rol)
            ->where('dia_semana', $entity->dia_semana)
            ->where('activo', 1)
            ->where('hora_inicio <', $entity->hora_fin)
            ->where('hora_fin >', $entity->hora_inicio)
            ->first();

        if ($hayCruce) {
            throw new \Exception("El rango horario se cruza con otro rango existente para este rol y día ({$diaSemana}). Se permiten rangos contiguos.");
        }

        if (!$model->save($entity)) {
            throw new \Exception(json_encode($model->errors()));
        }

        $entity->id = $model->insertID;
        return $entity;
    }

    public function editar(array $datos, int $id): object
    {
        $model = $this->getHorarioProvider();
        $existente = $model->find($id);

        if (!$existente) {
            throw new \Exception('Horario no encontrado.');
        }

        $entity = new HorarioGrupoUsuarioRolEntity();
        $entity->id = $id;
        $entity->id_grupo_usuario_rol = $datos['id_grupo_usuario_rol'] ?? $existente->id_grupo_usuario_rol;
        $entity->dia_semana = $datos['dia_semana'] ?? $existente->dia_semana;
        $entity->hora_inicio = isset($datos['hora_inicio']) ? $this->normalizarHora($datos['hora_inicio']) : $existente->hora_inicio;
        $entity->hora_fin = isset($datos['hora_fin']) ? $this->normalizarHora($datos['hora_fin']) : $existente->hora_fin;
        $entity->activo = $datos['activo'] ?? $existente->activo;

        $this->validarRangoHoras($entity->hora_inicio, $entity->hora_fin);

        // Validar cruces contra otros registros activos (excluyendo el mismo id).
        $hayCruce = $model
            ->where('id_grupo_usuario_rol', $entity->id_grupo_usuario_rol)
            ->where('dia_semana', $entity->dia_semana)
            ->where('activo', 1)
            ->where('id !=', $id)
            ->where('hora_inicio <', $entity->hora_fin)
            ->where('hora_fin >', $entity->hora_inicio)
            ->first();

        if ($hayCruce) {
            throw new \Exception("El rango horario se cruza con otro rango existente para este rol y día ({$entity->dia_semana}). Se permiten rangos contiguos.");
        }

        if (!$model->save($entity)) {
            throw new \Exception(json_encode($model->errors()));
        }

        return $entity;
    }

    public function eliminar(int $id): string
    {
        $model = $this->getHorarioProvider();
        $existente = $model->find($id);

        if (!$existente) {
            throw new \Exception('Horario no encontrado.');
        }

        if (!$model->delete($id)) {
            throw new \Exception('No se pudo eliminar el horario.');
        }

        return 'Horario eliminado correctamente.';
    }

    private function normalizarHora(string $hora): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $hora, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return $hora;
    }

    private function validarDatosCrear(array $datos): void
    {
        $requeridos = ['id_grupo_usuario_rol', 'dia_semana', 'hora_inicio', 'hora_fin'];
        foreach ($requeridos as $campo) {
            if (empty($datos[$campo])) {
                throw new \Exception("El campo {$campo} es requerido.");
            }
        }

        $diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        if (!in_array(strtolower($datos['dia_semana']), $diasValidos, true)) {
            throw new \Exception('El día debe ser uno de: ' . implode(', ', $diasValidos));
        }

        if ((int) $datos['id_grupo_usuario_rol'] < 1) {
            throw new \Exception('El id_grupo_usuario_rol debe ser un número válido mayor a 0.');
        }
    }

    private function validarRangoHoras(string $horaInicio, string $horaFin): void
    {
        $inicio = strtotime($horaInicio);
        $fin = strtotime($horaFin);

        if ($inicio === false || $fin === false) {
            throw new \Exception('Las horas deben tener formato válido (HH:mm o HH:mm:ss).');
        }

        if ($fin <= $inicio) {
            throw new \Exception('La hora de fin debe ser mayor que la hora de inicio.');
        }
    }
}
