<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CondicionesFaseMover;
use App\Models\crm\Fase;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' =>
        [
            'listFasesByTableroId',
        ]]);
    }

    public function list(Request $request)
    {
        $log = new Funciones();
        $tabId = $request->input('tabId');
        $fechaDesde = $request->input('filtroFechaDesde');
        $fechaHasta = $request->input('filtroFechaHasta');
        $tipoTablero = $request->input('tipoTablero');

        try {
            // Validar que el usuario pertenece al tablero
            $user = auth('api')->user();

            $tableroUser = DB::selectOne("SELECT * FROM crm.tablero_user t
                                            WHERE t.user_id = ? AND t.tab_id = ?", [$user->id, $tabId]);

            if (!$tableroUser) {
                $log->logError(FaseController::class, 'Usuario sin acceso al tablero: ' . $tabId);

                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permisos para acceder a este tablero', null));
            } else {

                // Si tiene acceso, continuar con el listado normal
                $data = $this->listarfases($tabId, $fechaDesde, $fechaHasta, $tipoTablero);

                $log->logInfo(FaseController::class, 'Se listo con exito las fases');

                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
            }
        } catch (\Throwable $e) {
            $log->logError(FaseController::class, 'Error al listar las fases', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Al listar', $e->getMessage()));
        }
    }

    public function faseActualById($faseId)
    {
        $log = new Funciones();
        try {
            $faseActual = Fase::with('condicionFaseMover',)->where('id', $faseId)->first();
            if ($faseActual) {
                $log->logInfo(FaseController::class, 'Fase actual');

                return response()->json(RespuestaApi::returnResultado('success', 'Fase actual', $faseActual));
            }

            $log->logError(FaseController::class, 'Error al obtener la fase actual');

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener fase actual', $faseId));
        } catch (\Throwable $e) {
            $log->logError(FaseController::class, 'Error al obtener la fase actual', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Error al obtener fase actual', $e->getMessage()));
        }
    }

    // public function faseById($faseId)
    // {
    //     $jsonData = $request->input('jsonData');
    //     $decodedData = json_decode($jsonData);

    //     $filteredArray = array_map(function ($innerArray) {
    //         return array_filter($innerArray, function ($value) {
    //             return $value % 2 == 0; // Filtrar números pares en el array interno
    //         });
    //     }, $decodedData->data);

    //     return response()->json(['filteredArray' => $filteredArray]);
    // }

    public function edit(Request $request)
    {
        //$data = Fase::with('caso.user','caso.clienteCrm', 'caso.resumen', 'caso.tareas','caso.actividad')->where('tab_id',$tableroId)->get();
        $log = new Funciones();
        try {

            $data = DB::transaction(function () use ($request) {
                $idFase = $request->input('id');
                $faseUpd = Fase::find($idFase);
                $faseUpd->update([
                    "nombre" => $request->input('nombre'),
                    "descripcion" => $request->input('descripcion'),
                    "estado" => $request->input('estado'),
                    "orden" => $request->input('orden'),
                    "generar_caso" => $request->input('generar_caso'),
                    "color_id" => $request->input('color_id'),
                    "aprobar_credito" => $request->input('aprobar_credito'),
                ]);

                $condicion = CondicionesFaseMover::find($request->input('condicionId'));
                $idsFaseMover = json_encode($request->input('idsFaseMover'));
                if ($condicion) {
                    $condicion->parametro = $idsFaseMover;
                    $condicion->save();
                } else {
                    $condiDos = CondicionesFaseMover::create([
                        "parametro" => $idsFaseMover,
                    ]);

                    $faseUpd->cnd_mover_id = $condiDos->id;
                    $faseUpd->save();
                }
                $faseSave = Fase::with([
                    'caso.req_caso',
                    'condicionFaseMover'
                ])->find($idFase);


                return $faseSave;
            });

            $log->logInfo(FaseController::class, 'Se actualizo con exito la fase');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (\Throwable $e) {
            $log->logError(FaseController::class, 'Error al actualizar la fase.', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Error al actualizar fase.', $e->getMessage()));
        }
    }
    public function add(Request $request)
    {
        $log = new Funciones();
        try {

            $data = DB::transaction(function () use ($request) {
                $idsFaseMover = json_encode($request->input('idsFaseMover'));
                $condicion = CondicionesFaseMover::create([
                    "parametro" => $idsFaseMover,
                ]);

                //$faseCreada = Fase::create($request->all());
                $faseCreada = new Fase($request->all());
                $faseCreada->cnd_mover_id = $condicion->id;
                $faseCreada->save();




                $fase = Fase::with([
                    'caso.req_caso',
                    'condicionFaseMover'
                ])->find($faseCreada->id);


                return $fase;
            });

            $log->logInfo(FaseController::class, 'Se guardo con exito la fase');

            return response()->json(RespuestaApi::returnResultado('success', 'Fase creada con exito', $data));
        } catch (Exception $e) {
            $log->logError(FaseController::class, 'Error al guardar la fase', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Error al crear fase', $e));
        }
    }

    public function actualizarOrdenFases(Request $request)
    {
        $log = new Funciones();
        try {
            $listaFases = $request->all();
            $fechaDesde = $request->input('filtroFechaDesde');
            $fechaHasta = $request->input('filtroFechaHasta');
            $id = DB::transaction(function () use ($listaFases) {
                $tabId = 0;
                foreach ($listaFases as $item) {
                    $tabId = $item['tab_id'];
                    $fase = Fase::find($item['id']);
                    $fase->orden = $item['orden'];
                    $fase->save();
                }

                return $tabId;
            });
            $data = $this->listarFases($id, $fechaDesde, $fechaHasta);

            $log->logInfo(FaseController::class, 'Se actualizo con exito el orden de las fases');

            return response()->json(RespuestaApi::returnResultado('success', 'Fase creada con exito', $data));
        } catch (Exception $e) {
            $log->logError(FaseController::class, 'Error al actualizar el orden de las fases', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Error al crear fase', $e));
        }
    }
    public function listarfases1($tabId, $fechaInicio, $fechaFin, $tipoTablero = null)
    {
        $data = Fase::with([
            'caso.user',
            'caso.userCreador',
            'caso.clienteCrm',
            // 'caso.resumen',
            'caso.tareas' => function ($query) use ($tabId) {
                $query->where('tab_id', $tabId);
            },
            'caso.actividad',
            // 'caso.miembros.usuario.departamento',
            'caso.Etiqueta',
            'caso.req_caso' => function ($query) {
                $query->orderBy('id', 'asc')->orderBy('orden', 'asc');
            },
            'condicionFaseMover',
            'caso.tipocaso',
            'caso.agencia',
            //'caso.estadodos',
            'caso.estadodos' => function ($query) use ($tipoTablero) {
                if ($tipoTablero == 'KANBAN') {
                    $query->where('nombre', '<>', 'TERMINADO');
                } else {
                    $query->whereNotNull('nombre');
                }
            },
            'caso' => function ($query) use ($fechaInicio, $fechaFin) {
                $query->whereBetween('created_at', [
                    Carbon::parse($fechaInicio)->startOfDay(),
                    Carbon::parse($fechaFin)->endOfDay(),
                ]);
            },
        ])->where('tab_id', $tabId)
            ->orderBy('orden', 'asc')
            ->get();

        return $data;
    }

    public function listFasesByTableroId(Request $request)
    {
        try {
            $query = Fase::query()
                ->with([
                    'caso.user',
                    'caso.userCreador',
                    'caso.clienteCrm',
                    // 'caso.resumen',
                    'caso.tareas' => function ($query) use ($request) {
                        $query->where('tab_id', $request->tabId);
                    },
                    'caso.actividad',
                    // 'caso.miembros.usuario.departamento',
                    'caso.Etiqueta',
                    'caso.req_caso' => function ($query) {
                        $query->orderBy('id', 'asc')->orderBy('orden', 'asc');
                    },
                    'condicionFaseMover',
                    'caso.estadodos',
                    'caso.tipocaso',
                    'caso.tiempo_caso',
                    'caso.agencia',
                    'caso' => function ($query) use ($request) {
                        // $query->whereBetween('fecha_vencimiento', [
                        //     Carbon::parse($request->fechaInicio)->startOfDay(),
                        //     Carbon::parse($request->fechaFin)->endOfDay(),
                        // ]);

                        $query->whereBetween('fecha_inicio', [Carbon::parse($request->fechaInicio)->startOfDay(), Carbon::parse($request->fechaFin)->endOfDay()]);
                    },
                ])
                ->where('tab_id', $request->tabId);

            $data = $query->orderBy('orden', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function listarfases($tabId, $fechaInicio, $fechaFin, $tipoTablero = null)
    {
        $user = auth('api')->user();

        $permisosAgencias = DB::SELECT("SELECT a.alm_id 
                                            FROM crm.usuario_almacen a
                                        WHERE a.user_id = ?", [$user->id]);

        $stringAlmIdAgencias = collect($permisosAgencias)->pluck('alm_id')->toArray();

        $query = Fase::query()
            ->with([
                'caso.user', // --------------------------------------------------------------------
                'caso.userCreador',
                'caso.clienteCrm',
                // 'caso.resumen',
                'caso.tareas2',
                'caso.actividad',
                // 'caso.miembros.usuario.departamento',
                'caso.Etiqueta',
                'caso.req_caso' => function ($query) {
                    $query->orderBy('id', 'asc')->orderBy('orden', 'asc');
                },
                'condicionFaseMover',
                'caso.estadodos',
                'caso.tipocaso', //------------------------------------------------------------------------------
                'caso.tiempo_caso',
                'caso.agencia',
                'caso' => function ($query) use ($fechaInicio, $fechaFin, $tipoTablero, $user, $stringAlmIdAgencias) { // Se ocupa para poder mostrar el numero de casos que esta en la fase (Esto es un array)

                    // SI NO ES VACIO (Filtro por agencias asignadas)
                    if (!empty($stringAlmIdAgencias)) {
                        $query->whereBetween('fecha_inicio', [Carbon::parse($fechaInicio)->startOfDay(), Carbon::parse($fechaFin)->endOfDay()]);

                        $query->whereIn('codigo_agencia', $stringAlmIdAgencias);

                        // Filtros por tipo de usuario
                        if ($tipoTablero === 'KANBAN') {
                            switch ($user->usu_tipo) {
                                case 2: // Administrador
                                    // JGSJ Solo incluir casos con categoria_caso = 1
                                    $query->whereHas('tipocaso', function ($q) {
                                        $q->where('categoria_caso', 1);
                                    });
                                    // Si el tipo de tablero es KANBAN, se excluyen los casos con estado TERMINADO
                                    $query->whereDoesntHave('estadodos', function ($subquery) {
                                        // $subquery->where('nombre', 'TERMINADO');
                                        $subquery->whereIn('nombre', ['TERMINADO', 'Rechazado']);
                                    });
                                    break;


                                case 3: // Super usuario
                                    // Si el tipo de tablero es KANBAN, se excluyen los casos con estado TERMINADO y Rechazado
                                    $query->whereDoesntHave('estadodos', function ($subquery) {
                                        $subquery->whereIn('nombre', ['TERMINADO', 'Rechazado']);
                                    });
                                    break;


                                case 4: // Usuario común
                                    // JGSJ Solo incluir casos con categoria_caso = 1
                                    $query->whereHas('tipocaso', function ($q) {
                                        $q->where('categoria_caso', 1);
                                    });
                                    // Si el tipo de tablero es KANBAN, se excluyen los casos con estado TERMINADO y Rechazado
                                    $query->whereDoesntHave('estadodos', function ($subquery) {
                                        $subquery->whereIn('nombre', ['TERMINADO', 'Rechazado']);
                                    });
                                    break;


                                case 5: // Moderador
                                    // JGSJ Solo incluir casos con categoria_caso = 1
                                    $query->whereHas('tipocaso', function ($q) {
                                        $q->where('categoria_caso', 1);
                                    });
                                    // Si el tipo de tablero es KANBAN, se excluyen los casos con estado TERMINADO
                                    $query->whereDoesntHave('estadodos', function ($subquery) {
                                        $subquery->whereIn('nombre', ['TERMINADO', 'Rechazado']);
                                    });
                                    break;
                            }
                        }
                    } else {
                        $query->whereRaw('1=0'); // condición siempre falsa, para que no me retorne ningun caso si no hay agencias asignadas
                        return;
                    }
                },
            ])
            ->where('tab_id', $tabId);

        $data = $query->orderBy('orden', 'asc')->get();

        return $data;
    }

    public function agenciasCrmUsuario()
    {
        try {
            $user = auth('api')->user();

            $data = DB::SELECT("SELECT a.alm_id, ag.nombre
                                    FROM crm.usuario_almacen a
                                        JOIN crm.agencia ag ON a.alm_id::text = ag.codigo
                                WHERE a.user_id = ?
                                ORDER BY ag.nombre ASC;", [$user->id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
