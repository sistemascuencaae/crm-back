<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CondicionesFaseMover;
use App\Models\crm\Fase;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function list(Request $request)
    {
        $log = new Funciones();
        $tabId = $request->input('tabId');
        try {

            $data = $this->listarfases($tabId);

            $log->logInfo(FaseController::class, 'Se listo con exito las fases');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (\Throwable $e) {
            $log->logError(FaseController::class, 'Error al listar las fases', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Al listar', $e->getMessage()));
        }
    }

    public function faseActualById($faseId)
    {
        $log = new Funciones();
        try {
            $faseActual = Fase::with('condicionFaseMover', )->where('id', $faseId)->first();
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
            $data = $this->listarFases($id);

            $log->logInfo(FaseController::class, 'Se actualizo con exito el orden de las fases');

            return response()->json(RespuestaApi::returnResultado('success', 'Fase creada con exito', $data));
        } catch (Exception $e) {
            $log->logError(FaseController::class, 'Error al actualizar el orden de las fases', $e);

            return response()->json(RespuestaApi::returnResultado('exception', 'Error al crear fase', $e));
        }
    }


    public function listarfases($tabId)
    {
        $data = Fase::with([
            'caso.user',
            'caso.userCreador',
            'caso.clienteCrm',
            'caso.resumen',
            'caso.tareas' => function ($query) use ($tabId) {
                $query->where('tab_id', $tabId);
            },
            'caso.actividad',
            'caso.miembros.usuario.departamento',
            'caso.Etiqueta',
            'caso.req_caso' => function ($query) {
                $query->orderBy('id', 'asc')->orderBy('orden', 'asc');
            },
            'condicionFaseMover',
            'caso.estadodos'
        ])->where('tab_id', $tabId)
            ->orderBy('orden', 'asc')
            ->get();

        return $data;
    }
}
