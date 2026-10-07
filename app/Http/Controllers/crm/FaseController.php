<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use App\Models\crm\CondicionesFaseMover;
use App\Models\crm\Fase;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaseController extends Controller
{
    // Casos por columna en cada carga del Kanban. Fijo en el servidor: para cambiarlo, solo aqui
    private const CASOS_POR_PAGINA = 10;
    // Modo Lista: solo los tamaños del selector de la pantalla, y el tope del Excel
    private const TAMANIOS_LISTA = [10, 20, 30, 50, 100];
    private const TOPE_EXCEL_LISTA = 5000;

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

    // ------------------------------------------------------------------> KANBAN Y MODO LISTA PAGINADOS
    // Permisos y filtros viven en crm.fn_caso_tablero_filtrar_visibles, la misma para el Kanban y la lista.
    // Aqui solo se arman los casos con sus relaciones; listarfases() queda para sus otros usos.

    // Mismas relaciones que listarfases(): el JSON de cada caso no cambia de forma
    private function relacionesCaso(): array
    {
        return [
            'user',
            'userCreador',
            'clienteCrm',
            'tareas2',
            'actividad',
            'Etiqueta',
            'req_caso' => function ($query) {
                $query->orderBy('id', 'asc')->orderBy('orden', 'asc');
            },
            'estadodos',
            'tipocaso',
            'tiempo_caso',
            'agencia',
        ];
    }

    // Los with() se aplican una sola vez sobre los casos de la pagina, no sobre el tablero entero
    private function casosEnOrden(array $casoIds)
    {
        if (empty($casoIds)) {
            return collect();
        }

        return Caso::with($this->relacionesCaso())
            ->whereIn('id', $casoIds)
            ->orderByDesc('id') // whereIn no conserva el orden por si solo
            ->get();
    }

    // Kanban: los casos de la pagina repartidos por columna
    private function hidratarCasos(array $casoIds)
    {
        return $this->casosEnOrden($casoIds)->groupBy('fas_id');
    }

    private function fasesDelTablero(Request $request)
    {
        return Fase::with('condicionFaseMover')
            ->where('tab_id', (int) $request->input('tabId'))
            ->orderBy('orden', 'asc')
            ->get();
    }

    // Fecha vacia o invalida = ahora, como hacia Carbon::parse('') en listarfases
    private function fechaKanban($valor): string
    {
        try {
            return Carbon::parse($valor ?: 'now')->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return Carbon::now()->format('Y-m-d H:i:s');
        }
    }

    // Filtros de la barra, iguales en el Kanban y en la lista, con el tipo que espera la base:
    // estado, prioridad, agencias, buscador y tipo de tablero
    private function filtrosTablero(Request $request): array
    {
        $agencias = array_values(array_filter(array_map('intval', (array) $request->input('filtroAgencia', []))));

        return [
            (int) $request->input('filtroEstado', 0),
            (int) $request->input('filtroPrioridad', 0),
            $agencias ? '{' . implode(',', $agencias) . '}' : null,
            trim((string) $request->input('filtroTermino', '')),
            $request->input('tipoTablero', 'KANBAN'),
        ];
    }

    // El usuario sale del token, nunca del body: por Postman no se puede pedir por otro
    private function consultarKanban(Request $request, $user, $fasId, $cursorId, int $tamanio): array
    {
        $fila = DB::selectOne(
            'SELECT crm.fn_caso_kanban_listar_paginacion(?, ?, ?, ?, ?, ?, ?, ?, ?, ?::integer[], ?, ?) AS resultado',
            [
                (int) $request->input('tabId'), // siempre numerico: con letras fallaba la base
                $user->id,
                $this->fechaKanban($request->input('filtroFechaDesde')),
                $this->fechaKanban($request->input('filtroFechaHasta')),
                $fasId,
                $cursorId,
                $tamanio,
                ...$this->filtrosTablero($request),
            ]
        );

        return json_decode($fila->resultado, true);
    }

    // Modo Lista: los ids de una pagina (los mas nuevos primero), el total y el contexto del usuario
    private function consultarLista(Request $request, $user, int $pagina, int $tamanio): array
    {
        $fila = DB::selectOne(
            'SELECT crm.fn_caso_lista_listar_paginacion(?, ?, ?, ?, ?, ?, ?, ?, ?::integer[], ?, ?) AS resultado',
            [
                (int) $request->input('tabId'),
                $user->id,
                $this->fechaKanban($request->input('filtroFechaDesde')),
                $this->fechaKanban($request->input('filtroFechaHasta')),
                $pagina,
                $tamanio,
                ...$this->filtrosTablero($request),
            ]
        );

        return json_decode($fila->resultado, true);
    }

    // Lo que el front necesita para reaccionar si cambian sus permisos durante el dia
    private function contextoKanban(array $kanban): array
    {
        return [
            'usu_tipo' => $kanban['usu_tipo'],
            'solo_lectura' => $kanban['solo_lectura'],
            'agencias' => $kanban['agencias'],
        ];
    }

    // Errores de permisos que lanza la funcion (el codigo es para el front); null si es otro error
    private function errorPermisoKanban(\Throwable $e): ?array
    {
        $errores = [
            'SIN_ACCESO_TABLERO' => 'No tiene permisos para acceder a este tablero',
            'FASE_NO_PERTENECE_AL_TABLERO' => 'La fase no pertenece al tablero',
        ];

        foreach ($errores as $codigo => $mensaje) {
            if (str_contains($e->getMessage(), $codigo)) {
                return ['codigo' => $codigo, 'mensaje' => $mensaje];
            }
        }

        return null;
    }

    private function respuestaErrorKanban(\Throwable $e, string $contexto)
    {
        $log = new Funciones();
        $error = $this->errorPermisoKanban($e);
        $log->logError(FaseController::class, $error['mensaje'] ?? $contexto, $e);

        // El detalle queda en el log; al cliente no se le manda el error de la base (traia el SQL)
        return $error
            ? response()->json(RespuestaApi::returnResultado('error', $error['mensaje'], ['codigo' => $error['codigo']]))
            : response()->json(RespuestaApi::returnResultado('exception', 'Error al listar el tablero', null));
    }

    /**
     * Carga inicial del Kanban: fases con su primera pagina de casos, el total real de cada
     * columna y el contexto del usuario (rol, solo lectura, agencias). POST /api/crm/listFaseKanban
     */
    public function listFaseKanban(Request $request)
    {
        $log = new Funciones();
        $perPage = self::CASOS_POR_PAGINA; // fijo: lo que mande el front en perPage no se usa

        try {
            $user = auth('api')->user();

            // Se pide uno de mas por columna para saber si quedan paginas
            $kanban = $this->consultarKanban($request, $user, null, null, $perPage + 1);
            $porFase = collect($kanban['fases'])->keyBy('fas_id');

            $fases = $this->fasesDelTablero($request);

            $idsPorFase = [];
            $todosLosIds = [];

            foreach ($fases as $fase) {
                $ids = array_slice($porFase[$fase->id]['ids'] ?? [], 0, $perPage);
                $idsPorFase[$fase->id] = $ids;
                $todosLosIds = array_merge($todosLosIds, $ids);
            }

            $casosPorFase = $this->hidratarCasos($todosLosIds);

            foreach ($fases as $fase) {
                $ids = $idsPorFase[$fase->id];

                $fase->setRelation('caso', $casosPorFase->get($fase->id, collect())->values());
                $fase->total_casos = (int) ($porFase[$fase->id]['total_casos'] ?? 0);
                $fase->cursor_id = count($ids) ? end($ids) : null; // id mas bajo de la pagina
                $fase->hay_mas = count($porFase[$fase->id]['ids'] ?? []) > $perPage;
            }

            $log->logInfo(FaseController::class, 'Se listo con exito el kanban paginado');

            return response()->json(RespuestaApi::returnResultado(
                'success',
                'Se listo con exito',
                ['fases' => $fases] + $this->contextoKanban($kanban)
            ));
        } catch (\Throwable $e) {
            return $this->respuestaErrorKanban($e, 'Error al listar el kanban paginado');
        }
    }

    /**
     * Siguiente pagina de UNA columna. Paginacion por cursor (id < cursorId): a diferencia
     * del offset, no repite ni salta casos cuando entran o salen casos por WebSocket.
     * POST /api/crm/listCasosByFase
     */
    public function listCasosByFase(Request $request)
    {
        $log = new Funciones();
        $fasId = (int) $request->input('fasId');
        // Siempre numerico (con letras fallaba la base); vacio = primera pagina
        $cursorId = $request->filled('cursorId') ? (int) $request->input('cursorId') : null;
        $perPage = self::CASOS_POR_PAGINA; // fijo: lo que mande el front en perPage no se usa

        try {
            $user = auth('api')->user();

            // Se pide uno de mas para saber si quedan paginas, sin una segunda consulta
            $kanban = $this->consultarKanban($request, $user, $fasId, $cursorId, $perPage + 1);
            $columna = collect($kanban['fases'])->firstWhere('fas_id', $fasId);

            $ids = $columna['ids'] ?? [];
            $hayMas = count($ids) > $perPage;
            $ids = array_slice($ids, 0, $perPage);

            $casos = $this->hidratarCasos($ids)->get($fasId, collect())->values();

            $log->logInfo(FaseController::class, 'Se listo con exito la pagina de la fase ' . $fasId);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', [
                'fas_id' => $fasId,
                'caso' => $casos,
                'total_casos' => (int) ($columna['total_casos'] ?? 0),
                'cursor_id' => count($ids) ? end($ids) : $cursorId,
                'hay_mas' => $hayMas,
            ] + $this->contextoKanban($kanban)));
        } catch (\Throwable $e) {
            return $this->respuestaErrorKanban($e, 'Error al listar la pagina de la fase');
        }
    }

    /**
     * Modo Lista: una pagina de casos de todo el tablero (los mas nuevos primero), el total, las fases y
     * el contexto del usuario. Mismos permisos y filtros que el Kanban. POST /api/crm/listCasosLista
     */
    public function listCasosLista(Request $request)
    {
        $log = new Funciones();
        // Solo los tamaños del selector de la pantalla; cualquier otro valor se toma como 10
        $tamanio = (int) $request->input('tamanio');
        $tamanio = in_array($tamanio, self::TAMANIOS_LISTA, true) ? $tamanio : self::TAMANIOS_LISTA[0];
        $pagina = max(1, (int) $request->input('pagina', 1));

        try {
            $user = auth('api')->user();
            $lista = $this->consultarLista($request, $user, $pagina, $tamanio);

            $log->logInfo(FaseController::class, 'Se listo con exito el modo lista paginado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', [
                'casos' => $this->casosEnOrden($lista['ids']),
                'fases' => $this->fasesDelTablero($request),
                'total' => (int) $lista['total'],
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ] + $this->contextoKanban($lista)));
        } catch (\Throwable $e) {
            return $this->respuestaErrorKanban($e, 'Error al listar el modo lista paginado');
        }
    }

    /**
     * Excel del Modo Lista: todos los casos que cumplen los filtros, hasta 5.000 (los mas nuevos), solo con
     * las columnas del reporte, y el total para avisar si quedaron casos fuera. POST /api/crm/exportCasosLista
     */
    public function exportCasosLista(Request $request)
    {
        $log = new Funciones();

        try {
            $user = auth('api')->user();
            $lista = $this->consultarLista($request, $user, 1, self::TOPE_EXCEL_LISTA);

            $casos = empty($lista['ids'])
                ? collect()
                : Caso::select(['id', 'nombre', 'fecha_inicio', 'fecha_vencimiento', 'estado_2', 'prioridad', 'user_id', 'fas_id', 'identificacion', 'cliente', 'comprobante'])
                    ->with(['estadodos:id,nombre', 'user:id,usu_alias'])
                    ->whereIn('id', $lista['ids'])
                    ->orderByDesc('id')
                    ->get();

            $log->logInfo(FaseController::class, 'Se exporto con exito el modo lista: ' . $casos->count() . ' de ' . $lista['total']);

            return response()->json(RespuestaApi::returnResultado('success', 'Se exporto con exito', [
                'casos' => $casos,
                'fases' => Fase::where('tab_id', (int) $request->input('tabId'))->get(['id', 'nombre']),
                'total' => (int) $lista['total'],
                'tope' => self::TOPE_EXCEL_LISTA,
            ] + $this->contextoKanban($lista)));
        } catch (\Throwable $e) {
            return $this->respuestaErrorKanban($e, 'Error al exportar el modo lista');
        }
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
