<?php

namespace Belly\Conversacion\UseCases;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConversacionExportUseCase
{
    public const MAX_FILAS = 50000;

    /** @var array<string, string> */
    public const COLUMNAS = [
        'id' => 'ID conversación',
        'id_cliente' => 'ID cliente',
        'cliente' => 'Cliente',
        'telefono' => 'Teléfono',
        'asesor' => 'Asesor',
        'transferido_por' => 'Transferido por',
        'estado' => 'Estado',
        'temperatura' => 'Temperatura',
        'fecha_venta' => 'Fecha venta',
        'etiqueta' => 'Etiqueta',
        'campania' => 'Campaña',
        'falta_responder' => 'Falta responder',
        'fecha_creacion' => 'Fecha creación',
    ];

    /** @var array<int, string> */
    private const ESTADOS = [
        1 => 'En atención',
        2 => 'Finalizado',
        3 => 'Sin abrir',
    ];

    /**
     * @param array<string, mixed> $filtros
     * @param list<int> $idsSeleccionados
     * @param list<string> $columnKeys
     *
     * @return array{filename: string, content: string, total: int}
     */
    public function exportar(
        string $tipo,
        int $idUsuario,
        array $filtros,
        string $alcance,
        array $idsSeleccionados,
        array $columnKeys
    ): array {
        $columnKeys = $this->normalizarColumnas($columnKeys);
        $filas = $this->obtenerFilas($tipo, $idUsuario, $filtros);

        if ($alcance === 'seleccion' && $idsSeleccionados !== []) {
            $set = array_flip($idsSeleccionados);
            $filas = array_values(array_filter($filas, static function ($row) use ($set) {
                $id = (int) self::valor($row, 'id');

                return isset($set[$id]);
            }));
        }

        if ($filas === []) {
            throw new \Exception('No hay registros para exportar con los criterios elegidos.');
        }

        if (count($filas) > self::MAX_FILAS) {
            throw new \Exception('Demasiados registros (' . count($filas) . '). Aplique más filtros (máximo ' . self::MAX_FILAS . ').');
        }

        $content = $this->generarXlsx($filas, $columnKeys);
        $filename = $this->nombreArchivo($tipo);

        return [
            'filename' => $filename,
            'content' => $content,
            'total' => count($filas),
        ];
    }

    /**
     * @param array<string, mixed> $filtros
     *
     * @return list<mixed>
     */
    private function obtenerFilas(string $tipo, int $idUsuario, array $filtros): array
    {
        $useCase = conversaciones();

        switch ($tipo) {
            case 'contact_center':
                $result = $useCase->listarConversacionesContactCenter(1, 'all', $filtros);
                break;
            case 'customer_success':
                $result = $useCase->listarConversacionesCustomerSuccess(1, 'all', $filtros);
                break;
            case 'listar':
            default:
                $result = $useCase->listarConversacion($idUsuario, $filtros, 1, 'all');
                break;
        }

        return $result['data'] ?? [];
    }

    /**
     * @param list<string> $columnKeys
     *
     * @return list<string>
     */
    private function normalizarColumnas(array $columnKeys): array
    {
        $validas = [];
        foreach ($columnKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && isset(self::COLUMNAS[$key])) {
                $validas[] = $key;
            }
        }

        if ($validas === []) {
            throw new \Exception('Seleccione al menos una columna para exportar.');
        }

