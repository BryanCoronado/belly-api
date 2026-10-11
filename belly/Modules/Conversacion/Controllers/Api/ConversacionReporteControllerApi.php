<?php

namespace Belly\Conversacion\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Conversacion\Entities\ReporteLeadsPorAsesorItemEntity;
use CodeIgniter\API\ResponseTrait;

class ConversacionReporteControllerApi extends BaseController
{
    use ResponseTrait;

    public function obtenerLeadsPorAsesor()
    {
        try {
            $fechaInicio = $this->request->getGet('fechaInicio');
            $fechaFin = $this->request->getGet('fechaFin');
            $payload = conversacion_reporte()->obtenerLeadsPorAsesor($fechaInicio, $fechaFin);
            $data = [
                'leyenda' => $payload['leyenda'],
                'fecha_inicio' => $payload['fecha_inicio'],
                'fecha_fin' => $payload['fecha_fin'],
                'asesores' => array_map(
                    static fn (ReporteLeadsPorAsesorItemEntity $item): array => $item->toArray(),
                    $payload['asesores']
                ),
            ];

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function obtenerTasaCierrePorAsesor()
    {
        try {
            $fechaInicio = $this->request->getGet('fechaInicio');
            $fechaFin = $this->request->getGet('fechaFin');
            $data = conversacion_reporte()->obtenerTasaCierrePorAsesor($fechaInicio, $fechaFin);

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function obtenerMetricasCampanias()
    {
        try {
            $fechaInicio = $this->request->getGet('fechaInicio');
            $fechaFin = $this->request->getGet('fechaFin');
            $idApp = $this->request->getGet('id_app');
            $data = conversacion_reporte()->obtenerMetricasCampanias($fechaInicio, $fechaFin, $idApp);

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function obtenerEficienciaCampanias()
    {
        try {
            $fechaInicio = $this->request->getGet('fechaInicio');
            $fechaFin = $this->request->getGet('fechaFin');
            $idApp = $this->request->getGet('id_app');
            $data = conversacion_reporte()->obtenerEficienciaCampanias($fechaInicio, $fechaFin, $idApp);

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function obtenerMetricasPorOrigen()
    {
        try {
            $fechaInicio = $this->request->getGet('fechaInicio');
            $fechaFin = $this->request->getGet('fechaFin');
            $data = conversacion_reporte()->obtenerMetricasPorOrigen($fechaInicio, $fechaFin);

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarConversacionesPorOrigen()
    {
        try {
            $fechaInicio = $this->request->getGet('fechaInicio');
            $fechaFin = $this->request->getGet('fechaFin');
            $registro = $this->request->getGet('registro');
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = (int) ($this->request->getGet('perPage') ?? 20);
            $buscar = $this->request->getGet('buscar');
            $idEstadoRaw = $this->request->getGet('idEstado');
            $idEstado = ($idEstadoRaw === null || $idEstadoRaw === '') ? null : (int) $idEstadoRaw;

            $data = conversacion_reporte()->listarConversacionesPorOrigen(
                $fechaInicio,
                $fechaFin,
                $registro,
                $page,
                $perPage,
                $buscar,
                $idEstado
            );

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function obtenerUltimaConversacionAsesores()
    {
        try {
            $data = conversacion_reporte()->obtenerUltimaConversacionAsesores();

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function estadosAsesores()
    {
        try {
            $data = conversacion_reporte()->estadosAsesores();

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
