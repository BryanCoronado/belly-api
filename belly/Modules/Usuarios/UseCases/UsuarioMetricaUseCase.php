<?php
namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Config\UsuarioConfig;

class UsuarioMetricaUseCase {
    protected UsuarioConfig $config;

    const ESTADO_ABIERTO    = 1;
    const ESTADO_FINALIZADO = 2;
    const ESTADO_SIN_ABRIR  = 3;

    const ROL_PERSONAL_CS       = 5 || 1;
    const ROL_JEFE_CS           = 9;
    const ROL_JEFE_CC           = 7 || 1;

    const TIPO_CUSTOMER_SUCCESS = 'customer_success';
    const TIPO_CONTACT_CENTER   = 'contact_center';

    const ROLES_CON_METRICAS_INDIVIDUALES = [self::ROL_PERSONAL_CS];

    const ROLES_JEFE_TIPO = [
        self::ROL_JEFE_CS => self::TIPO_CUSTOMER_SUCCESS,
        self::ROL_JEFE_CC => self::TIPO_CONTACT_CENTER,
    ];

    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
    }


    public function obtenerMetricasHoy(): array
    {
        $usuario_logueado = auth()->getUsuario();
        if (!$usuario_logueado) {
            throw new \Exception('No se pudo obtener el usuario autenticado');
        }

        $id_usuario = $usuario_logueado->id;
        $roles      = $this->obtenerRolesUsuario($id_usuario);
        $ids_rol    = array_map(fn($r) => (int) $r->id_rol, $roles);
        $nombres_rol = array_map(fn($r) => $r->nombre_rol, $roles);
        $hoy        = $this->getRangoHoy();

        $data = [
            'id_usuario' => $id_usuario,
            'roles'      => array_map(fn($r) => ['id' => (int) $r->id_rol, 'nombre' => $r->nombre_rol], $roles),
            'fecha'      => date('Y-m-d'),
        ];

        $tieneMetricasIndividuales = !empty(array_intersect($ids_rol, self::ROLES_CON_METRICAS_INDIVIDUALES));
        if ($tieneMetricasIndividuales) {
            $data = array_merge($data, $this->metricasIndividuales($id_usuario, $hoy));
        }

        foreach ($ids_rol as $id_rol) {
            if (isset(self::ROLES_JEFE_TIPO[$id_rol])) {
                $tipo = self::ROLES_JEFE_TIPO[$id_rol];
                $data['conversaciones_' . $tipo . '_hoy'] = $this->contarPorTipo($tipo, $hoy);
            }
        }

        return $data;
    }

    private function metricasIndividuales(int $id_usuario, array $hoy): array
    {
        return [
            'total_chats_abiertos_hoy' => $this->contarPorEstadoUsuario($id_usuario, self::ESTADO_ABIERTO, $hoy),
            'chats_sin_abrir_hoy'      => $this->contarPorEstadoUsuario($id_usuario, self::ESTADO_SIN_ABRIR, $hoy),
            'chats_finalizados_hoy'    => $this->contarPorEstadoUsuario($id_usuario, self::ESTADO_FINALIZADO, $hoy),
        ];
    }

    private function contarPorEstadoUsuario(int $id_usuario, int $id_estado, array $rango): int
    {
        return model('ConversacionModel')
            ->where('id_usuario', $id_usuario)
            ->where('id_estado', $id_estado)
            ->where('created_at >=', $rango['inicio'])
            ->where('created_at <=', $rango['fin'])
            ->where('deleted_at', null)
            ->countAllResults();
    }

    private function contarPorTipo(string $tipo, array $rango): int
    {
        return model('ConversacionModel')
            ->where('tipo', $tipo)
            ->where('created_at >=', $rango['inicio'])
            ->where('created_at <=', $rango['fin'])
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
