<?php

namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;

class ConversacionMetricaUseCase
{
    protected ConversacionConfig $config;

    // Estados de conversación
    const ESTADO_EN_ATENCION = 1;
    const ESTADO_FINALIZADO  = 2;
    const ESTADO_SIN_ABRIR   = 3;

    // Roles
    const ROL_PERSONAL_CC          = 7;
    const ROL_JEFE_CC              = 8;
    const ROL_JEFE_CS              = 9;
    const ROL_PERSONAL_CS          = 10;
    const ROL_PERSONAL_MKT         = 11;
    const ROL_JEFE_CC_POSTVENTA    = 12;

    // Tipos de conversación
    const TIPO_CONTACT_CENTER   = 'contact_center';
    const TIPO_CUSTOMER_SUCCESS = 'customer_success';

    // Roles de jefes con su tipo de conversación asociado
    const ROLES_JEFE_TIPO = [
        self::ROL_JEFE_CC           => self::TIPO_CONTACT_CENTER,
        self::ROL_JEFE_CS           => self::TIPO_CUSTOMER_SUCCESS,
        self::ROL_JEFE_CC_POSTVENTA => self::TIPO_CONTACT_CENTER,
    ];

    // Roles individuales (personal)
    const ROLES_PERSONAL = [
        self::ROL_PERSONAL_CC,
        self::ROL_PERSONAL_CS,
    ];

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Punto de entrada principal: construye el dashboard según el rol del usuario logueado
     */
    public function conversacionMetrica(): array
    {
        $usuario = auth()->getUsuario();
        if (!$usuario) {
            throw new \Exception('No se pudo obtener el usuario autenticado');
        }

        $id_usuario = $usuario->id;
        $roles      = $this->obtenerRolesUsuario($id_usuario);
        $ids_rol    = array_map(fn($r) => (int) $r->id_rol, $roles);
        $hoy        = $this->getRangoHoy();

        $data = [
            'id_usuario' => $id_usuario,
            'roles'      => array_map(fn($r) => ['id' => (int) $r->id_rol, 'nombre' => $r->nombre_rol], $roles),
            'fecha'      => date('Y-m-d'),
        ];

        // ─── Determinar tipo(s) de conversación que le corresponden ───
        $tipos = [];
        foreach ($ids_rol as $id_rol) {
            if (isset(self::ROLES_JEFE_TIPO[$id_rol])) {
                $tipos[] = self::ROLES_JEFE_TIPO[$id_rol];
            }
        }
        $tipos = array_unique($tipos);

        // Si es jefe, generar dashboard de equipo por cada tipo
        foreach ($tipos as $tipo) {
            $data['dashboard_' . $tipo] = $this->buildDashboardEquipo($tipo, $hoy);
        }

        // Si es personal, generar métricas individuales
        $esPersonal = !empty(array_intersect($ids_rol, self::ROLES_PERSONAL));
        if ($esPersonal) {
            $data['mis_metricas'] = $this->buildDashboardIndividual($id_usuario, $hoy);
        }

        return $data;
    }

    //  DASHBOARD DE EQUIPO (Jefe CC / Jefe CS / Jefe CC Postventa)

