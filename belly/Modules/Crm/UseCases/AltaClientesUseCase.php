<?php

namespace Belly\Crm\UseCases;

use Belly\Crm\Services\ConsultaClientes;
use Belly\Services\Autorizacion;
use CodeIgniter\Database\BaseConnection;
use DomainException;

/** Alta manual de clientes, importación desde archivo e inicio de chat con quien aún no escribió. */
class AltaClientesUseCase
{
    public const MAX_FILAS = 2000;
    private const MAX_ETIQUETAS_NUEVAS = 30;
    private const COLORES = ['#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f97316', '#f59e0b', '#10b981', '#14b8a6'];

    private BaseConnection $db;
    private ConsultaClientes $consulta;
    private CarteraClientesUseCase $cartera;
    private AjustesCrmUseCase $ajustes;

    public function __construct(?ConsultaClientes $consulta = null, ?CarteraClientesUseCase $cartera = null, ?AjustesCrmUseCase $ajustes = null)
    {
        $this->db = db_connect();
        $this->consulta = $consulta ?? new ConsultaClientes($this->db);
        $this->ajustes = $ajustes ?? new AjustesCrmUseCase($this->consulta);
        $this->cartera = $cartera ?? new CarteraClientesUseCase($this->consulta, $this->ajustes);
    }

    /**
     * @param array<string, mixed> $datos telefono, codigo_pais, nombre, apellido, correo, distrito, notas,
     *                                    id_etapa, id_tipo, etiquetas[], id_responsable, abrir_chat
     * @return array<string, mixed> La ficha del cliente y, si se pidió, el chat abierto.
     */
    public function crear(array $datos): array
    {
        $telefono = ConsultaClientes::normalizarTelefono($datos['telefono'] ?? null, (string) ($datos['codigo_pais'] ?? ''));
        if ($telefono === null) {
            throw new DomainException('Escribe un número válido con código de país (8 a 15 dígitos).');
        }
        $idEmpresa = $this->consulta->idEmpresa();

        $existente = $this->db->table('cliente c')
            ->select('c.id, cp.nombre_completo')
            ->join('cliente_perfil cp', 'cp.id_cliente = c.id AND cp.deleted_at IS NULL', 'left')
            ->where('c.id_empresa', $idEmpresa)->where('c.telefono', $telefono)->where('c.deleted_at', null)
            ->get(1)->getRowArray();
        if ($existente !== null) {
            $quien = trim((string) ($existente['nombre_completo'] ?? ''));
            throw new DomainException('Ese número ya está registrado' . ($quien !== '' ? " como {$quien}" : '') . '.');
        }
        if ($this->bloqueado($idEmpresa, $telefono)) {
            throw new DomainException('Ese número está en tu lista negra. Quítalo de ahí si quieres registrarlo.');
        }
        $correo = trim((string) ($datos['correo'] ?? ''));
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Ese correo no es válido.');
        }

        $this->db->transBegin();
        try {
            $idCliente = $this->insertarCliente($idEmpresa, $telefono, $this->responsable($datos['id_responsable'] ?? null), 'manual');
            $campos = array_intersect_key($datos, array_flip(['nombre', 'apellido', 'correo', 'distrito', 'notas', 'id_etapa', 'id_tipo', 'etiquetas']));
            $ficha = $this->cartera->actualizar($idCliente, $campos);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        if (! empty($datos['abrir_chat'])) {
            try {
                $ficha['id_conversacion'] = $this->iniciarChat($idCliente)['id_conversacion'];
            } catch (DomainException $e) {
                $ficha['aviso'] = 'El cliente quedó guardado, pero no se pudo abrir el chat: ' . $e->getMessage();
            }
        }

        return $ficha;
    }

