<?php

namespace Belly\Configuracion\Controllers\Api;

use App\Controllers\BaseController;

class EmpresaControllerApi extends BaseController
{
    public function listar()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->listar(),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function leerModo()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->leerModo(),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function leerCupo()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => cupo()->resumen(),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function guardarModo()
    {
        try {
            $datos = $this->request->getJSON(true);

            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->guardarModo(is_array($datos) ? $datos : []),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function leerAppAsesor()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->leerAppAsesor(),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function guardarAppAsesor()
    {
        try {
            $datos = $this->request->getJSON(true);

            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->guardarAppAsesor(is_array($datos) ? $datos : []),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function subirApkAsesor()
    {
        try {
            $archivo = $this->request->getFile('apk');

            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->subirApkAsesor($archivo),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    /** Descarga pública del APK (para compartir el enlace sin login). */
    public function descargarApkAsesor()
    {
        $ruta = empresa()->rutaApkAsesor();
        $rutaPublica = FCPATH . 'app' . DIRECTORY_SEPARATOR . 'belly-asesor.apk';
        if (! is_file($ruta) && is_file($rutaPublica)) {
            $ruta = $rutaPublica;
        }

        if (! is_file($ruta) || ! is_readable($ruta) || filesize($ruta) <= 0) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'error' => 'El APK aún no está publicado. Súbelo desde Integraciones → App Belly Asesor.',
            ]);
        }

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.android.package-archive')
            ->download($ruta, 'belly-asesor.apk');
    }

    public function cambiarEstado(int $id)
    {
        try {
            $datos = $this->request->getJSON(true);

            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->cambiarEstado($id, is_array($datos) ? $datos : []),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function crear()
    {
        try {
            $datos = $this->request->getJSON(true);
            if (! is_array($datos)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'No se recibieron datos.',
                ]);
            }

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data' => empresa()->crear($datos),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    private function responderError(\Throwable $e)
    {
        $codigo = str_contains($e->getMessage(), 'No autorizado') || str_contains($e->getMessage(), 'Solo el administrador')
            ? 403
            : 400;

        return $this->response->setStatusCode($codigo)->setJSON([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    }
}
