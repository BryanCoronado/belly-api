<?php



namespace Belly\Mensajes\Controllers\Api;



use App\Controllers\BaseController;



class PlantillaRapidaController extends BaseController

{

    public function crear()

    {

        try {

            $data = $this->datosDesdeRequest();

            $imagen = $this->request->getFile('imagen');

            $plantilla_rapida = plantilla_rapida()->crearPlantillaRapida($data, $imagen);



            return $this->response->setJSON([

                'success' => true,

                'data'    => $plantilla_rapida,

            ]);

        } catch (\InvalidArgumentException $e) {

            return $this->response->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ])->setStatusCode(400);

        } catch (\Exception $e) {

            return $this->response->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ])->setStatusCode(400);

        }

    }



    public function listar()

    {

        $page = $this->request->getGet('page') ?? 1;

        $perPage = $this->request->getGet('perPage') ?? 10;

        $search = $this->request->getGet('search') ?? null;

        $search = ($search && strlen($search) > 2) ? $search : null;



        try {

            $plantillas_rapidas = plantilla_rapida()->listarTodasPlantillasRapidas($page, $perPage, $search);



            return $this->response->setJSON([

                'success'    => true,

                'data'       => $plantillas_rapidas['data'] ?? [],

                'pagination' => $plantillas_rapidas['pagination'] ?? null,

            ]);

        } catch (\Exception $e) {

            return $this->response->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ])->setStatusCode(400);

        }

    }



    public function listarPorUsuario()

    {

        $page = $this->request->getGet('page') ?? 1;

        $perPage = $this->request->getGet('perPage') ?? 10;

        $search = $this->request->getGet('search') ?? null;

        $search = ($search && strlen($search) > 2) ? $search : null;



        try {

            $plantillas_rapidas = plantilla_rapida()->listarPlantillasRapidasPorUsuario($page, $perPage, $search);



            return $this->response->setJSON([

                'success'    => true,

                'data'       => $plantillas_rapidas['data'] ?? [],

                'pagination' => $plantillas_rapidas['pagination'] ?? null,

            ]);

        } catch (\Exception $e) {

            return $this->response->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ])->setStatusCode(400);

        }

    }



    public function ver(int $id)

    {

        try {

            $plantilla_rapida = plantilla_rapida()->verPlantillaRapida($id);



            return $this->response->setJSON([

                'success' => true,

                'data'    => $plantilla_rapida,

            ]);

        } catch (\Exception $e) {

            return $this->response->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ])->setStatusCode(404);

        }

    }



    public function editar(int $id)

    {

        try {

            $data = $this->datosDesdeRequest();

            $imagen = $this->request->getFile('imagen');

            $plantilla_rapida = plantilla_rapida()->editarPlantillaRapida($data, $id, $imagen);



            return $this->response->setJSON([

                'success' => true,

                'data'    => $plantilla_rapida,

            ]);

        } catch (\InvalidArgumentException $e) {

            return $this->response->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ])->setStatusCode(400);

        } catch (\Exception $e) {

            return $this->response->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ])->setStatusCode(400);

        }

    }



    public function eliminar(int $id)

    {

        try {

            $resultado = plantilla_rapida()->eliminarPlantillaRapida($id);



            return $this->response->setStatusCode(200)->setJSON([

                'success' => true,

                'data'    => $resultado,

            ]);

        } catch (\Exception $e) {

            return $this->response->setStatusCode(404)->setJSON([

                'success' => false,

                'error'   => $e->getMessage(),

            ]);

        }

    }



    /**

     * JSON o multipart (nombre, contenido, eliminar_imagen).

     *

     * @return array<string, mixed>

     */

    private function datosDesdeRequest(): array
    {
        if ($this->requestEsMultipart()) {
            return $this->datosDesdePost();
        }

        $raw = (string) $this->request->getBody();
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json) && $json !== []) {
                return $json;
            }
        }

        return $this->datosDesdePost();
    }

    private function requestEsMultipart(): bool
    {
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));

        return str_contains($contentType, 'multipart/form-data');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDesdePost(): array
    {
        return [
            'nombre'          => $this->request->getPost('nombre'),
            'contenido'       => $this->request->getPost('contenido'),
            'eliminar_imagen' => $this->request->getPost('eliminar_imagen'),
        ];
    }

}