    /**
     * Abre (o reutiliza) un chat con el cliente para escribirle desde la bandeja.
     * Si nunca escribió o pasaron 24 horas, el chat pedirá usar una plantilla.
     *
     * @return array{id_conversacion: int, nueva: bool}
     */
    public function iniciarChat(int $idCliente): array
    {
        $this->cartera->ficha($idCliente);
        $app = $this->consulta->appWhatsapp('Conecta tu número de WhatsApp para poder escribirle.');
        $usuario = Autorizacion::usuario();
        if ($usuario === null) {
            throw new DomainException('Tu sesión terminó. Vuelve a entrar.');
        }
        $idEmpresa = $this->consulta->idEmpresa();

        $abierta = $this->db->table('conversacion')
            ->select('id')
            ->where('id_cliente', $idCliente)->where('id_empresa', $idEmpresa)->where('id_app', (int) $app['id'])
            ->whereIn('id_estado', [1, 3])->where('deleted_at', null)
            ->orderBy('id', 'DESC')
            ->get(1)->getRowArray();
        if ($abierta !== null) {
            return ['id_conversacion' => (int) $abierta['id'], 'nueva' => false];
        }

        $creada = conversaciones()->crearConversacion([
            'id_empresa' => $idEmpresa,
            'id_cliente' => $idCliente,
            'id_usuario' => (int) $usuario->id,
            'id_app' => (int) $app['id'],
            'id_estado' => 1,
            'origen' => 'manual',
            'registro' => 'manual',
            'id_asignado_por' => (int) $usuario->id,
            'asignado_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id_conversacion' => (int) $creada['id'], 'nueva' => true];
    }

    /**
     * @param list<array<string, mixed>> $filas telefono, nombre, apellido, correo, distrito, notas, etiquetas ("a, b")
     * @param array<string, mixed> $opciones codigo_pais, duplicados ('omitir' | 'actualizar'), id_etapa, id_tipo, etiquetas[], id_responsable
     * @return array{creados: int, actualizados: int, omitidos: int, problemas: list<array<string, mixed>>}
     */
    public function importar(array $filas, array $opciones): array
    {
        if ($filas === []) {
            throw new DomainException('El archivo no tiene filas con datos.');
        }
        if (count($filas) > self::MAX_FILAS) {
            throw new DomainException('Puedes importar hasta ' . self::MAX_FILAS . ' clientes por archivo.');
        }

        $idEmpresa = $this->consulta->idEmpresa();
        $codigoPais = (string) ($opciones['codigo_pais'] ?? '');
        $actualizar = ($opciones['duplicados'] ?? 'omitir') === 'actualizar';
        $idEtapa = $this->idDe('embudo_etapa', $opciones['id_etapa'] ?? null, 'Esa etapa no existe.');
        $idTipo = $this->idDe('cliente_tipo', $opciones['id_tipo'] ?? null, 'Ese tipo no existe.');
        $etiquetasComunes = array_map(fn (int $id) => $this->idDe('etiqueta', $id, 'Una de las etiquetas no existe.'), $this->consulta->listaIds($opciones['etiquetas'] ?? []));
        $responsable = $this->responsable($opciones['id_responsable'] ?? null);

        $problemas = [];
        $validas = [];
        $vistos = [];
        foreach (array_values($filas) as $indice => $fila) {
            $numero = (int) ($fila['fila'] ?? $indice + 2);
            $crudo = trim((string) ($fila['telefono'] ?? ''));
            $telefono = ConsultaClientes::normalizarTelefono($crudo, $codigoPais);
            if ($telefono === null) {
                $problemas[] = ['fila' => $numero, 'telefono' => $crudo, 'motivo' => $crudo === '' ? 'Falta el teléfono.' : 'El teléfono no es válido.'];

                continue;
            }
            if (isset($vistos[$telefono])) {
                $problemas[] = ['fila' => $numero, 'telefono' => $telefono, 'motivo' => "Repetido en el archivo (fila {$vistos[$telefono]})."];

                continue;
            }
            $correo = trim((string) ($fila['correo'] ?? ''));
            if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                $problemas[] = ['fila' => $numero, 'telefono' => $telefono, 'motivo' => 'El correo no es válido.'];

                continue;
            }
            $vistos[$telefono] = $numero;
            $validas[] = ['fila' => $numero, 'telefono' => $telefono, 'perfil' => $this->perfilDe($fila), 'notas' => $this->texto($fila['notas'] ?? null, 5000), 'etiquetas' => $this->nombresEtiquetas($fila['etiquetas'] ?? null)];
        }

        $telefonos = array_column($validas, 'telefono');
        $existentes = $this->existentes($idEmpresa, $telefonos);
        $bloqueados = $this->bloqueados($idEmpresa, $telefonos);
        $visibles = $this->visibles(array_values($existentes));
        $idsEtiqueta = $this->etiquetasPorNombre($idEmpresa, array_merge(...array_column($validas, 'etiquetas') ?: [[]]));

        $creados = 0;
        $actualizados = 0;
        $ahora = date('Y-m-d H:i:s');
        foreach ($validas as $fila) {
            $telefono = $fila['telefono'];
            if (isset($bloqueados[$telefono])) {
                $problemas[] = ['fila' => $fila['fila'], 'telefono' => $telefono, 'motivo' => 'Está en la lista negra.'];

                continue;
            }
            $etiquetas = array_values(array_unique(array_merge(
                $etiquetasComunes,
                array_values(array_filter(array_map(static fn (string $n) => $idsEtiqueta[mb_strtolower($n)] ?? 0, $fila['etiquetas'])))
            )));

            $idExistente = $existentes[$telefono] ?? null;
            if ($idExistente !== null) {
                if (! $actualizar) {
                    $problemas[] = ['fila' => $fila['fila'], 'telefono' => $telefono, 'motivo' => 'Ya estaba registrado.'];

                    continue;
                }
                if (! isset($visibles[$idExistente])) {
                    $problemas[] = ['fila' => $fila['fila'], 'telefono' => $telefono, 'motivo' => 'Ya existe y lo atiende otra persona.'];

                    continue;
                }
                $this->actualizarExistente($idEmpresa, $idExistente, $fila, $idEtapa, $idTipo, $etiquetas, $ahora);
                $actualizados++;

                continue;
            }

            $this->db->transStart();
            $idCliente = $this->insertarCliente($idEmpresa, $telefono, $responsable, 'importado', [
                'id_etapa' => $idEtapa,
                'etapa_at' => $idEtapa !== null ? $ahora : null,
                'id_tipo' => $idTipo,
                'notas' => $fila['notas'],
            ]);
            $this->db->table('cliente_perfil')->insert($fila['perfil'] + ['id_cliente' => $idCliente, 'created_at' => $ahora, 'updated_at' => $ahora]);
            $this->agregarEtiquetas($idEmpresa, $idCliente, $etiquetas, $ahora);
            $this->db->transComplete();
            if ($this->db->transStatus()) {
                $creados++;
            } else {
                $problemas[] = ['fila' => $fila['fila'], 'telefono' => $telefono, 'motivo' => 'No se pudo guardar.'];
            }
        }

        usort($problemas, static fn (array $a, array $b) => $a['fila'] <=> $b['fila']);

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => count($problemas),
            'problemas' => array_slice($problemas, 0, 500),
        ];
    }

    /** @param array<string, mixed> $extra */
    private function insertarCliente(int $idEmpresa, string $telefono, ?int $responsable, string $origen, array $extra = []): int
    {
        $ahora = date('Y-m-d H:i:s');
        $this->db->table('cliente')->insert($extra + [
            'id_empresa' => $idEmpresa,
            'telefono' => $telefono,
            'id_responsable' => $responsable,
            'origen' => $origen,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Quien no es administrador siempre queda como responsable.
     * El administrador puede elegir a cualquier persona activa de su empresa.
     */
    private function responsable(mixed $valor): ?int
    {
        $usuario = Autorizacion::usuario();
        if ($usuario === null) {
            throw new DomainException('Tu sesión terminó. Vuelve a entrar.');
        }
        $id = (int) ($valor ?? 0);
        if (! Autorizacion::esAdministradorEmpresa() || $id <= 0) {
            return (int) $usuario->id;
        }
        $valido = $this->db->table('usuario')
            ->where('id', $id)->where('id_empresa', $this->consulta->idEmpresa())
            ->where('activo', 1)->where('es_bot', 0)->where('deleted_at', null)
            ->countAllResults() > 0;
        if (! $valido) {
            throw new DomainException('Esa persona no está activa en tu equipo.');
        }

        return $id;
    }

    /** @param array<string, mixed> $fila @return array<string, ?string> */
    private function perfilDe(array $fila): array
    {
        $nombre = $this->texto($fila['nombre'] ?? null, 100);
        $apellido = $this->texto($fila['apellido'] ?? null, 100);

        return [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'nombre_completo' => trim(($nombre ?? '') . ' ' . ($apellido ?? '')) ?: null,
            'correo' => $this->texto($fila['correo'] ?? null, 150),
            'distrito' => $this->texto($fila['distrito'] ?? null, 100),
        ];
    }

    /**
     * Solo completa lo que trae el archivo: un campo vacío no borra lo que ya había.
     *
     * @param array<string, mixed> $fila
     * @param list<int> $etiquetas
     */
    private function actualizarExistente(int $idEmpresa, int $idCliente, array $fila, ?int $idEtapa, ?int $idTipo, array $etiquetas, string $ahora): void
    {
        $cliente = array_filter([
            'id_etapa' => $idEtapa,
            'etapa_at' => $idEtapa !== null ? $ahora : null,
            'id_tipo' => $idTipo,
            'notas' => $fila['notas'],
        ], static fn ($valor) => $valor !== null);
        if ($cliente !== []) {
            $this->db->table('cliente')->where('id', $idCliente)->where('id_empresa', $idEmpresa)->update($cliente + ['updated_at' => $ahora]);
        }

        $perfil = array_filter($fila['perfil'], static fn ($valor) => $valor !== null);
        if ($perfil !== []) {
            $actual = $this->db->table('cliente_perfil')->where('id_cliente', $idCliente)->get(1)->getRowArray();
            if ($actual !== null) {
                if (isset($perfil['nombre']) || isset($perfil['apellido'])) {
                    $perfil['nombre_completo'] = trim(($perfil['nombre'] ?? $actual['nombre'] ?? '') . ' ' . ($perfil['apellido'] ?? $actual['apellido'] ?? '')) ?: null;
                }
                $this->db->table('cliente_perfil')->where('id', $actual['id'])->update($perfil + ['updated_at' => $ahora, 'deleted_at' => null]);
            } else {
                $this->db->table('cliente_perfil')->insert($fila['perfil'] + ['id_cliente' => $idCliente, 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }

        $this->agregarEtiquetas($idEmpresa, $idCliente, $etiquetas, $ahora);
    }

    /** @param list<int> $etiquetas */
    private function agregarEtiquetas(int $idEmpresa, int $idCliente, array $etiquetas, string $ahora): void
    {
        foreach ($etiquetas as $idEtiqueta) {
            $this->db->query(
                'INSERT IGNORE INTO cliente_etiqueta (id_empresa, id_cliente, id_etiqueta, created_at) VALUES (?, ?, ?, ?)',
                [$idEmpresa, $idCliente, $idEtiqueta, $ahora]
            );
        }
    }

    /**
     * Busca las etiquetas por nombre (sin importar mayúsculas) y crea las que faltan.
     *
     * @param list<string> $nombres
     * @return array<string, int> nombre en minúsculas => id
     */
    private function etiquetasPorNombre(int $idEmpresa, array $nombres): array
    {
        $porNombre = [];
        foreach ($this->db->table('etiqueta')->select('id, nombre')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->get()->getResultArray() as $fila) {
            $porNombre[mb_strtolower($fila['nombre'])] = (int) $fila['id'];
        }

        $nuevas = [];
        foreach ($nombres as $nombre) {
            $clave = mb_strtolower($nombre);
            if (! isset($porNombre[$clave]) && ! isset($nuevas[$clave])) {
                $nuevas[$clave] = $nombre;
            }
        }
        if (count($nuevas) > self::MAX_ETIQUETAS_NUEVAS) {
            throw new DomainException('El archivo trae ' . count($nuevas) . ' etiquetas nuevas; el máximo por importación es ' . self::MAX_ETIQUETAS_NUEVAS . '. Revisa la columna de etiquetas.');
        }

        $ahora = date('Y-m-d H:i:s');
        $indice = count($porNombre);
        foreach ($nuevas as $clave => $nombre) {
            $this->db->table('etiqueta')->insert([
                'id_empresa' => $idEmpresa,
                'nombre' => $nombre,
                'color' => self::COLORES[$indice++ % count(self::COLORES)],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $porNombre[$clave] = (int) $this->db->insertID();
        }

        return $porNombre;
    }

    /** @return list<string> */
    private function nombresEtiquetas(mixed $valor): array
    {
        $partes = preg_split('/[,|;]/u', (string) ($valor ?? '')) ?: [];
        $nombres = [];
        foreach ($partes as $parte) {
            $nombre = mb_substr(trim($parte), 0, 60);
            if ($nombre !== '') {
                $nombres[mb_strtolower($nombre)] = $nombre;
            }
        }

        return array_values($nombres);
    }

    /** @param list<string> $telefonos @return array<string, int> */
    private function existentes(int $idEmpresa, array $telefonos): array
    {
        $mapa = [];
        foreach (array_chunk($telefonos, 500) as $tanda) {
            $filas = $this->db->table('cliente')->select('id, telefono')
                ->where('id_empresa', $idEmpresa)->where('deleted_at', null)->whereIn('telefono', $tanda)
                ->get()->getResultArray();
            foreach ($filas as $fila) {
                $mapa[$fila['telefono']] = (int) $fila['id'];
            }
        }

        return $mapa;
    }

    /** @param list<string> $telefonos @return array<string, true> */
    private function bloqueados(int $idEmpresa, array $telefonos): array
    {
        $mapa = [];
        foreach (array_chunk($telefonos, 500) as $tanda) {
            $filas = $this->db->table('lista_negra')->select('telefono')
                ->where('id_empresa', $idEmpresa)->where('deleted_at', null)->whereIn('telefono', $tanda)
                ->get()->getResultArray();
            foreach ($filas as $fila) {
                $mapa[$fila['telefono']] = true;
            }
        }

        return $mapa;
    }

    /** @param list<int> $ids @return array<int, true> */
    private function visibles(array $ids): array
    {
        $mapa = [];
        foreach (array_chunk($ids, 500) as $tanda) {
            $filas = $this->consulta->builder([], null)->select('c.id')->whereIn('c.id', $tanda)->get()->getResultArray();
            foreach ($filas as $fila) {
                $mapa[(int) $fila['id']] = true;
            }
        }

        return $mapa;
    }

    private function bloqueado(int $idEmpresa, string $telefono): bool
    {
        return $this->bloqueados($idEmpresa, [$telefono]) !== [];
    }

    private function idDe(string $tabla, mixed $valor, string $mensaje): ?int
    {
        $id = (int) ($valor ?? 0);
        if ($id <= 0) {
            return null;
        }
        $existe = $this->db->table($tabla)
            ->where('id', $id)->where('id_empresa', $this->consulta->idEmpresa())->where('deleted_at', null)
            ->countAllResults() > 0;
        if (! $existe) {
            throw new DomainException($mensaje);
        }

        return $id;
    }

    private function texto(mixed $valor, int $maximo): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : mb_substr($texto, 0, $maximo);
    }
}
