<?php

namespace Belly\Usuarios\UseCases;

use Belly\Services\Autorizacion;
use Belly\Services\PermisoService;

class AccesoUseCase
{
    /** @var list<array<string, mixed>>|null */
    private ?array $catalogo = null;

    /**
     * @return list<array{id:int,id_padre:?int,nombre:string,descripcion:string,grupo:string,alias:string,tipo:string,requiere_whatsapp:bool,solo_admin:bool}>
     */
    public function catalogo(): array
    {
        if ($this->catalogo !== null) {
            return $this->catalogo;
        }

        $filas = db_connect()->table('permiso')
            ->select('permiso.id, permiso.id_permiso_padre, permiso.permiso as nombre, permiso.descripcion, permiso.alias, permiso.tipo, permiso.requiere_whatsapp, permiso.solo_admin, gp.grupo')
            ->join('grupo_permiso as gp', 'gp.id = permiso.id_grupo_permiso', 'inner')
            ->where('permiso.deleted_at', null)
            ->where('permiso.activo', 1)
            ->orderBy('gp.id', 'ASC')
            ->orderBy('permiso.orden', 'ASC')
            ->orderBy('permiso.id', 'ASC')
            ->get()
            ->getResultArray();

        $salida = [];
        foreach ($filas as $fila) {
            $salida[] = [
                'id' => (int) $fila['id'],
                'id_padre' => $fila['id_permiso_padre'] !== null ? (int) $fila['id_permiso_padre'] : null,
                'nombre' => (string) $fila['nombre'],
                'descripcion' => (string) ($fila['descripcion'] ?? ''),
                'grupo' => (string) $fila['grupo'],
                'alias' => (string) ($fila['alias'] ?? ''),
                'tipo' => (string) ($fila['tipo'] ?? 'modulo'),
                'requiere_whatsapp' => (int) ($fila['requiere_whatsapp'] ?? 0) === 1,
                'solo_admin' => (int) ($fila['solo_admin'] ?? 0) === 1,
            ];
        }

        return $this->catalogo = $salida;
    }

    /** @return array<int, array<string, mixed>> */
    public function listarRoles(?int $idEmpresa = null): array
    {
        $idEmpresa = $idEmpresa ?? $this->idEmpresa();
        $filas = db_connect()->table('grupo_usuario_rol')
            ->select('id, rol, alias, descripcion, es_admin')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('es_admin', 'DESC')
            ->orderBy('rol', 'ASC')
            ->get()
            ->getResultArray();

        if ($filas === []) {
            $creados = $this->crearRolesIniciales($idEmpresa);
            helper('vip2system');
            $usuario = auth()->getUsuario();
            if ($usuario !== null && (int) ($usuario->sudo ?? 0) === 1) {
                $this->asignarUsuarioARol((int) $usuario->id, $creados['admin'], $idEmpresa);
            }
            return $this->listarRoles($idEmpresa);
        }

        return array_map(fn (array $fila) => $this->presentarRol($fila), $filas);
    }

    /** @return array{admin:int,asesor:int} */
    public function crearRolesIniciales(int $idEmpresa): array
    {
        $this->crearGruposIniciales($idEmpresa);
        $admin = $this->insertarRol($idEmpresa, [
            'rol' => 'Administrador',
            'alias' => 'administrador',
            'descripcion' => 'Gestiona la empresa, el equipo y los permisos.',
            'es_admin' => 1,
        ]);
        $asesor = $this->insertarRol($idEmpresa, [
            'rol' => 'Asesor',
            'alias' => 'asesor',
            'descripcion' => 'Atiende chats y clientes.',
            'es_admin' => 0,
        ]);

        $todos = [];
        foreach ($this->catalogo() as $modulo) {
            $todos[] = [
                'id' => $modulo['id'],
                'acc_lectura' => 1,
                'acc_crear' => 1,
                'acc_actualizar' => 1,
                'acc_eliminar' => 1,
            ];
        }
        $this->guardarPermisosRol($admin, $todos, $idEmpresa, false);

        $operativos = [
            'mod_sistemas_access',
            'mod_dashboard_access',
            'mod_conversaciones_chat_access',
            'mod_leads_access',
            'mod_leads_clientes_access',
            'mod_conversaciones_chat_mis_clientes_access',
            'mod_conversaciones_chat_mis_conversaciones_access',
            'mod_mis_plantillas_rapidas_access',
            'imagenes_access',
            'videos_access',
            'documentos_access',
            'camara_access',
            'plantillas_mensajes_access',
            'catalogo_access',
        ];
        $operacion = [];
        foreach ($this->catalogo() as $modulo) {
            $puede = in_array($modulo['alias'], $operativos, true);
            $operacion[] = [
                'id' => $modulo['id'],
                'acc_lectura' => $puede ? 1 : 0,
                'acc_crear' => $puede ? 1 : 0,
                'acc_actualizar' => $puede ? 1 : 0,
                'acc_eliminar' => 0,
            ];
        }
        // Plantilla inicial: lo que requiere WhatsApp queda guardado y se habilita al conectarlo.
        $this->guardarPermisosRol($asesor, $operacion, $idEmpresa, false);

        return ['admin' => $admin, 'asesor' => $asesor];
    }

