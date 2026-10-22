<?php

namespace Belly\Commands;

use Belly\Database\Catalogo\CatalogoAcceso;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Aplica CatalogoAcceso a una base existente sin borrar datos:
 * actualiza grupos, permisos y módulos por id, retira los obsoletos y normaliza los permisos de los roles.
 */
class SincronizarCatalogo extends BaseCommand
{
    protected $group = 'Belly';
    protected $name = 'catalogo:sincronizar';
    protected $description = 'Sincroniza grupos, permisos y módulos del menú con el catálogo del código.';

    public function run(array $params)
    {
        $db = db_connect();
        $ahora = date('Y-m-d H:i:s');
        $db->transStart();

        foreach (CatalogoAcceso::grupos() as $grupo) {
            $this->guardar($db, 'grupo_permiso', $grupo, $ahora);
            $this->guardar($db, 'modulo_grupo', ['id' => $grupo['id'], 'grupo' => $grupo['grupo']], $ahora);
        }

        $permisos = CatalogoAcceso::permisos();
        usort($permisos, static fn (array $a, array $b) => ($a['id_permiso_padre'] === null ? 0 : 1) <=> ($b['id_permiso_padre'] === null ? 0 : 1));
        foreach ($permisos as $permiso) {
            $this->guardar($db, 'permiso', $permiso, $ahora);
        }

        foreach (CatalogoAcceso::modulos() as $modulo) {
            $this->guardar($db, 'modulo', $modulo, $ahora);
        }

        $this->retirarObsoletos($db);
        $padres = $this->completarPadres($db, $ahora);
        $soloAdmin = $this->quitarSoloAdmin($db);

        $db->transComplete();
        if (! $db->transStatus()) {
            CLI::error('No se pudo sincronizar el catálogo.');

            return EXIT_ERROR;
        }

        CLI::write('Catálogo sincronizado.', 'green');
        CLI::write("Permisos padre agregados a roles: {$padres}. Permisos de administrador quitados a roles comunes: {$soloAdmin}.");

        return EXIT_SUCCESS;
    }

    /** @param array<string, mixed> $fila */
    private function guardar(BaseConnection $db, string $tabla, array $fila, string $ahora): void
    {
        $existe = $db->table($tabla)->where('id', $fila['id'])->countAllResults() > 0;
        if ($existe) {
            $db->table($tabla)->where('id', $fila['id'])->update($fila + ['updated_at' => $ahora, 'deleted_at' => null]);

            return;
        }

        $db->table($tabla)->insert($fila + ['created_at' => $ahora]);
    }

    /** Elimina permisos y módulos que ya no existen en el producto, junto con lo que los referenciaba. */
    private function retirarObsoletos(BaseConnection $db): void
    {
        $permisos = CatalogoAcceso::PERMISOS_RETIRADOS;
        $modulos = CatalogoAcceso::MODULOS_RETIRADOS;

        $db->table('modulo')->whereIn('id', $modulos)->delete();
        foreach (['grupo_usuario_permiso_rol', 'grupo_usuario_permiso_usuario', 'usuario_permiso_especial', 'grupo_usuario_permiso'] as $tabla) {
            $db->table($tabla)->whereIn('id_permiso', $permisos)->delete();
        }
        $db->table('modulo')->whereIn('id_permiso', $permisos)->delete();
        $db->table('permiso')->whereIn('id', $permisos)->delete();
    }

    /** Si un rol tiene un subpermiso, también debe tener acceso a su módulo padre. */
    private function completarPadres(BaseConnection $db, string $ahora): int
    {
        $faltantes = $db->query(
            'SELECT DISTINCT r.id_grupo_usuario, r.id_grupo_usuario_rol, p.id_permiso_padre AS id_padre
               FROM grupo_usuario_permiso_rol r
               JOIN permiso p ON p.id = r.id_permiso AND p.id_permiso_padre IS NOT NULL
               LEFT JOIN grupo_usuario_permiso_rol padre
                      ON padre.id_grupo_usuario = r.id_grupo_usuario
                     AND padre.id_grupo_usuario_rol = r.id_grupo_usuario_rol
                     AND padre.id_permiso = p.id_permiso_padre
              WHERE r.acc_lectura = 1 AND r.deleted_at IS NULL AND padre.id_permiso IS NULL'
        )->getResultArray();

        foreach ($faltantes as $fila) {
            $db->table('grupo_usuario_permiso_rol')->insert([
                'id_grupo_usuario' => $fila['id_grupo_usuario'],
                'id_grupo_usuario_rol' => $fila['id_grupo_usuario_rol'],
                'id_permiso' => $fila['id_padre'],
                'acc_lectura' => 1,
                'acc_crear' => 0,
                'acc_actualizar' => 0,
                'acc_eliminar' => 0,
                'activo' => 1,
                'created_at' => $ahora,
            ]);
        }

        $db->query(
            'UPDATE grupo_usuario_permiso_rol padre
               JOIN permiso hijo_p ON hijo_p.id_permiso_padre = padre.id_permiso
               JOIN grupo_usuario_permiso_rol hijo
                    ON hijo.id_permiso = hijo_p.id
                   AND hijo.id_grupo_usuario = padre.id_grupo_usuario
                   AND hijo.id_grupo_usuario_rol = padre.id_grupo_usuario_rol
                   AND hijo.acc_lectura = 1
                SET padre.acc_lectura = 1
              WHERE padre.acc_lectura = 0'
        );

        return count($faltantes);
    }

    private function quitarSoloAdmin(BaseConnection $db): int
    {
        $db->query(
            'DELETE r FROM grupo_usuario_permiso_rol r
               JOIN permiso p ON p.id = r.id_permiso AND p.solo_admin = 1
               JOIN grupo_usuario_rol rol ON rol.id = r.id_grupo_usuario_rol AND rol.es_admin = 0'
        );

        return $db->affectedRows();
    }
}
