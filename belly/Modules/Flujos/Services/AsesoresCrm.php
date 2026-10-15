<?php

namespace Belly\Flujos\Services;

use Belly\Flujos\Domain\CatalogoAsesores;

/** Lista las personas de la empresa que pueden atender chats. */
class AsesoresCrm implements CatalogoAsesores
{
    public function listar(): array
    {
        helper('vip2system');
        $idEmpresa = (int) (id_empresa_sesion() ?? 0);
        if ($idEmpresa <= 0) {
            return [];
        }

        $sql = 'SELECT u.id, u.usuario, p.nombres, p.apellidos,
                    (SELECT COUNT(*) FROM conversacion c WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND c.id_estado IN (1, 3)) AS abiertas
                FROM usuario u
                LEFT JOIN usuario_perfil p ON p.id_usuario = u.id AND p.deleted_at IS NULL
                WHERE ' . ConsultaAsesores::condicion() . '
                ORDER BY p.nombres ASC, p.apellidos ASC, u.usuario ASC';

        $lista = [];
        foreach (db_connect()->query($sql, [$idEmpresa])->getResultArray() as $f) {
            $nombre = trim(trim((string) ($f['nombres'] ?? '')) . ' ' . trim((string) ($f['apellidos'] ?? '')));
            $lista[] = [
                'id' => (int) $f['id'],
                'nombre' => $nombre !== '' ? $nombre : (string) $f['usuario'],
                'chats_abiertos' => (int) $f['abiertas'],
            ];
        }

        return $lista;
    }
}