    /** @param array<string, mixed> $datos */
    public function crearRol(array $datos): array
    {
        $this->exigirAdministrador();
        $idEmpresa = $this->idEmpresa();
        $nombre = trim((string) ($datos['rol'] ?? ''));
        if ($nombre === '') {
            throw new \Exception('El nombre del rol es obligatorio.');
        }

        $id = $this->insertarRol($idEmpresa, [
            'rol' => $nombre,
            'alias' => $this->aliasDe($nombre),
            'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
            'es_admin' => 0,
        ]);

        $permisos = is_array($datos['permisos'] ?? null) ? $datos['permisos'] : [];
        if ($permisos !== []) {
            $this->guardarPermisosRol($id, $permisos, $idEmpresa);
        }

        return $this->obtenerRol($id);
    }

    /** @param array<string, mixed> $datos */
    public function editarRol(int $id, array $datos): array
    {
        $this->exigirAdministrador();
        $rol = $this->rolDeEmpresa($id);

        $nombre = trim((string) ($datos['rol'] ?? $rol['rol']));
        if ($nombre === '') {
            throw new \Exception('El nombre del rol es obligatorio.');
        }

        db_connect()->table('grupo_usuario_rol')->where('id', $id)->update([
            'rol' => $nombre,
            'alias' => $this->aliasDe($nombre),
            'descripcion' => trim((string) ($datos['descripcion'] ?? $rol['descripcion'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (is_array($datos['permisos'] ?? null)) {
            $this->guardarPermisosRol($id, $datos['permisos'], (int) $rol['id_empresa']);
        }

        return $this->obtenerRol($id);
    }

    public function eliminarRol(int $id): void
    {
        $this->exigirAdministrador();
        $rol = $this->rolDeEmpresa($id);
        if ((int) $rol['es_admin'] === 1) {
            throw new \Exception('No se puede eliminar el rol de administrador.');
        }

        $ocupado = db_connect()->table('grupo_usuario_usuario')
            ->where('id_grupo_usuario_rol', $id)
            ->where('deleted_at', null)
            ->countAllResults();
        if ($ocupado > 0) {
            throw new \Exception('Hay usuarios con este rol. Cámbialos antes de eliminarlo.');
        }

        db_connect()->table('grupo_usuario_rol')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function obtenerRol(int $id, ?int $idEmpresa = null): array
    {
        $rol = $this->rolDeEmpresa($id, $idEmpresa);
        $presentado = $this->presentarRol($rol);
        $presentado['permisos'] = $this->permisosDeRol($id, (int) $rol['id_empresa']);

        return $presentado;
    }

    /** @return array<int, array<string, mixed>> */
    public function permisosDeRol(int $idRol, ?int $idEmpresa = null): array
    {
        $rol = $this->rolDeEmpresa($idRol, $idEmpresa);
        $grupo = $this->grupoDeRol($rol);
        $asignados = db_connect()->table('grupo_usuario_permiso_rol')
            ->where('id_grupo_usuario', $grupo)
            ->where('id_grupo_usuario_rol', $idRol)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $porId = [];
        foreach ($asignados as $fila) {
            $porId[(int) $fila['id_permiso']] = $fila;
        }

        $salida = [];
        foreach ($this->catalogo() as $modulo) {
            $actual = $porId[$modulo['id']] ?? [];
            $salida[] = [
                'id' => $modulo['id'],
                'id_padre' => $modulo['id_padre'],
                'nombre' => $modulo['nombre'],
                'descripcion' => $modulo['descripcion'],
                'grupo' => $modulo['grupo'],
                'alias' => $modulo['alias'],
                'tipo' => $modulo['tipo'],
                'requiere_whatsapp' => $modulo['requiere_whatsapp'],
                'solo_admin' => $modulo['solo_admin'],
                'acc_lectura' => (int) ($actual['acc_lectura'] ?? 0),
                'acc_crear' => (int) ($actual['acc_crear'] ?? 0),
                'acc_actualizar' => (int) ($actual['acc_actualizar'] ?? 0),
                'acc_eliminar' => (int) ($actual['acc_eliminar'] ?? 0),
            ];
        }

        return $salida;
    }

    /**
     * Reglas: un subpermiso solo vale si su padre tiene acceso; los permisos «solo_admin» no se
     * otorgan a roles comunes; sin WhatsApp conectado no se pueden cambiar los que lo requieren
     * (se conservan los que ya tenía el rol para cuando se conecte).
     *
     * @param array<int, array<string, mixed>> $permisos
     */
    public function guardarPermisosRol(int $idRol, array $permisos, ?int $idEmpresa = null, bool $respetarConexion = true): array
    {
        $rol = $this->rolDeEmpresa($idRol, $idEmpresa);
        $grupo = $this->grupoDeRol($rol);
        $db = db_connect();
        $catalogo = array_column($this->catalogo(), null, 'id');
        $padres = array_column($this->catalogo(), 'id_padre', 'id');
        $esRolAdmin = (int) $rol['es_admin'] === 1;
        helper('vip2system');
        $bloquearWhatsapp = $respetarConexion && ! conexion_whatsapp()->estaConectada((int) $rol['id_empresa']);

        $anteriores = $db->table('grupo_usuario_permiso_rol')
            ->where('id_grupo_usuario', $grupo)
            ->where('id_grupo_usuario_rol', $idRol)
            ->get()
            ->getResultArray();

        $porId = [];
        foreach ($permisos as $permiso) {
            $porId[(int) ($permiso['id'] ?? 0)] = $permiso;
        }
        if ($bloquearWhatsapp) {
            foreach ($catalogo as $id => $item) {
                if ($item['requiere_whatsapp']) {
                    unset($porId[$id]);
                }
            }
            foreach ($anteriores as $fila) {
                $id = (int) $fila['id_permiso'];
                if (($catalogo[$id]['requiere_whatsapp'] ?? false) === true) {
                    $porId[$id] = $fila + ['id' => $id];
                }
            }
        }
        $permisos = array_values($porId);

        $conLectura = [];
        foreach ($permisos as $permiso) {
            if ((int) ($permiso['acc_lectura'] ?? 0) === 1) {
                $conLectura[(int) ($permiso['id'] ?? 0)] = true;
            }
        }

        $db->table('grupo_usuario_permiso_rol')
            ->where('id_grupo_usuario', $grupo)
            ->where('id_grupo_usuario_rol', $idRol)
            ->delete();

        foreach ($permisos as $permiso) {
            $idPermiso = (int) ($permiso['id'] ?? 0);
            if (! array_key_exists($idPermiso, $padres)) {
                continue;
            }
            if ($catalogo[$idPermiso]['solo_admin'] && ! $esRolAdmin) {
                continue;
            }

            $lectura = (int) ($permiso['acc_lectura'] ?? 0) === 1 ? 1 : 0;
            $crear = (int) ($permiso['acc_crear'] ?? 0) === 1 ? 1 : 0;
            $actualizar = (int) ($permiso['acc_actualizar'] ?? 0) === 1 ? 1 : 0;
            $eliminar = (int) ($permiso['acc_eliminar'] ?? 0) === 1 ? 1 : 0;

            $idPadre = $padres[$idPermiso];
            if ($idPadre !== null) {
                // Un subpermiso es un interruptor único y solo vale si su módulo padre tiene acceso.
                $activo = $lectura && isset($conLectura[$idPadre]) ? 1 : 0;
                $lectura = $crear = $actualizar = $eliminar = $activo;
            }

            if ($lectura + $crear + $actualizar + $eliminar === 0) {
                continue;
            }

            $db->table('grupo_usuario_permiso_rol')->insert([
                'id_grupo_usuario' => $grupo,
                'id_permiso' => $idPermiso,
                'id_grupo_usuario_rol' => $idRol,
                'acc_lectura' => $lectura,
                'acc_crear' => $crear,
                'acc_actualizar' => $actualizar,
                'acc_eliminar' => $eliminar,
                'activo' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->permisosDeRol($idRol, (int) $rol['id_empresa']);
    }

    /** @return array<int, array{id:int,grupo:string,modulos:array<int, mixed>}> */
    public function menu(): array
    {
        $esAdmin = $this->esAdministradorSesion();
        $soloLectura = $esAdmin ? null : $this->aliasesConLectura();
        helper('vip2system');
        $conWhatsapp = conexion_whatsapp()->estaConectada();

        $filas = db_connect()->table('modulo')
            ->select('modulo.id, modulo.nombre, modulo.url, modulo.icon, modulo.descripcion, modulo.orden, modulo.parent_id, modulo.id_modulo_grupo, mg.grupo, permiso.alias, permiso.requiere_whatsapp, permiso.solo_admin')
            ->join('modulo_grupo as mg', 'mg.id = modulo.id_modulo_grupo', 'left')
            ->join('permiso', 'permiso.id = modulo.id_permiso', 'left')
            ->where('modulo.activo', 1)
            ->where('modulo.deleted_at', null)
            ->orderBy('modulo.id_modulo_grupo', 'ASC')
            ->orderBy('modulo.orden', 'ASC')
            ->orderBy('modulo.id', 'ASC')
            ->get()
            ->getResultArray();

        $filas = array_values(array_filter($filas, static function (array $fila) use ($soloLectura, $esAdmin, $conWhatsapp): bool {
            if (! $conWhatsapp && (int) ($fila['requiere_whatsapp'] ?? 0) === 1) {
                return false;
            }
            if (! $esAdmin && (int) ($fila['solo_admin'] ?? 0) === 1) {
                return false;
            }
            $alias = (string) ($fila['alias'] ?? '');

            return $soloLectura === null || $alias === '' || in_array($alias, $soloLectura, true);
        }));

        $porGrupo = [];
        foreach ($this->arbolModulos($filas) as $modulo) {
            $idGrupo = (int) ($modulo['id_modulo_grupo'] ?? 0);
            if (! isset($porGrupo[$idGrupo])) {
                $porGrupo[$idGrupo] = [
                    'id' => $idGrupo,
                    'grupo' => (string) ($modulo['grupo'] ?? 'General'),
                    'modulos' => [],
                ];
            }
            $porGrupo[$idGrupo]['modulos'][] = $modulo;
        }

        return array_values($porGrupo);
    }

    /** @return array<int, array<string, mixed>> */
    public function listarGrupos(): array
    {
        $idEmpresa = $this->idEmpresa();
        $this->crearGruposIniciales($idEmpresa);
        $base = $this->gruposBase($idEmpresa);

        $filas = db_connect()->table('grupo_usuario')
            ->select('id, grupo, descripcion')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $miembros = [];
        foreach ($this->miembrosDeEmpresa($idEmpresa) as $miembro) {
            $miembros[$miembro['id_grupo_usuario']][] = [
                'id' => $miembro['id'],
                'nombre' => $miembro['nombre'],
            ];
        }

        return array_map(static fn (array $fila) => [
            'id' => (int) $fila['id'],
            'grupo' => (string) $fila['grupo'],
            'descripcion' => (string) ($fila['descripcion'] ?? ''),
            'es_base' => in_array((int) $fila['id'], $base, true) ? 1 : 0,
            'miembros' => $miembros[(int) $fila['id']] ?? [],
        ], $filas);
    }

    /**
     * Pasa a este grupo a los usuarios indicados. Quien se quite vuelve al grupo base de su rol.
     *
     * @param array<int, mixed> $usuarios
     */
    public function guardarMiembrosGrupo(int $id, array $usuarios): void
    {
        $this->exigirAdministrador();
        $idEmpresa = $this->idEmpresa();
        $this->grupoUsuario($id, $idEmpresa);
        $esBase = in_array($id, $this->gruposBase($idEmpresa), true);

        $elegidos = array_values(array_unique(array_filter(array_map('intval', $usuarios), static fn (int $v) => $v > 0)));
        $actuales = $this->miembrosDeEmpresa($idEmpresa);
        $deLaEmpresa = array_column($actuales, 'id');
        foreach ($elegidos as $idUsuario) {
            if (! in_array($idUsuario, $deLaEmpresa, true)) {
                throw new \Exception('Uno de los usuarios no pertenece a esta empresa.');
            }
        }

        $db = db_connect();
        $ahora = date('Y-m-d H:i:s');
        foreach ($actuales as $miembro) {
            $estaba = $miembro['id_grupo_usuario'] === $id;
            $queda = in_array($miembro['id'], $elegidos, true);
            if ($estaba === $queda) {
                continue;
            }

            if ($queda) {
                $destino = $id;
            } else {
                if ($esBase) {
                    throw new \Exception('Nadie puede quedar sin grupo: para sacar a alguien de un grupo base, agrégalo a otro grupo.');
                }
                $destino = $this->grupoDeRol($this->rolDeEmpresa($miembro['id_rol'], $idEmpresa));
            }

            $db->table('grupo_usuario_usuario')
                ->where('id_usuario', $miembro['id'])
                ->where('deleted_at', null)
                ->update(['id_grupo_usuario' => $destino, 'updated_at' => $ahora]);
        }
    }

    /** @return array<int, array{id:int,nombre:string,id_grupo_usuario:int,id_rol:int}> */
    private function miembrosDeEmpresa(int $idEmpresa): array
    {
        $filas = db_connect()->table('usuario as u')
            ->select('u.id, u.usuario, up.nombres, up.apellidos, guu.id_grupo_usuario, guu.id_grupo_usuario_rol')
            ->join('grupo_usuario_usuario as guu', 'guu.id_usuario = u.id AND guu.deleted_at IS NULL', 'inner')
            ->join('usuario_perfil as up', 'up.id_usuario = u.id', 'left')
            ->where('u.id_empresa', $idEmpresa)
            ->where('u.deleted_at', null)
            ->orderBy('up.nombres', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $fila) => [
            'id' => (int) $fila['id'],
            'nombre' => trim(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? '')) ?: (string) $fila['usuario'],
            'id_grupo_usuario' => (int) $fila['id_grupo_usuario'],
            'id_rol' => (int) $fila['id_grupo_usuario_rol'],
        ], $filas);
    }

    /** @return array<string, mixed> */
    public function rolPorDefecto(int $idEmpresa): array
    {
        $rol = db_connect()->table('grupo_usuario_rol')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('es_admin', 'ASC')
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($rol === null) {
            $roles = $this->crearRolesIniciales($idEmpresa);
            $rol = $this->rolDeEmpresa($roles['asesor'] ?? $roles['admin'], $idEmpresa);
        }

        return $rol;
    }

    /** @return array{admin:int,equipo:int} */
    public function crearGruposIniciales(int $idEmpresa): array
    {
        $base = $this->gruposBase($idEmpresa);
        if ($base !== []) {
            return ['admin' => $base[0], 'equipo' => $base[1] ?? $base[0]];
        }

        $db = db_connect();
        $ids = [];
        foreach ([
            ['Administradores', 'Gestionan la empresa y el equipo.'],
            ['Equipo', 'Personal que atiende chats y clientes.'],
        ] as [$grupo, $descripcion]) {
            $db->table('grupo_usuario')->insert([
                'id_empresa' => $idEmpresa,
                'grupo' => $grupo,
                'descripcion' => $descripcion,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $ids[] = (int) $db->insertID();
        }

        return ['admin' => $ids[0], 'equipo' => $ids[1]];
    }

    public function obtenerGrupo(int $id): array
    {
        $grupo = $this->grupoUsuario($id);
        $grupo['es_base'] = in_array($id, $this->gruposBase($this->idEmpresa()), true) ? 1 : 0;
        $grupo['miembros'] = array_values(array_map(
            static fn (array $miembro) => $miembro['id'],
            array_filter($this->miembrosDeEmpresa($this->idEmpresa()), static fn (array $miembro) => $miembro['id_grupo_usuario'] === $id),
        ));

        return $grupo;
    }

    /** @param array<string, mixed> $datos */
    public function crearGrupo(array $datos): array
    {
        $this->exigirAdministrador();
        $nombre = trim((string) ($datos['grupo'] ?? ''));
        if ($nombre === '') {
            throw new \Exception('El nombre del grupo es obligatorio.');
        }

        $id = $this->enTransaccion(function () use ($nombre, $datos): int {
            db_connect()->table('grupo_usuario')->insert([
                'id_empresa' => $this->idEmpresa(),
                'grupo' => $nombre,
                'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int) db_connect()->insertID();
            $this->guardarDetalleGrupo($id, $datos);

            return $id;
        });

        return $this->obtenerGrupo($id);
    }

    /** @param array<string, mixed> $datos */
    public function editarGrupo(int $id, array $datos): array
    {
        $this->exigirAdministrador();
        $this->grupoUsuario($id);
        $nombre = trim((string) ($datos['grupo'] ?? ''));
        if ($nombre === '') {
            throw new \Exception('El nombre del grupo es obligatorio.');
        }

        $this->enTransaccion(function () use ($id, $nombre, $datos): int {
            db_connect()->table('grupo_usuario')->where('id', $id)->update([
                'grupo' => $nombre,
                'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->guardarDetalleGrupo($id, $datos);

            return $id;
        });

        return $this->obtenerGrupo($id);
    }

    private function enTransaccion(callable $trabajo): int
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $resultado = $trabajo();
            $db->transCommit();

            return $resultado;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @param array<string, mixed> $datos */
    private function guardarDetalleGrupo(int $id, array $datos): void
    {
        if (is_array($datos['miembros'] ?? null)) {
            $this->guardarMiembrosGrupo($id, $datos['miembros']);
        }
    }

    public function eliminarGrupo(int $id): void
    {
        $this->exigirAdministrador();
        $this->grupoUsuario($id);
        if (in_array($id, $this->gruposBase($this->idEmpresa()), true)) {
            throw new \Exception('Este grupo es base de la empresa y no se puede eliminar.');
        }

        $ocupado = db_connect()->table('grupo_usuario_usuario')
            ->where('id_grupo_usuario', $id)
            ->where('deleted_at', null)
            ->countAllResults();
        if ($ocupado > 0) {
            throw new \Exception('Hay usuarios en este grupo. Cámbialos antes de eliminarlo.');
        }

        db_connect()->table('grupo_usuario')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function asignarUsuarioARol(int $idUsuario, int $idRol, ?int $idEmpresa = null, ?int $idGrupo = null): void
    {
        $rol = $this->rolDeEmpresa($idRol, $idEmpresa);
        $empresaRol = (int) $rol['id_empresa'];
        if ($idGrupo && $idGrupo > 0) {
            $this->grupoUsuario($idGrupo, $empresaRol);
            $grupo = $idGrupo;
        } else {
            $grupo = $this->grupoDeRol($rol);
        }
        $db = db_connect();
        $db->table('grupo_usuario_usuario')
            ->where('id_usuario', $idUsuario)
            ->where('deleted_at', null)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $db->table('grupo_usuario_usuario')->insert([
            'id_grupo_usuario' => $grupo,
            'id_usuario' => $idUsuario,
            'id_grupo_usuario_rol' => $idRol,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function rolDeEmpresa(int $id, ?int $idEmpresa = null): array
    {
        $idEmpresa = $idEmpresa !== null && $idEmpresa > 0 ? $idEmpresa : $this->idEmpresaOpcional();
        $consulta = db_connect()->table('grupo_usuario_rol')
            ->where('id', $id)
            ->where('deleted_at', null);
        if ($idEmpresa !== null) {
            $consulta->where('id_empresa', $idEmpresa);
        }
        $rol = $consulta->get()->getRowArray();
        if ($rol === null) {
            throw new \Exception('El rol no existe en esta empresa.');
        }

        return $rol;
    }

    /** @param array<string, mixed> $datos */
    private function insertarRol(int $idEmpresa, array $datos): int
    {
        $alias = $datos['alias'] ?: $this->aliasDe((string) $datos['rol']);
        $existe = db_connect()->table('grupo_usuario_rol')
            ->where('id_empresa', $idEmpresa)
            ->where('alias', $alias)
            ->where('deleted_at', null)
            ->countAllResults();
        if ($existe > 0) {
            throw new \Exception('Ya existe un rol con ese nombre.');
        }

        db_connect()->table('grupo_usuario_rol')->insert([
            'id_empresa' => $idEmpresa,
            'es_admin' => (int) ($datos['es_admin'] ?? 0),
            'rol' => $datos['rol'],
            'alias' => $alias,
            'descripcion' => $datos['descripcion'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) db_connect()->insertID();
    }

    private function grupoUsuario(int $id, ?int $idEmpresa = null): array
    {
        $grupo = db_connect()->table('grupo_usuario')
            ->select('id, grupo, descripcion')
            ->where('id', $id)
            ->where('id_empresa', $idEmpresa ?? $this->idEmpresa())
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
        if ($grupo === null) {
            throw new \Exception('El grupo no existe en esta empresa.');
        }

        return [
            'id' => (int) $grupo['id'],
            'grupo' => (string) $grupo['grupo'],
            'descripcion' => (string) ($grupo['descripcion'] ?? ''),
        ];
    }

    /**
     * Los dos primeros grupos de la empresa son los base: [administradores, equipo].
     *
     * @return array<int, int>
     */
    private function gruposBase(int $idEmpresa): array
    {
        $filas = db_connect()->table('grupo_usuario')
            ->select('id')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->limit(2)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $fila): int => (int) $fila['id'], $filas);
    }

    /** @param array<string, mixed> $rol */
    private function grupoDeRol(array $rol): int
    {
        $grupos = $this->crearGruposIniciales((int) $rol['id_empresa']);

        return (int) $rol['es_admin'] === 1 ? $grupos['admin'] : $grupos['equipo'];
    }

    /** @param array<string, mixed> $fila */
    private function presentarRol(array $fila): array
    {
        return [
            'id' => (int) $fila['id'],
            'rol' => $fila['rol'],
            'alias' => $fila['alias'] ?? '',
            'descripcion' => $fila['descripcion'] ?? '',
            'es_admin' => (int) ($fila['es_admin'] ?? 0),
        ];
    }

    private function aliasDe(string $nombre): string
    {
        $alias = strtolower(trim($nombre));
        $alias = preg_replace('/[^a-z0-9]+/', '-', $alias) ?? '';

        return substr(trim($alias, '-'), 0, 45);
    }

    private function idEmpresa(): int
    {
        $id = $this->idEmpresaOpcional();
        if ($id === null) {
            throw new \Exception('No hay empresa en sesión.');
        }

        return $id;
    }

    private function idEmpresaOpcional(): ?int
    {
        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);

        return $id > 0 ? $id : null;
    }

    private function exigirAdministrador(): void
    {
        Autorizacion::exigirAdministradorEmpresa('Solo el administrador puede gestionar roles y permisos.');
    }

    private function esAdministradorSesion(): bool
    {
        return Autorizacion::esAdministradorEmpresa();
    }

    /** @return array<int, string> */
    private function aliasesConLectura(): array
    {
        helper('vip2system');
        $usuario = auth()->getUsuario();
        if ($usuario === null) {
            return [];
        }

        $permisos = new PermisoService();
        $permisos->cargarPermisos((int) $usuario->id);

        $alias = [];
        foreach ($permisos->getPermisos() as $clave => $acciones) {
            if ($acciones['acc_lectura']) {
                $alias[] = (string) $clave;
            }
        }

        return $alias;
    }

    /**
     * @param array<int, array<string, mixed>> $modulos
     * @return array<int, array<string, mixed>>
     */
    private function arbolModulos(array $modulos, $parentId = null): array
    {
        $rama = [];
        foreach ($modulos as $modulo) {
            // Un submódulo cuyo padre quedó fuera del menú no se muestra.
            $padre = $modulo['parent_id'] ?? null;
            $esRaiz = $padre === null || $padre === '';
            $coincide = $parentId === null
                ? $esRaiz
                : (string) $padre === (string) $parentId;
            if (! $coincide) {
                continue;
            }
            $hijos = $this->arbolModulos($modulos, $modulo['id']);
            $rama[] = [
                'id' => (int) $modulo['id'],
                'nombre' => (string) $modulo['nombre'],
                'url' => (string) ($modulo['url'] ?? ''),
                'icon' => (string) ($modulo['icon'] ?? ''),
                'descripcion' => (string) ($modulo['descripcion'] ?? ''),
                'alias' => (string) ($modulo['alias'] ?? ''),
                'id_modulo_grupo' => (int) ($modulo['id_modulo_grupo'] ?? 0),
                'grupo' => (string) ($modulo['grupo'] ?? 'General'),
                'hijos' => $hijos,
            ];
        }

        return $rama;
    }
}
