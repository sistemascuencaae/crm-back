<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Requerimientos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequerimientoController extends Controller
{
    private $log;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->log = new Funciones();
    }

    public function listRequerimientosByFaseId($fase_id)
    {
        try {
            $requerimientos = Requerimientos::where('fase_id', $fase_id)->with('tipo_caso')->orderBy('orden', 'ASC')->get();

            $this->log->logInfo(RequerimientoController::class, 'Se listo con exito los requerimientos de la fase con el ID: ' . $fase_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimientos));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al listar los requerimientos de la fase con el ID: ' . $fase_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // QUITADO 28-oct: ahora va por crm.fn_requerimientos_predefinidos_* (reactivar = descomentar).
    // public function addRequerimientos(Request $request)
    // {
    //     try {
    //         $existeReqEquifax = DB::selectOne("SELECT 'equifax' AS campo_a_exist,
    //         EXISTS (SELECT 1 FROM crm.requerimientos_predefinidos WHERE fase_id = " . $request->input('fase_id') . " and tipo = 'equifax') AS existe_a,
    //         'equifax enrolamiento cliente' AS campo_b_exist,
    //         EXISTS (SELECT 1 FROM crm.requerimientos_predefinidos WHERE fase_id = " . $request->input('fase_id') . " and tipo = 'equifax enrolamiento cliente')
    //         AS existe_b;");
    //         //echo ('$existeReqEquifax: ' . json_encode($existeReqEquifax));
    //         //echo ('$request->all(): '.json_encode($request->all()));
    //         if ($existeReqEquifax) {
    //             if ($existeReqEquifax->existe_a == true && $request->input('tipo') == 'equifax') {
    //                 $this->log->logError(RequerimientoController::class, 'Ya existe el requerimiento tipo: ' . $request->input('tipo'));

    //                 return response()->json(RespuestaApi::returnResultado('error', 'Ya existe el requerimiento tipo: ' . $request->input('tipo'), ''));
    //             }
    //             if ($existeReqEquifax->existe_b == true && $request->input('tipo') == 'equifax enrolamiento cliente') {
    //                 $this->log->logError(RequerimientoController::class, 'Ya existe el requerimiento tipo: ' . $request->input('tipo'));

    //                 return response()->json(RespuestaApi::returnResultado('error', 'Ya existe el requerimiento tipo: ' . $request->input('tipo'), ''));
    //             }
    //         }

    //         // Verifica si ya existe un requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $request->input('fase_id'))
    //             ->where('tipo', 'perfil de cliente')
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             $this->log->logError(RequerimientoController::class, 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre);

    //             // Si ya existe un requerimiento con tipo "perfil de cliente" en esta fase
    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } else {
    //             // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
    //             $requerimiento = Requerimientos::create($request->all());

    //             $resultado = Requerimientos::where('fase_id', $requerimiento->fase_id)->with('tipo_caso')->orderBy('orden', 'ASC')->get();

    //             $this->log->logInfo(RequerimientoController::class, 'Se guardo con exito los requerimientos en la fase con el ID: ' . $request->input('fase_id'));

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $resultado));
    //         }

    //     } catch (Exception $e) {
    //         $this->log->logError(RequerimientoController::class, 'Error al guardar los requerimientos en la fase con el ID: ' . $request->input('fase_id'), $e);

    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function addRequerimientos(Request $request)
    // {
    //     try {
    //         // Verifica si ya existe un requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $request->input('fase_id'))
    //             ->where('tipo', 'perfil de cliente')
    //             ->first();

    //         // Verifica si ya existe un requerimiento con el mismo valor en el campo "orden"
    //         $existingOrden = Requerimientos::where('fase_id', $request->input('fase_id'))
    //             ->where('orden', $request->input('orden'))
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             // Si ya existe un requerimiento con tipo "perfil de cliente" en esta fase
    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } elseif ($existingOrden) {
    //             // Si ya existe un requerimiento con el mismo valor en el campo "orden"
    //             return response()->json(RespuestaApi::returnResultado('error', 'El campo "orden" ya existe en otro registro.', ''));
    //         } else {
    //             // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
    //             $requerimiento = Requerimientos::create($request->all());

    //             $resultado = Requerimientos::where('fase_id', $requerimiento->fase_id)->orderBy('orden', 'ASC')->get();

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $resultado));
    //         }

    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // QUITADO 28-oct: ahora va por crm.fn_requerimientos_predefinidos_* (reactivar = descomentar).
    // public function editRequerimientos(Request $request, $id)
    // {
    //     try {
    //         $requerimiento = Requerimientos::findOrFail($id);

    //         // Verifica si ya existe otro requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $requerimiento->fase_id)
    //             ->where('tipo', 'perfil de cliente')
    //             ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             $this->log->logError(RequerimientoController::class, 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre);

    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } else {

    //             $requerimiento->update($request->all());
    //             $resultado = Requerimientos::where('id', $requerimiento->id)->with('tipo_caso')->first();

    //             $this->log->logInfo(RequerimientoController::class, 'Se actualizo con exito el requerimiento con el ID: ' . $id);

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
    //         }
    //     } catch (Exception $e) {
    //         $this->log->logError(RequerimientoController::class, 'Error al actualizar el requerimiento con el ID: ' . $id, $e);

    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function editRequerimientos(Request $request, $id)
    // {
    //     try {
    //         $requerimiento = Requerimientos::findOrFail($id);

    //         // Verifica si ya existe otro requerimiento con el tipo "perfil de cliente" en la misma fase
    //         $existingRequerimiento = Requerimientos::where('fase_id', $requerimiento->fase_id)
    //             ->where('tipo', 'perfil de cliente')
    //             ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
    //             ->first();

    //         // Verifica si ya existe un requerimiento con el mismo valor en el campo "orden"
    //         $existingOrden = Requerimientos::where('fase_id', $requerimiento->fase_id)
    //             ->where('orden', $request->input('orden'))
    //             ->where('id', '!=', $id) // Excluye el propio requerimiento actual de la búsqueda
    //             ->first();

    //         if ($existingRequerimiento && $request->input('tipo') === 'perfil de cliente') {
    //             return response()->json(RespuestaApi::returnResultado('error', 'Ya EXISTE un registro de Perfil de cliente: ' . $existingRequerimiento->nombre, ''));
    //         } elseif ($existingOrden) {
    //             // Si ya existe un requerimiento con el mismo valor en el campo "orden"
    //             return response()->json(RespuestaApi::returnResultado('error', 'El campo "orden" ya existe en otro registro.', ''));
    //         } else {
    //             // Si no existe un requerimiento con tipo "perfil de cliente" o el nuevo tipo no es "perfil de cliente"
    //             $requerimiento->update($request->all());

    //             return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $requerimiento));
    //         }
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }


    // QUITADO 28-oct: ahora va por crm.fn_requerimientos_predefinidos_* (reactivar = descomentar).
    // public function deleteRequerimientos($id)
    // {
    //     try {
    //         $requerimiento = Requerimientos::findOrFail($id);

    //         $requerimiento->delete();

    //         $this->log->logInfo(RequerimientoController::class, 'Se elimino con exito el requerimiento con el ID: ' . $id);

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $requerimiento));
    //     } catch (Exception $e) {
    //         $this->log->logError(RequerimientoController::class, 'Error al eliminar el requerimiento con el ID: ' . $id, $e);

    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // 28-oct: agregar, editar y eliminar van por crm.fn_requerimientos_predefinidos_* (las validaciones, las fases
    // habilitadas y la auditoría forense están en la función); aquí se arma el jsonb y se responde como antes.
    private const CAMPOS_PAYLOAD = [
        'nombre', 'estado', 'tipo', 'fase_id', 'tab_id', 'user_id', 'valor_lista', 'requerido', 'orden',
        'acc_publico', 'desc_requerida', 'tipo_caso_id', 'minimo', 'maximo', 'editable', 'fases_habilitadas',
    ];

    public function addRequerimientos(Request $request)
    {
        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $r = $this->ejecutarFuncion('crm.fn_requerimientos_predefinidos_crear', $payload);
            if (!($r['success'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $r['message'] ?? 'No se pudo guardar el requerimiento.', ''));
            }

            $requerimiento = Requerimientos::find($r['id']);
            $resultado = Requerimientos::where('fase_id', $requerimiento->fase_id)->with('tipo_caso')->orderBy('orden', 'ASC')->get();

            $this->log->logInfo(RequerimientoController::class, 'Se guardo con exito el requerimiento con el ID: ' . $r['id']);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $resultado));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al guardar el requerimiento en la fase con el ID: ' . $request->input('fase_id'), $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editRequerimientos(Request $request, $id)
    {
        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $r = $this->ejecutarFuncion('crm.fn_requerimientos_predefinidos_modificar', $payload);
            if (!($r['success'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $r['message'] ?? 'No se pudo actualizar el requerimiento.', ''));
            }

            $resultado = Requerimientos::where('id', $id)->with('tipo_caso')->first();

            $this->log->logInfo(RequerimientoController::class, 'Se actualizo con exito el requerimiento con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al actualizar el requerimiento con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteRequerimientos(Request $request, $id)
    {
        try {
            $requerimiento = Requerimientos::findOrFail($id);

            $r = $this->ejecutarFuncion('crm.fn_requerimientos_predefinidos_eliminar', [
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ]);
            if (!($r['success'] ?? false)) {
                return response()->json(RespuestaApi::returnResultado('error', $r['message'] ?? 'No se pudo eliminar el requerimiento.', ''));
            }

            $this->log->logInfo(RequerimientoController::class, 'Se elimino con exito el requerimiento con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $requerimiento));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al eliminar el requerimiento con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Las fases del combo "Habilitado en las fases": las activas de tableros activos y, aunque no lo estén, las que
    // ya tiene el requerimiento (?incluir=536,540).
    public function listFasesAgrupadasPorTablero(Request $request)
    {
        try {
            $incluir = array_values(array_filter(
                array_map('intval', explode(',', (string) $request->query('incluir', ''))),
                function ($x) { return $x > 0; }
            ));
            $fases = DB::select('SELECT * FROM crm.fn_fase_listar_agrupadas_por_tablero(?::integer[])', ['{' . implode(',', $incluir) . '}']);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $fases));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al listar las fases por tablero', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las fases', $e->getMessage()));
        }
    }

    // La auditoría de los requerimientos de una fase (auditoria.logs_cambios): el resumen y el historial paginado, con
    // los eliminados.
    public function requerimientosAuditoria(Request $request, $fase_id)
    {
        try {
            $faseId   = (int) $fase_id;
            $pagina   = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio  = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($faseId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Fase no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM crm.fn_requerimientos_predefinidos_auditoria_resumen(?)', [$faseId]);
            $eventos = DB::select('SELECT * FROM crm.fn_requerimientos_predefinidos_auditoria_listar_paginacion(?, ?, ?, ?)',
                [$faseId, $pagina, $tamanio, $busqueda]);
            $total   = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total'   => (int) $total,
                'pagina'  => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            $this->log->logError(RequerimientoController::class, 'Error al cargar la auditoría de los requerimientos de la fase: ' . $fase_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría', $e->getMessage()));
        }
    }

    // Llama la función (nombre fijo, nunca del usuario) con el jsonb y devuelve su respuesta {success, message, id}.
    private function ejecutarFuncion(string $funcion, array $payload): array
    {
        $fila = DB::selectOne('SELECT ' . $funcion . '(?::jsonb) AS resultado', [json_encode($payload)]);

        return json_decode($fila->resultado ?? '{}', true) ?: [];
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios.
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id'     => $u->id ?? null,
            'usuario_login'  => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'request_id'     => (string) Str::uuid(),
        ];
    }
}
