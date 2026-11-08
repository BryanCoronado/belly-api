<?php

namespace Belly\Services;

/**
 * Consulta DNI y RUC en json.pe para el alta de cuentas.
 */
class JsonPe
{
    private const URL = 'https://api.json.pe/api';

    /**
     * @return array{
     *   tipo: string,
     *   numero: string,
     *   nombre: string,
     *   nombres: string,
     *   apellidos: string,
     *   estado: string,
     *   condicion: string,
     *   direccion: string,
     *   ubigeo: string,
     *   representante_nombre: string,
     *   representante_cargo: string,
     *   representante_documento: string,
     *   crudo: array<string, mixed>
     * }
     */
    public function consultar(string $numero): array
    {
        $numero = preg_replace('/\D+/', '', $numero) ?? '';
        if (preg_match('/^\d{8}$/', $numero) === 1) {
            return $this->dni($numero);
        }
        if (preg_match('/^\d{11}$/', $numero) === 1) {
            return $this->ruc($numero);
        }

        throw new \RuntimeException('Escribe un DNI de 8 dígitos o un RUC de 11.');
    }

    /** @return array<string, mixed> */
    private function dni(string $numero): array
    {
        $respuesta = $this->pedir('/dni', ['dni' => $numero]);
        $data = is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];

        $nombres = trim((string) ($data['nombres'] ?? ''));
        $paterno = trim((string) ($data['apellido_paterno'] ?? ''));
        $materno = trim((string) ($data['apellido_materno'] ?? ''));
        $completo = trim((string) ($data['nombre_completo'] ?? ''));
        if ($completo === '') {
            $completo = trim($paterno . ' ' . $materno . ', ' . $nombres, ' ,');
        }

        return [
            'tipo' => 'dni',
            'numero' => $numero,
            'nombre' => $completo !== '' ? $completo : 'Persona natural',
            'nombres' => $nombres,
            'apellidos' => trim($paterno . ' ' . $materno),
            'estado' => '',
            'condicion' => '',
            'direccion' => trim((string) ($data['direccion_completa'] ?? $data['direccion'] ?? '')),
            'ubigeo' => trim((string) ($data['ubigeo_reniec'] ?? $data['ubigeo_sunat'] ?? '')),
            'representante_nombre' => $completo,
            'representante_cargo' => 'Titular',
            'representante_documento' => $numero,
            'crudo' => $respuesta,
        ];
    }

    /** @return array<string, mixed> */
    private function ruc(string $numero): array
    {
        $empresa = $this->pedir('/ruc', ['ruc' => $numero]);
        $data = is_array($empresa['data'] ?? null) ? $empresa['data'] : [];
        $razon = trim((string) ($data['nombre_o_razon_social'] ?? $data['razon_social'] ?? ''));
        if ($razon === '') {
            throw new \RuntimeException('No encontramos ese RUC. Revisa el número.');
        }

        $reps = [];
        try {
            $lista = $this->pedir('/ruc/representantes', ['ruc' => $numero]);
            $reps = is_array($lista['data'] ?? null) ? $lista['data'] : [];
        } catch (\Throwable $e) {
            $reps = [];
        }

        $primero = is_array($reps[0] ?? null) ? $reps[0] : [];
        $repNombre = trim((string) ($primero['nombre'] ?? ''));
        $repCargo = trim((string) ($primero['cargo'] ?? ''));
        $repDoc = preg_replace('/\D+/', '', (string) ($primero['numero_de_documento'] ?? '')) ?? '';

        return [
            'tipo' => 'ruc',
            'numero' => $numero,
            'nombre' => $razon,
            'nombres' => '',
            'apellidos' => '',
            'estado' => trim((string) ($data['estado'] ?? '')),
            'condicion' => trim((string) ($data['condicion'] ?? '')),
            'direccion' => trim((string) ($data['direccion_completa'] ?? $data['direccion'] ?? '')),
            'ubigeo' => trim((string) ($data['ubigeo_sunat'] ?? '')),
            'representante_nombre' => $repNombre,
            'representante_cargo' => $repCargo,
            'representante_documento' => $repDoc,
            'crudo' => [
                'empresa' => $empresa,
                'representantes' => $reps,
            ],
        ];
    }

    /**
     * @param array<string, string> $cuerpo
     * @return array<string, mixed>
     */
    private function pedir(string $ruta, array $cuerpo): array
    {
        $token = trim((string) env('JSONPE_TOKEN', ''));
        if ($token === '') {
            throw new \RuntimeException('Falta configurar la consulta de DNI y RUC.');
        }

        $ch = curl_init(self::URL . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $crudo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($crudo === false || $crudo === '') {
            throw new \RuntimeException('No pudimos consultar el documento. Intenta otra vez.');
        }

        $json = json_decode($crudo, true);
        if (! is_array($json)) {
            throw new \RuntimeException('La respuesta de la consulta no se entendió.');
        }

        if ($codigo >= 400 || ($json['success'] ?? true) === false) {
            $mensaje = trim((string) ($json['message'] ?? $json['error'] ?? ''));
            if ($mensaje === '' || stripos($mensaje, 'exito') !== false) {
                $mensaje = 'No encontramos ese documento. Revisa el número.';
            }
            throw new \RuntimeException($mensaje);
        }

        if ($error !== '') {
            throw new \RuntimeException('No pudimos consultar el documento. Intenta otra vez.');
        }

        return $json;
    }
}