    private function buildDashboardEquipo(string $tipo, array $hoy): array
    {
        $db = \Config\Database::connect();

        $resumen = $this->resumenGeneral($tipo, $hoy);
        $rendimiento = $this->rendimientoEquipo($tipo, $hoy);
        $carga = $this->cargaTrabajoPorAsesor($tipo, $hoy);
        $distribucion_estado = $this->distribucionPorEstado($tipo, $hoy);
        $distribucion_motivo = $this->distribucionPorMotivo($tipo, $hoy);
        $conversaciones_app = $this->conversacionesPorApp($tipo, $hoy);
        $tendencia = $this->tendenciaConversaciones($tipo);

        $resultado = [
            'resumen_general' => $resumen,
        ];

        // Solo incluir campos que tienen datos (no vacíos)
        if (!empty($rendimiento)) {
            $resultado['rendimiento_equipo'] = $rendimiento;
        }
        if (!empty($carga)) {
            $resultado['carga_trabajo_asesor'] = $carga;
        }
        if (!empty($distribucion_estado)) {
            $resultado['distribucion_estado'] = $distribucion_estado;
        }
        if (!empty($distribucion_motivo)) {
            $resultado['distribucion_motivo'] = $distribucion_motivo;
        }
        if (!empty($conversaciones_app)) {
            $resultado['conversaciones_por_app'] = $conversaciones_app;
        }
        if (!empty($tendencia)) {
            $resultado['tendencia_conversaciones'] = $tendencia;
        }

        return $resultado;
    }

    /**
     * Resumen General de Conversaciones:
     *  - total hoy, activas, cerradas, sin abrir, tiempo respuesta promedio
     */
    private function resumenGeneral(string $tipo, array $hoy): array
    {
        $activas    = $this->contarPorTipoEstado($tipo, self::ESTADO_EN_ATENCION, $hoy);
        $cerradas   = $this->contarPorTipoEstado($tipo, self::ESTADO_FINALIZADO, $hoy);
        $sin_abrir  = $this->contarPorTipoEstado($tipo, self::ESTADO_SIN_ABRIR, $hoy);
        $total      = $activas + $cerradas + $sin_abrir;

        return [
            'total_conversaciones_hoy'  => $total,
            'conversaciones_activas'    => $activas,
            'conversaciones_cerradas'   => $cerradas,
            'conversaciones_sin_abrir'  => $sin_abrir,
        ];
    }