        return $validas;
    }

    /**
     * @param list<mixed> $filas
     * @param list<string> $columnKeys
     */
    private function generarXlsx(array $filas, array $columnKeys): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Conversaciones');

        $colIndex = 1;
        foreach ($columnKeys as $key) {
            $sheet->setCellValue([$colIndex, 1], self::COLUMNAS[$key]);
            $colIndex++;
        }

        $rowIndex = 2;
        foreach ($filas as $fila) {
            $colIndex = 1;
            foreach ($columnKeys as $key) {
                $sheet->setCellValue([$colIndex, $rowIndex], $this->mapearValorColumna($key, $fila));
                $colIndex++;
            }
            $rowIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $content;
    }

    /**
     * @param mixed $item
     */
    private function mapearValorColumna(string $key, $item): string
    {
        switch ($key) {
            case 'id':
                return (string) (self::valor($item, 'id') ?? '');
            case 'id_cliente':
                return (string) (self::valor($item, 'id_cliente') ?? '');
            case 'cliente':
                return $this->nombreCliente($item);
            case 'telefono':
                return (string) (self::valor($item, 'telefono') ?? '');
            case 'asesor':
                return $this->nombreAsesor($item);
            case 'transferido_por':
                return $this->transferidoPor($item);
            case 'estado':
                $idEstado = (int) (self::valor($item, 'id_estado') ?? 0);

                return self::ESTADOS[$idEstado] ?? 'Desconocido';
            case 'temperatura':
                return $this->labelTemperatura(self::valor($item, 'temperatura'));
            case 'fecha_venta':
                return $this->formatearFecha(self::valor($item, 'venta_registrada_at'));
            case 'etiqueta':
                $nombre = self::valor($item, 'etiqueta_nombre') ?? self::valor($item, 'etiquetaNombre');

                return $nombre ? (string) $nombre : 'Sin etiqueta';
            case 'campania':
                $campania = self::valor($item, 'nombre_campania');

                return $campania ? (string) $campania : 'Sin campaña';
            case 'falta_responder':
                return $this->ultimoMensajeEsDelCliente($item) ? 'Sí' : 'No';
            case 'fecha_creacion':
                return $this->formatearFecha(self::valor($item, 'created_at'));
            default:
                return '';
        }
    }

    /**
     * @param mixed $item
     */
    private function nombreCliente($item): string
    {
        $nombre = trim((string) (self::valor($item, 'nombre_cliente') ?? ''));
        $apellido = trim((string) (self::valor($item, 'apellido_cliente') ?? ''));
        $completo = trim("{$nombre} {$apellido}");

        if ($completo !== '') {
            return $completo;
        }

        $nombreCompleto = self::valor($item, 'nombre_completo_cliente');

        return $nombreCompleto ? trim((string) $nombreCompleto) : 'Sin nombre';
    }

    /**
     * @param mixed $item
     */
    private function nombreAsesor($item): string
    {
        $nombres = trim((string) (self::valor($item, 'nombres') ?? ''));
        $apellidos = trim((string) (self::valor($item, 'apellidos') ?? ''));
        $completo = trim("{$nombres} {$apellidos}");
        if ($completo !== '') {
            return $completo;
        }

        $usuario = self::valor($item, 'nombre_usuario');

        return $usuario ? (string) $usuario : '-';
    }

    /**
     * @param mixed $item
     */
    private function transferidoPor($item): string
    {
        $nombreAsignado = self::valor($item, 'nombre_asignado_por');
        if ($nombreAsignado && trim((string) $nombreAsignado) !== '') {
            return trim((string) $nombreAsignado);
        }

        $nombres = trim((string) (self::valor($item, 'asignado_por_nombres') ?? ''));
        $apellidos = trim((string) (self::valor($item, 'asignado_por_apellidos') ?? ''));
        $completo = trim("{$nombres} {$apellidos}");
        if ($completo !== '') {
            return $completo;
        }

        $usuario = self::valor($item, 'asignado_por_usuario');

        return $usuario ? (string) $usuario : '';
    }

    /**
     * @param mixed $raw
     */
    private function formatearFecha($raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        if (is_object($raw) && isset($raw->date)) {
            $raw = $raw->date;
        }

        if (is_array($raw) && isset($raw['date'])) {
            $raw = $raw['date'];
        }

        $ts = strtotime((string) $raw);
        if ($ts === false) {
            return '';
        }

        return date('d/m/Y H:i', $ts);
    }

    private function labelTemperatura($temperatura): string
    {
        $valor = strtolower(trim((string) ($temperatura ?? '')));
        if ($valor === 'frio') {
            return 'Cliente frío';
        }
        if ($valor === 'caliente') {
            return 'Cliente caliente';
        }
        if ($valor === 'tibio') {
            return 'Cliente tibio';
        }
        if ($valor === 'venta') {
            return 'Cliente venta';
        }

        return $valor !== '' ? "Cliente {$valor}" : 'Cliente sin temperatura';
    }

    /**
     * @param mixed $item
     */
    private function ultimoMensajeEsDelCliente($item): bool
    {
        $contesta = strtolower(trim((string) (self::valor($item, 'ultimo_mensaje_contesta') ?? '')));

        return $contesta === 'cliente';
    }

    /**
     * @param mixed $row
     *
     * @return mixed
     */
    private static function valor($row, string $key)
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }
        if (is_object($row)) {
            return $row->$key ?? null;
        }

        return null;
    }

    private function nombreArchivo(string $tipo): string
    {
        $fecha = date('Y-m-d');
        $prefijo = preg_replace('/[^a-z0-9_-]+/i', '_', $tipo) ?: 'conversaciones';

        return "{$prefijo}_{$fecha}.xlsx";
    }
}
