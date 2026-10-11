<?php
namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Models\ConversacionModel;
use Belly\Conversacion\Entities\ConversacionEntity;


use Belly\Conversacion\Models\CitaConversacionModel;

class CitaConversacionUseCase {


       
    protected ConversacionConfig $config;
    protected ?CitaConversacionModel $cita_conversacion_provider = NULL;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
    }

    public function getCitaConversacionProvider(): CitaConversacionModel
    {
        if ($this->cita_conversacion_provider !== null) {
            return $this->cita_conversacion_provider;
        }

        $class_name = $this->config->cita_conversacion_provider;
        $this->cita_conversacion_provider = new $class_name();

        return $this->cita_conversacion_provider;
    }

    public function actualizarAsistenciaCita($idCitaAgendada, $asistencia)
    {
        $citaModel = $this->getCitaConversacionProvider();
        $cita = $citaModel->where('id_cita_erp', $idCitaAgendada)->first();
        if (!$cita) {
            throw new \Exception('No se encontró la cita agendada indicada.');
        }
        $cita->cita_estado = $asistencia;
        if (!$citaModel->save($cita)) {
            throw new \Exception('No se pudo actualizar la asistencia.');
        }
        return [
            'id_cita_conversacion' => $cita->id,
            'success' => true
        ];
    }

    public function crearCitaConversacion(array $datos)
    {
        $cita_conversacion_model = $this->getCitaConversacionProvider();

        $cita_conversacion_entity = new \Belly\Conversacion\Entities\CitaConversacionEntity();
        $cita_conversacion_entity->id_conversacion = $datos['id_conversacion'] ?? null;
        $cita_conversacion_entity->id_cita_erp = $datos['id_cita_erp'] ?? null;
        $cita_conversacion_entity->cita_estado = $datos['cita_estado'] ?? null;
        $cita_conversacion_entity->fecha_agendacion = $datos['fecha_agendacion'] ?? null;

        if ($cita_conversacion_model->save($cita_conversacion_entity)) {
            $cita_conversacion_entity->id = $cita_conversacion_model->insertID;
            // Retornar la entidad recién creada (puedes ajustar si quieres retornar el registro completo desde la BD)
            return $cita_conversacion_entity;
        } else {
            throw new \Exception(json_encode($cita_conversacion_model->errors()));
        }
    }

    public function getById(int $id){
        $cita_conversacion_model = $this->getCitaConversacionProvider();
        $cita_conversacion_model->select([
            'cita_conversacion.*',
            'conversacion.id_cliente',
            'conversacion.id_usuario',
            'usuario.usuario as nombre_usuario',
            'usuario_perfil.nombres as nombres_usuario',
            'usuario_perfil.apellidos as apellidos_usuario',
            'cliente.telefono',
            'cliente_perfil.nombre as nombre_cliente',
            'cliente_perfil.apellido as apellido_cliente',
            'cliente_perfil.nombre_completo as nombre_completo_cliente'
        ]);
        $cita_conversacion_model->join('conversacion', 'conversacion.id = cita_conversacion.id_conversacion', 'left');
        $cita_conversacion_model->join('usuario', 'usuario.id = conversacion.id_usuario', 'left');
        $cita_conversacion_model->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left');
        $cita_conversacion_model->join('cliente', 'cliente.id = conversacion.id_cliente', 'left');
        $cita_conversacion_model->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left');

        $cita = $cita_conversacion_model->find($id);
        return [
            'cita' => [
                'id' => $cita->id ?? null,
                'id_conversacion' => $cita->id_conversacion ?? null,
                'id_cita_erp' => $cita->id_cita_erp ?? null,
                'cita_estado' => $cita->cita_estado ?? null,
                'fecha_agendacion' => $cita->fecha_agendacion ?? null,
                'created_at' => $cita->created_at ?? null,
                'updated_at' => $cita->updated_at ?? null,
                'deleted_at' => $cita->deleted_at ?? null,
                'usuario' => [
                    'id' => $cita->id_usuario ?? null,
                    'usuario' => $cita->nombre_usuario ?? null,
                    'nombres' => $cita->nombres_usuario ?? null,
                    'apellidos' => $cita->apellidos_usuario ?? null,
                ],
                'cliente' => [
                    'id' => $cita->id_cliente ?? null,
                    'telefono' => $cita->telefono ?? null,
                    'nombre' => $cita->nombre_cliente ?? null,
                    'apellido' => $cita->apellido_cliente ?? null,
                    'nombre_completo' => $cita->nombre_completo_cliente ?? null,
                ]
            ]
        ];
    }


    public function listarCitasConversacion()
    {
        // Obtener parámetros de paginación y búsqueda desde GET si existen
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['perPage']) ? $_GET['perPage'] : 10;
        $search = isset($_GET['search']) ? $_GET['search'] : null;

        $cita_conversacion_model = $this->getCitaConversacionProvider();
        // Seleccionar solo los campos necesarios, incluyendo nombre de usuario y cliente
        $cita_conversacion_model->select([
            'cita_conversacion.*',
            'usuario.usuario as nombre_usuario',
            'cliente_perfil.nombre as nombre_cliente',
            'cliente_perfil.apellido as apellido_cliente'
        ]);
        $cita_conversacion_model->join('conversacion', 'conversacion.id = cita_conversacion.id_conversacion', 'left');
        $cita_conversacion_model->join('usuario', 'usuario.id = conversacion.id_usuario', 'left');
        $cita_conversacion_model->join('cliente', 'cliente.id = conversacion.id_cliente', 'left');
        $cita_conversacion_model->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left');
        $cita_conversacion_model->orderBy('cita_conversacion.created_at', 'DESC');
        if (!empty($search)) {
            $cita_conversacion_model->groupStart();
            $cita_conversacion_model->like('cita_conversacion.id_cita_erp', $search);
            $cita_conversacion_model->orLike('cita_conversacion.cita_estado', $search);
            $cita_conversacion_model->groupEnd();
        }
        if ($perPage === 'all') {
            $result = $cita_conversacion_model->findAll();
            $total = count($result);
            // Formatear resultado para incluir solo los campos requeridos
            $data = array_map(function($cita) {
                return [
                    'id' => $cita->id ?? null,
                    'id_conversacion' => $cita->id_conversacion ?? null,
                    'id_cita_erp' => $cita->id_cita_erp ?? null,
                    'cita_estado' => $cita->cita_estado ?? null,
                    'fecha_agendacion' => $cita->fecha_agendacion ?? null,
                    'created_at' => $cita->created_at ?? null,
                    'updated_at' => $cita->updated_at ?? null,
                    'deleted_at' => $cita->deleted_at ?? null,
                    'usuario' => [
                        'nombre' => $cita->nombre_usuario ?? null,
                    ],
                    'cliente' => [
                        'nombre' => $cita->nombre_cliente ?? null,
                        'apellido' => $cita->apellido_cliente ?? null,
                    ]
                ];
            }, $result);
            return [
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ]
            ];
        }
        $result = $cita_conversacion_model->paginate($perPage, 'default', $page);
        $pager = $cita_conversacion_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPage = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = ($total > 0) ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = ($from + count($result)) - 1;
        // Formatear resultado para incluir solo los campos requeridos
        $data = array_map(function($cita) {
            return [
                'id' => $cita->id ?? null,
                'id_conversacion' => $cita->id_conversacion ?? null,
                'id_cita_erp' => $cita->id_cita_erp ?? null,
                'cita_estado' => $cita->cita_estado ?? null,
                'fecha_agendacion' => $cita->fecha_agendacion ?? null,
                'created_at' => $cita->created_at ?? null,
                'updated_at' => $cita->updated_at ?? null,
                'deleted_at' => $cita->deleted_at ?? null,
                'usuario' => [
                    'nombre' => $cita->nombre_usuario ?? null,
                ],
                'cliente' => [
                    'nombre' => $cita->nombre_cliente ?? null,
                    'apellido' => $cita->apellido_cliente ?? null,
                ]
            ];
        }, $result);
        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'currentPage' => $currentPage,
                'from' => $from,
                'to' => $to,
                'hasNext' => $currentPage < $lastPage,
                'hasPrev' => $currentPage > 1,
            ],
        ];
    }
}
