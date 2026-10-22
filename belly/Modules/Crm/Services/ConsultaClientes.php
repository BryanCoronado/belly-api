<?php

namespace Belly\Crm\Services;

use Belly\Services\Autorizacion;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DomainException;

/**
 * Arma la consulta de clientes de la empresa con los filtros del CRM.
 * Quien no es administrador solo ve a los clientes que atiende o atendió.
 *
 * Filtros: q, id_etapa, id_tipo (0 = sin tipo), id_etiqueta, ventana ('abierta' | 'cerrada'), id_asesor.
 */
final class ConsultaClientes
{
    public const SEGUNDOS_VENTANA = 86400;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function idEmpresa(): int
    {
        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new DomainException('No se pudo identificar tu empresa.');
        }

        return $id;
    }

    /** Null cuando puede ver a todos los clientes de la empresa. */
    public function idUsuarioRestringido(): ?int
    {
        if (Autorizacion::esAdministradorEmpresa()) {
            return null;
        }
        $usuario = Autorizacion::usuario();
        if ($usuario === null) {
            throw new DomainException('Tu sesión terminó. Vuelve a entrar.');
        }

        return (int) $usuario->id;
    }

    /** El número de WhatsApp conectado de la empresa. @return array<string, mixed> */
    public function appWhatsapp(string $siFalta = 'Conecta tu número de WhatsApp primero.'): array
    {
        $app = $this->db->table('app')
            ->where('id_empresa', $this->idEmpresa())
            ->where('estado', 1)
            ->where('verificado_at IS NOT NULL', null, false)
            ->where("phone_number_id IS NOT NULL AND phone_number_id <> ''", null, false)
            ->where('access_token IS NOT NULL', null, false)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)->getRowArray();
        if ($app === null) {
            throw new DomainException($siFalta);
        }

        return $app;
    }

    /**
     * Deja solo dígitos con código de país. Si el número no viene con + ni 00,
     * se le antepone el código por defecto cuando aún no lo tiene.
     */
    public static function normalizarTelefono(mixed $crudo, string $codigoPais = ''): ?string
    {
        $texto = trim((string) ($crudo ?? ''));
        $internacional = str_starts_with($texto, '+') || str_starts_with($texto, '00');
        $digitos = preg_replace('/\D/', '', $texto) ?? '';
        if (str_starts_with($texto, '00')) {
            $digitos = substr($digitos, 2);
        }
        $codigo = preg_replace('/\D/', '', $codigoPais) ?? '';
        if (! $internacional && $codigo !== '' && $digitos !== '' && ! str_starts_with($digitos, $codigo)) {
            $digitos = $codigo . ltrim($digitos, '0');
        }

        $largo = strlen($digitos);

        return $largo >= 8 && $largo <= 15 ? $digitos : null;
    }

    /** Desde cuándo un mensaje del cliente mantiene abierta la ventana de 24 horas. */
    public function inicioVentana(): string
    {
        return date('Y-m-d H:i:s', time() - self::SEGUNDOS_VENTANA);
    }

    public function sqlUltimoMensajeCliente(string $alias = 'c'): string
    {
        return "(SELECT MAX(m.created_at) FROM mensaje m
                   JOIN conversacion cv ON cv.id = m.id_conversacion AND cv.deleted_at IS NULL
                  WHERE cv.id_cliente = {$alias}.id AND m.contesta = 'cliente' AND m.deleted_at IS NULL)";
    }

    /**
     * @param array<string, mixed> $filtros
     * @param int|null $idPrimeraEtapa Los clientes sin etapa cuentan como de la primera.
     */
    public function builder(array $filtros, ?int $idPrimeraEtapa): BaseBuilder
    {
        $idEmpresa = $this->idEmpresa();
        $builder = $this->db->table('cliente c')
            ->join('cliente_perfil cp', 'cp.id_cliente = c.id AND cp.deleted_at IS NULL', 'left')
            ->where('c.id_empresa', $idEmpresa)
            ->where('c.deleted_at', null);

        $restringido = $this->idUsuarioRestringido();
        if ($restringido !== null) {
            $builder->where($this->sqlAtendidoPor($restringido), null, false);
        }

        $asesor = (int) ($filtros['id_asesor'] ?? 0);
        if ($asesor > 0) {
            $builder->where($this->sqlAtendidoPor($asesor), null, false);
        }

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $builder->groupStart()
                ->like('c.telefono', $q)
                ->orLike('cp.nombre', $q)
                ->orLike('cp.apellido', $q)
                ->orLike('cp.nombre_completo', $q)
                ->orLike('cp.correo', $q)
                ->groupEnd();
        }

        $etapa = (int) ($filtros['id_etapa'] ?? 0);
        if ($etapa > 0) {
            if ($etapa === $idPrimeraEtapa) {
                $builder->groupStart()->where('c.id_etapa', $etapa)->orWhere('c.id_etapa', null)->groupEnd();
            } else {
                $builder->where('c.id_etapa', $etapa);
            }
        }

        if (array_key_exists('id_tipo', $filtros) && $filtros['id_tipo'] !== null && $filtros['id_tipo'] !== '') {
            $tipo = (int) $filtros['id_tipo'];
            $tipo > 0 ? $builder->where('c.id_tipo', $tipo) : $builder->where('c.id_tipo', null);
        }

        $etiquetas = $this->listaIds($filtros['id_etiqueta'] ?? null);
        foreach ($etiquetas as $idEtiqueta) {
            $builder->where("EXISTS (SELECT 1 FROM cliente_etiqueta ce WHERE ce.id_cliente = c.id AND ce.id_etiqueta = {$idEtiqueta})", null, false);
        }

        $ventana = (string) ($filtros['ventana'] ?? '');
        if ($ventana === 'abierta' || $ventana === 'cerrada') {
            $inicio = $this->db->escape($this->inicioVentana());
            $ultimo = $this->sqlUltimoMensajeCliente();
            $builder->where(
                $ventana === 'abierta' ? "{$ultimo} >= {$inicio}" : "({$ultimo} IS NULL OR {$ultimo} < {$inicio})",
                null,
                false
            );
        }

        return $builder;
    }

    /** Le tocó una conversación del cliente o quedó como su responsable al crearlo. */
    private function sqlAtendidoPor(int $idUsuario): string
    {
        return "(c.id_responsable = {$idUsuario}
                 OR c.id IN (SELECT a.id_cliente FROM conversacion a WHERE a.deleted_at IS NULL AND a.id_usuario = {$idUsuario}))";
    }

    /** @return list<int> */
    public function listaIds(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        $lista = is_array($valor) ? $valor : explode(',', (string) $valor);

        return array_values(array_unique(array_filter(array_map('intval', $lista), static fn (int $id) => $id > 0)));
    }

    /** @return array<string, mixed> */
    public static function filtrosDe(array $entrada): array
    {
        return [
            'q' => $entrada['q'] ?? null,
            'id_etapa' => $entrada['id_etapa'] ?? null,
            'id_tipo' => $entrada['id_tipo'] ?? null,
            'id_etiqueta' => $entrada['id_etiqueta'] ?? null,
            'ventana' => $entrada['ventana'] ?? null,
            'id_asesor' => $entrada['id_asesor'] ?? null,
        ];
    }
}
