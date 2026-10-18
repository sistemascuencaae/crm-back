<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Fase;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FaseController2 extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' =>
        []]);
    }

    public function listTableroCompleto(Request $request)
    {
        try {
            // Validar que el usuario pertenece al tablero
            $user = auth('api')->user();

            $tabId = $request->input('tabId');
            $fechaDesde = $request->input('filtroFechaDesde');
            $fechaHasta = $request->input('filtroFechaHasta');
            $tipoTablero = $request->input('tipoTablero');

            $tableroUser = DB::selectOne("SELECT * 
                                            FROM crm.tablero_user t
                                            WHERE t.user_id = ?
                                            AND t.tab_id = ?", [$user->id, $tabId]);

            if (!$tableroUser) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permisos para acceder a este tablero.', null));
            }

            $query = Fase::query()
                // Solo campos de fase que se usan en el HTML
                ->select('id', 'tab_id', 'nombre', 'color_id', 'generar_caso', 'orden')
                ->with([
                    // ⚠️ IMPORTANTE: El callback de 'caso' debe ir PRIMERO antes de las relaciones anidadas
                    // De lo contrario Laravel carga 'caso' sin el select() cuando procesa 'caso.user', etc.
                    'caso' => function ($query) use ($fechaDesde, $fechaHasta, $tipoTablero, $user) {
                        // SELECT ESPECÍFICO - Solo campos que se MUESTRAN EN EL CARD
                        $query->select(
                            'id',               // ID del caso
                            'fas_id',           // Para drag & drop
                            'nombre',           // Título del card (línea 314)
                            'descripcion',      // Descripción (línea 383)
                            'bloqueado',        // Ícono candado (líneas 270, 273)
                            'prioridad',        // Badge prioridad (línea 333)
                            'fecha_inicio',     // Fecha inicio (línea 352)
                            'fecha_vencimiento', // Fecha vencimiento (línea 354)
                            'user_id',          // FK para relación user
                            'tc_id',            // FK para relación tipocaso
                            'estado_2',         // FK para relación estadodos
                            'cliente',          // Nombre cliente (línea 368)
                            'identificacion',   // Cédula (línea 377)
                            'codigo_agencia'    // FK para relación agencia (línea 360)
                        );

                        // 1. FILTRO DE FECHAS
                        $query->whereBetween('fecha_inicio', [
                            Carbon::parse($fechaDesde)->startOfDay(),
                            Carbon::parse($fechaHasta)->endOfDay()
                        ]);

                        // 2. FILTRO POR AGENCIAS PERMITIDAS - JOIN DIRECTO (MÁS EFICIENTE)
                        // En lugar de hacer una consulta separada y usar whereIn, usamos whereExists
                        // CAST necesario porque alm_id es INTEGER y codigo_agencia es VARCHAR
                        $query->whereExists(function ($subquery) use ($user) {
                            $subquery->select(DB::raw(1))
                                ->from('crm.usuario_almacen as ua')
                                ->whereRaw('ua.alm_id = CAST(crm.caso.codigo_agencia AS INTEGER)')
                                ->where('ua.user_id', $user->id);
                        });

                        // 3. FILTROS POR TIPO DE USUARIO Y TABLERO
                        if ($tipoTablero === 'KANBAN') {

                            // Filtrar por categoria_caso = 1 (para usuarios 2, 4, 5)
                            // Usando JOIN directo en lugar de whereHas para evitar conflictos con select()
                            if (in_array($user->usu_tipo, [2, 4, 5])) {
                                $query->whereExists(function ($subquery) {
                                    $subquery->select(DB::raw(1))
                                        ->from('crm.tipo_caso as tc')
                                        ->whereColumn('tc.id', 'crm.caso.tc_id')
                                        ->where('tc.categoria_caso', 1);
                                });
                            }

                            // Excluir casos con estado TERMINADO
                            // Usando whereExists en lugar de whereDoesntHave para evitar conflictos
                            $query->whereNotExists(function ($subquery) {
                                $subquery->select(DB::raw(1))
                                    ->from('crm.estados_caso as ec')
                                    ->whereColumn('ec.id', 'crm.caso.estado_2')
                                    ->where('ec.nombre', 'TERMINADO');
                            });
                        }
                    },

                    // RELACIONES - Solo campos que se MUESTRAN EN EL CARD
                    'caso.user:id,usu_alias,en_linea',          // Usuario asignado (líneas 312, 324-328)
                    'caso.tipocaso:id,nombre,categoria_caso',    // Tipo caso (línea 315)
                    'caso.estadodos:id,nombre',                  // Estado (líneas 339-342)
                    'caso.agencia:codigo,nombre',                // Agencia (líneas 360-363) - opcional
                    'caso.Etiqueta:id,caso_id,nombre,color',     // Etiquetas (líneas 390-399)
                ])
                ->where('tab_id', $tabId);

            $data = $query->orderBy('orden', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (\Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error al listar', $e->getMessage()));
        }
    }
}