    /**
     * Rendimiento del Equipo: métricas agregadas por asesor
     */
    private function rendimientoEquipo(string $tipo, array $hoy): array
    {
        $db = \Config\Database::connect();

        $resultado = $db->table('conversacion c')
            ->select('
                c.id_usuario,
                CONCAT(up.nombres, " ", up.apellidos) as asesor,
                COUNT(c.id) as total,
                SUM(CASE WHEN c.id_estado = ' . self::ESTADO_EN_ATENCION . ' THEN 1 ELSE 0 END) as activas,
                SUM(CASE WHEN c.id_estado = ' . self::ESTADO_FINALIZADO . ' THEN 1 ELSE 0 END) as finalizadas,
                SUM(CASE WHEN c.id_estado = ' . self::ESTADO_SIN_ABRIR . ' THEN 1 ELSE 0 END) as sin_abrir
            ')
            ->join('usuario_perfil up', 'up.id_usuario = c.id_usuario', 'left')
            ->where('c.tipo', $tipo)
            ->where('c.created_at >=', $hoy['inicio'])
            ->where('c.created_at <=', $hoy['fin'])
            ->where('c.deleted_at', null)
            ->groupBy('c.id_usuario')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResult();

        return array_map(fn($r) => [
            'id_usuario'  => (int) $r->id_usuario,
            'asesor'      => $r->asesor ?? 'Sin nombre',
            'total'       => (int) $r->total,
            'activas'     => (int) $r->activas,
            'finalizadas' => (int) $r->finalizadas,
            'sin_abrir'   => (int) $r->sin_abrir,
        ], $resultado);
    }

    /**
     * Carga de Trabajo por Asesor: conversaciones activas + sin abrir de cada asesor
     */
    private function cargaTrabajoPorAsesor(string $tipo, array $hoy): array
    {
        $db = \Config\Database::connect();

        $resultado = $db->table('conversacion c')
            ->select('
                c.id_usuario,
                CONCAT(up.nombres, " ", up.apellidos) as asesor,
                COUNT(c.id) as carga_actual
            ')
            ->join('usuario_perfil up', 'up.id_usuario = c.id_usuario', 'left')
            ->where('c.tipo', $tipo)
            ->whereIn('c.id_estado', [self::ESTADO_EN_ATENCION, self::ESTADO_SIN_ABRIR])
            ->where('c.created_at >=', $hoy['inicio'])
            ->where('c.created_at <=', $hoy['fin'])
            ->where('c.deleted_at', null)
            ->groupBy('c.id_usuario')
            ->orderBy('carga_actual', 'DESC')
            ->get()
            ->getResult();

        return array_map(fn($r) => [
            'id_usuario'   => (int) $r->id_usuario,
            'asesor'       => $r->asesor ?? 'Sin nombre',
            'carga_actual' => (int) $r->carga_actual,
        ], $resultado);
    }

    /**
     * Distribución por Estado
     */
    private function distribucionPorEstado(string $tipo, array $hoy): array
    {
        $db = \Config\Database::connect();

        $resultado = $db->table('conversacion c')
            ->select('ec.id as id_estado, ec.nombre as estado, COUNT(c.id) as total')
            ->join('estado_conversacion ec', 'ec.id = c.id_estado')
            ->where('c.tipo', $tipo)
            ->where('c.created_at >=', $hoy['inicio'])
            ->where('c.created_at <=', $hoy['fin'])
            ->where('c.deleted_at', null)
            ->groupBy('c.id_estado')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResult();

        return array_map(fn($r) => [
            'id_estado' => (int) $r->id_estado,
            'estado'    => $r->estado,
            'total'     => (int) $r->total,
        ], $resultado);
    }

    /**
     * Distribución por Motivo
     */
    private function distribucionPorMotivo(string $tipo, array $hoy): array
    {
        $db = \Config\Database::connect();

        $resultado = $db->table('conversacion c')
            ->select('mc.id as id_motivo, mc.nombre as motivo, COUNT(c.id) as total')
            ->join('motivo_conversacion mc', 'mc.id = c.id_motivo', 'left')
            ->where('c.tipo', $tipo)
            ->where('c.created_at >=', $hoy['inicio'])
            ->where('c.created_at <=', $hoy['fin'])
            ->where('c.deleted_at', null)
            ->groupBy('c.id_motivo')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResult();

        return array_map(fn($r) => [
            'id_motivo' => $r->id_motivo ? (int) $r->id_motivo : null,
            'motivo'    => $r->motivo ?? 'Sin motivo',
            'total'     => (int) $r->total,
        ], $resultado);
    }

    /**
     * Conversaciones por Canal (App / línea de WhatsApp)
     */
    private function conversacionesPorApp(string $tipo, array $hoy): array
    {
        $db = \Config\Database::connect();

        $resultado = $db->table('conversacion c')
            ->select('a.id as id_app, a.nombre as app, a.telefono, COUNT(c.id) as total')
            ->join('app a', 'a.id = c.id_app')
            ->where('c.tipo', $tipo)
            ->where('c.created_at >=', $hoy['inicio'])
            ->where('c.created_at <=', $hoy['fin'])
            ->where('c.deleted_at', null)
            ->groupBy('c.id_app')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResult();

        return array_map(fn($r) => [
            'id_app'   => (int) $r->id_app,
            'app'      => $r->app,
            'telefono' => $r->telefono,
            'total'    => (int) $r->total,
        ], $resultado);
    }

    /**
     * Tendencia de Conversaciones: últimos 7 días agrupados por fecha
     */
    private function tendenciaConversaciones(string $tipo): array
    {
        $db = \Config\Database::connect();

        $fecha_inicio = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $fecha_fin    = date('Y-m-d 23:59:59');

        $resultado = $db->table('conversacion c')
            ->select('DATE(c.created_at) as fecha, COUNT(c.id) as total')
            ->where('c.tipo', $tipo)
            ->where('c.created_at >=', $fecha_inicio)
            ->where('c.created_at <=', $fecha_fin)
            ->where('c.deleted_at', null)
            ->groupBy('DATE(c.created_at)')
            ->orderBy('fecha', 'ASC')
            ->get()
            ->getResult();

        // Rellenar días sin datos con 0
        $tendencia = [];
        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $tendencia[$fecha] = 0;
        }
        foreach ($resultado as $r) {
            $tendencia[$r->fecha] = (int) $r->total;
        }

        return array_map(fn($fecha, $total) => [
            'fecha' => $fecha,
            'total' => $total,
        ], array_keys($tendencia), array_values($tendencia));
    }

    // ║  DASHBOARD INDIVIDUAL (Personal CC / Personal CS)               ║

    private function buildDashboardIndividual(int $id_usuario, array $hoy): array
    {
        $resultado = [
            'total_conversaciones_hoy' => $this->contarPorUsuarioEstado($id_usuario, null, $hoy),
            'activas'                  => $this->contarPorUsuarioEstado($id_usuario, self::ESTADO_EN_ATENCION, $hoy),
            'finalizadas'              => $this->contarPorUsuarioEstado($id_usuario, self::ESTADO_FINALIZADO, $hoy),
            'sin_abrir'                => $this->contarPorUsuarioEstado($id_usuario, self::ESTADO_SIN_ABRIR, $hoy),
        ];

        // Solo incluir distribución de motivos si tiene datos
        $motivos = $this->distribucionMotivoPorUsuario($id_usuario, $hoy);
        if (!empty($motivos)) {
            $resultado['distribucion_motivo'] = $motivos;
        }

        return $resultado;
    }

    private function contarPorUsuarioEstado(int $id_usuario, ?int $id_estado, array $hoy): int
    {
        $builder = model('ConversacionModel')
            ->where('id_usuario', $id_usuario)
            ->where('created_at >=', $hoy['inicio'])
            ->where('created_at <=', $hoy['fin'])
            ->where('deleted_at', null);

        if ($id_estado !== null) {
            $builder->where('id_estado', $id_estado);
        }

        return $builder->countAllResults();
    }

    private function distribucionMotivoPorUsuario(int $id_usuario, array $hoy): array
    {
        $db = \Config\Database::connect();

        $resultado = $db->table('conversacion c')
            ->select('mc.id as id_motivo, mc.nombre as motivo, COUNT(c.id) as total')
            ->join('motivo_conversacion mc', 'mc.id = c.id_motivo', 'left')
            ->where('c.id_usuario', $id_usuario)
            ->where('c.created_at >=', $hoy['inicio'])
            ->where('c.created_at <=', $hoy['fin'])
            ->where('c.deleted_at', null)
            ->groupBy('c.id_motivo')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResult();

        return array_map(fn($r) => [
            'id_motivo' => $r->id_motivo ? (int) $r->id_motivo : null,
            'motivo'    => $r->motivo ?? 'Sin motivo',
            'total'     => (int) $r->total,
        ], $resultado);
    }

    private function contarPorTipoEstado(string $tipo, int $id_estado, array $hoy): int
    {
        return model('ConversacionModel')
            ->where('tipo', $tipo)
            ->where('id_estado', $id_estado)
            ->where('created_at >=', $hoy['inicio'])
            ->where('created_at <=', $hoy['fin'])
            ->where('deleted_at', null)
            ->countAllResults();
    }

    private function obtenerRolesUsuario(int $id_usuario): array
    {
        $result = \Config\Database::connect()
            ->table('grupo_usuario_usuario guu')
            ->select('gur.id as id_rol, gur.rol as nombre_rol')
            ->join('grupo_usuario_rol gur', 'gur.id = guu.id_grupo_usuario_rol')
            ->where('guu.id_usuario', $id_usuario)
            ->where('guu.deleted_at', null)
            ->get()
            ->getResult();

        return $result ?: [];
    }

    private function getRangoHoy(): array
    {
        return [
            'inicio' => date('Y-m-d 00:00:00'),
            'fin'    => date('Y-m-d 23:59:59'),
        ];
    }
}
