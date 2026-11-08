<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\EstadosFormulas;
use App\Models\configuracion\TipoCasoTablero;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadosFormulasController extends Controller
{
    // 07-nov: textos de los códigos que levantan las funciones del editor del flujo (RAISE EXCEPTION 'CODIGO').
    private const MENSAJES_ERROR = [
        'REQUIERE_NOMBRE' => 'Escribe el nombre.',
        'NOMBRE_TIPO_MUY_LARGO' => 'El nombre del tipo de caso no puede pasar de 250 caracteres.',
        'NOMBRE_DUPLICADO' => 'Ya existe un tipo de caso con ese nombre (puede estar inactivo). Elige otro.',
        'TIPO_CASO_NO_EXISTE' => 'El tipo de caso no existe.',
        'TABLERO_NO_ES_DEL_TIPO' => 'El tablero no es de este tipo de caso.',
        'NOMBRE_FASE_MUY_LARGO' => 'El nombre de la fase no puede pasar de 500 caracteres.',
        'DESCRIPCION_MUY_LARGA' => 'La descripción no puede pasar de 200 caracteres.',
        'REQUIERE_COLOR' => 'Elige el color de la fase.',
        'COLOR_INVALIDO' => 'El color elegido no es de la paleta de las fases.',
        'FASE_DUPLICADA' => 'Ya existe una fase con ese nombre en el tablero (puede estar inactiva).',
        'FASES_NO_COINCIDEN' => 'Las fases del tablero cambiaron mientras ordenabas. Vuelve a abrir Ordenar fases.',
        'FASE_REPETIDA' => 'Una fase viene dos veces en el orden.',
        // 07-nov (etapa 2): configuración del tablero, tableros del tipo, copiar fórmulas y permisos del perfil.
        'SIN_PERMISO' => 'No tienes permiso para esta acción.',
        'FASE_NO_EXISTE' => 'La fase no existe o está inactiva.',
        'REQUIERE_DESCRIPCION' => 'Escribe la descripción.',
        'CONDICIONES_INVALIDAS' => 'Las condiciones de movimientos solo pueden ser otras fases del mismo tablero.',
        'ESTADO_NO_EXISTE' => 'El estado no existe.',
        'ESTADO_PREDETERMINADO' => 'PENDIENTE y TERMINADO son los estados de siempre del tablero: no se editan ni se eliminan.',
        'NOMBRE_ESTADO_MUY_LARGO' => 'El nombre del estado no puede pasar de 250 caracteres.',
        'ESTADO_DUPLICADO' => 'Ya existe un estado con ese nombre en el tablero.',
        'ESTADO_EN_USO' => 'El estado está en uso y no se puede desactivar ni eliminar:',
        'RESPUESTA_NO_EXISTE' => 'La respuesta no existe.',
        'NOMBRE_RESPUESTA_MUY_LARGO' => 'El nombre de la respuesta no puede pasar de 250 caracteres.',
        'REQUIERE_FASE' => 'Elige la fase.',
        'FASE_NO_ES_DEL_TABLERO' => 'La fase no es de este tablero.',
        'RESPUESTA_DUPLICADA' => 'Ya existe una respuesta con ese nombre en esa fase.',
        'RESPUESTA_EN_USO' => 'La respuesta está en uso:',
        'TABLERO_NO_EXISTE' => 'El tablero no existe.',
        'TABLERO_INACTIVO' => 'El tablero está inactivo.',
        'TABLERO_YA_ES_DEL_TIPO' => 'El tablero ya es de este tipo de caso.',
        'TABLERO_EN_USO' => 'El tipo de caso todavía usa este tablero:',
        'FASE_ORIGEN_NO_EXISTE' => 'La fase de donde se copian las fórmulas no existe.',
    ];

    // 07-nov: el menú del editor, de donde salen las banderas Nuevo (create), Modificar (edit) y Eliminar (delete).
    private const MENU_EDITOR = 'FLUJOS-TIPO-CASO';

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listEstadosFormulasByTablero($id)
    {
        $log = new Funciones();
        try {
            $respuestas = EstadosFormulas::where('tab_id', $id)->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')->get();

            $log->logInfo(EstadosFormulasController::class, 'Se listo con exito los estados del tablero con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los estados del tablero con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listEstadosFormulasByTabIdByTipoCasoId($tab_id, $tipo_caso_id)
    {
        $log = new Funciones();
        try {
            // original
            // $respuestas = EstadosFormulas::where('tab_id', $id)->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima','tipoCaso')->get();

            // nuevo cambio
            $respuestas = TipoCasoTablero::where('tipo_caso_id', $tipo_caso_id)->where('tab_id', $tab_id)
                ->with(
                    'estado_formula.estado_actual',
                    'estado_formula.fase_actual',
                    'estado_formula.respuesta_caso',
                    'estado_formula.estado_proximo',
                    'estado_formula.tablero_proximo',
                    'estado_formula.fase_proxima',
                    'estado_formula.tipoCaso'
                )
                ->get();

            $log->logInfo(EstadosFormulasController::class, 'Se listo con exito los estados del tablero con el ID: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los estados del tablero con el ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // 27-oct: flujo de un tipo de caso para Demos > Flujos por tipo de caso.
    // 27-oct (rediseño): todo el diagrama en un JSON (crm.fn_tipo_caso_flujo): tipo_caso, tableros, fases (con la de
    // creación y su estado inicial), formulas y requerimientos.
    public function listEstadosFormulasFlujoByTipoCasoId($tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_flujo(?) AS flujo', [(int) $tipo_caso_id]);
            $flujo = $fila && $fila->flujo ? json_decode($fila->flujo) : null;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $flujo));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar el flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar el flujo del tipo de caso', $e->getMessage()));
        }
    }

    // 28-oct: editor del flujo (Demos > Flujos por tipo de caso). Los combos de una fórmula: por tablero del tipo, sus
    // fases, estados y respuestas activas (crm.fn_tipo_caso_flujo_catalogos).
    public function listFlujoCatalogosByTipoCasoId($tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_flujo_catalogos(?) AS catalogos', [(int) $tipo_caso_id]);
            $catalogos = $fila && $fila->catalogos ? json_decode($fila->catalogos) : null;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $catalogos));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los catálogos del flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar los catálogos del flujo', $e->getMessage()));
        }
    }

    // 28-oct: botón Guardar del editor del flujo: todos los cambios juntos. Las reglas y los mensajes están en
    // crm.fn_tipo_caso_flujo_guardar. data = {estado: success | confirmar | error, errores, avisos, version, recargar}.
    // "confirmar" llega con status success: no se guardó nada y la pantalla pregunta antes de mandar confirmar = true.
    public function guardarFlujoTipoCaso(Request $request, $tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $cambios = $request->input('cambios');
            // 07-nov: quién hace el cambio (IP, navegador y request_id) para la auditoría de los requerimientos.
            if (is_array($cambios)) {
                $cambios['auditoria'] = $this->contextoAuditoriaForense($request);
            }
            $fila = DB::selectOne(
                'SELECT crm.fn_tipo_caso_flujo_guardar(?, ?, ?::json, ?::boolean, ?) AS resultado',
                [
                    (int) $tipo_caso_id,
                    (string) $request->input('version', ''),
                    json_encode(is_array($cambios) ? $cambios : new \stdClass(), JSON_UNESCAPED_UNICODE),
                    $request->boolean('confirmar') ? 'true' : 'false',
                    auth('api')->user()->id ?? null,
                ]
            );
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;
            $estado = $resultado->status ?? 'error';
            $mensaje = $resultado->mensaje ?? 'No se pudo guardar el flujo.';
            $data = [
                'estado' => $estado,
                'errores' => $resultado->errores ?? [],
                'avisos' => $resultado->avisos ?? [],
                'version' => $resultado->version ?? null,
                'recargar' => $resultado->recargar ?? false,
                // 07-nov: SIN_PERMISO cuando el perfil no tiene Nuevo, Modificar o Eliminar para lo que trae el Guardar.
                'codigo' => $resultado->codigo ?? null,
            ];

            if ($estado === 'error') {
                $log->logError(EstadosFormulasController::class, 'No se guardó el flujo del tipo de caso con el ID: ' . $tipo_caso_id . ': ' . $mensaje);

                return response()->json(RespuestaApi::returnResultado('error', $mensaje, $data));
            }

            if ($estado === 'success') {
                $log->logInfo(EstadosFormulasController::class, 'Se guardó el flujo del tipo de caso con el ID: ' . $tipo_caso_id . ': ' . $mensaje);
            }

            return response()->json(RespuestaApi::returnResultado('success', $mensaje, $data));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al guardar el flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar el flujo.', $e->getMessage()));
        }
    }

    // 28-oct: clona el tipo de caso con todo su flujo (crm.fn_tipo_caso_clonar): sale activo y con el nombre + "_clon".
    // data = {id, nombre} del tipo nuevo.
    // Version 2.0 (07-nov): con el nombre que elige el usuario (body: nombre); sin nombre, como antes. Los errores del
    // nombre llegan como código (MENSAJES_ERROR).
    public function clonarTipoCaso(Request $request, $tipo_caso_id)
    {
        // 07-nov: clonar es Nuevo (create) del menú del editor.
        if ($sinPermiso = $this->sinPermiso('create')) {
            return $sinPermiso;
        }
        $log = new Funciones();
        try {
            // DESACTIVADO 07-nov: $fila = DB::selectOne('SELECT crm.fn_tipo_caso_clonar(?) AS resultado', [(int) $tipo_caso_id]);
            $nombre = $request->has('nombre') ? (string) $request->input('nombre', '') : null;
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_clonar(?::integer, ?::text) AS resultado', [(int) $tipo_caso_id, $nombre]);
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;

            if (($resultado->status ?? 'error') !== 'success') {
                $mensaje = $resultado->mensaje ?? 'No se pudo clonar el tipo de caso.';
                $log->logError(EstadosFormulasController::class, 'No se clonó el tipo de caso con el ID: ' . $tipo_caso_id . ': ' . $mensaje);

                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }

            $log->logInfo(EstadosFormulasController::class, 'Se clonó el tipo de caso con el ID: ' . $tipo_caso_id . ' en el ID: ' . $resultado->id);

            return response()->json(RespuestaApi::returnResultado('success', $resultado->mensaje, ['id' => $resultado->id, 'nombre' => $resultado->nombre]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo clonar el tipo de caso.', 'Error al clonar el tipo de caso con el ID: ' . $tipo_caso_id);
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al clonar el tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo clonar el tipo de caso.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov): el nombre que propone Clonar (crm.fn_tipo_caso_sugerir_nombre_clon): <nombre>_clon, _clon2...
    // data = {nombre}.
    public function sugerirNombreClonTipoCaso($tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_sugerir_nombre_clon(?::integer) AS nombre', [(int) $tipo_caso_id]);
            if (!$fila || $fila->nombre === null) {
                return response()->json(RespuestaApi::returnResultado('error', self::MENSAJES_ERROR['TIPO_CASO_NO_EXISTE'], null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', ['nombre' => $fila->nombre]));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al sugerir el nombre del clon del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo proponer el nombre del clon.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov): los tipos de caso que usan un tablero (crm.fn_tablero_tipos_caso_listar): a quiénes les cambia el
    // kanban al crear u ordenar sus fases desde el editor del flujo. data = [{id, nombre, estado}].
    // 07-nov: se llamaba listTiposCasoByTableroId, como la ruta de TableroController que tapaba: renombrado.
    public function listTiposCasoUsanTablero($tab_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tablero_tipos_caso_listar(?::integer) AS tipos', [(int) $tab_id]);
            $tipos = $fila && $fila->tipos ? json_decode($fila->tipos) : [];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tipos));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los tipos de caso del tablero con el ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron leer los tipos de caso del tablero.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov): Nueva fase desde el editor del flujo (crm.fn_fase_crear, que valida todo). La fase es del
    // tablero: sale en el kanban de todos los tipos de caso que lo usan. Se guarda al momento. data = {id, tab_id, nombre, orden}.
    public function crearFaseFlujo(Request $request, $tipo_caso_id)
    {
        // 07-nov: Nuevo (create) del menú del editor.
        if ($sinPermiso = $this->sinPermiso('create')) {
            return $sinPermiso;
        }
        $log = new Funciones();
        try {
            $datos = [
                'tipo_caso_id' => (int) $tipo_caso_id,
                'tab_id' => $request->input('tab_id'),
                'nombre' => $request->input('nombre'),
                'descripcion' => $request->input('descripcion'),
                'color_id' => $request->input('color_id'),
                'generar_caso' => $request->boolean('generar_caso'),
                // 07-nov (etapa 2): las condiciones de movimientos, como en Nueva fase del tablero.
                'condiciones' => $request->input('condiciones'),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];
            $fila = DB::selectOne('SELECT crm.fn_fase_crear(?::jsonb) AS resultado', [json_encode($datos, JSON_UNESCAPED_UNICODE)]);
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;
            $log->logInfo(EstadosFormulasController::class, 'Se creó la fase ID: ' . ($resultado->id ?? '?') . ' desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se creó la fase.', $resultado));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo crear la fase.', 'Error al crear una fase desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id);
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al crear una fase desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo crear la fase.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov): ordenar las fases de un tablero desde el editor del flujo (crm.fn_fase_ordenar): body
    // {tab_id, fases: [id, ...]} con todas las activas menos la fija, en el orden nuevo. Se guarda al momento.
    // data = {tab_id, cambiadas, fases: [{id, nombre, orden, fija}]}.
    public function ordenarFasesFlujo(Request $request, $tipo_caso_id)
    {
        // 07-nov: ordenar es Modificar (edit) del menú del editor.
        if ($sinPermiso = $this->sinPermiso('edit')) {
            return $sinPermiso;
        }
        $log = new Funciones();
        try {
            $fases = $request->input('fases');
            $datos = [
                'tipo_caso_id' => (int) $tipo_caso_id,
                'tab_id' => $request->input('tab_id'),
                'fases' => is_array($fases) ? array_values($fases) : null,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];
            $fila = DB::selectOne('SELECT crm.fn_fase_ordenar(?::jsonb) AS resultado', [json_encode($datos, JSON_UNESCAPED_UNICODE)]);
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;
            $cambiadas = (int) ($resultado->cambiadas ?? 0);
            $log->logInfo(EstadosFormulasController::class, 'Se ordenaron las fases del tablero ' . ($resultado->tab_id ?? '?') . ' (' . $cambiadas . ' cambiaron) desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id);

            return response()->json(RespuestaApi::returnResultado('success', $cambiadas ? 'Se guardó el orden de las fases.' : 'El orden ya estaba así.', $resultado));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo guardar el orden de las fases.', 'Error al ordenar las fases desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id);
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al ordenar las fases desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar el orden de las fases.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov, etapa 2): fases (con condiciones de movimientos), estados y respuestas de un tablero, con en
    // qué se usa cada uno (crm.fn_tablero_configuracion_flujo_listar). data = {tablero, tipos, fases, estados, respuestas}.
    public function listConfiguracionTableroFlujo($tab_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tablero_configuracion_flujo_listar(?::integer) AS datos', [(int) $tab_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $fila && $fila->datos ? json_decode($fila->datos) : null));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar la configuración del tablero con el ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo leer la configuración del tablero.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov, etapa 2): editar una fase (crm.fn_fase_modificar): nombre, descripción, color, permitir crear
    // tarjetas y condiciones de movimientos. Modificar (edit).
    public function modificarFaseFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'edit', 'fn_fase_modificar',
            ['id', 'nombre', 'descripcion', 'color_id', 'generar_caso', 'condiciones'], 'Se guardó la fase.', 'No se pudo guardar la fase.');
    }

    // Version 1.0 (07-nov, etapa 2): estados del tablero (crm.fn_estados_caso_crear, _modificar y _eliminar).
    public function crearEstadoFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'create', 'fn_estados_caso_crear',
            ['tab_id', 'nombre', 'estado'], 'Se creó el estado.', 'No se pudo crear el estado.');
    }

    public function modificarEstadoFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'edit', 'fn_estados_caso_modificar',
            ['id', 'nombre', 'estado'], 'Se guardó el estado.', 'No se pudo guardar el estado.');
    }

    public function eliminarEstadoFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'delete', 'fn_estados_caso_eliminar',
            ['id'], 'Se eliminó el estado.', 'No se pudo eliminar el estado.');
    }

    // Version 1.0 (07-nov, etapa 2): respuestas del tablero (crm.fn_respuestas_caso_crear, _modificar y _eliminar).
    public function crearRespuestaFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'create', 'fn_respuestas_caso_crear',
            ['tab_id', 'fase_id', 'nombre', 'estado'], 'Se creó la respuesta.', 'No se pudo crear la respuesta.');
    }

    public function modificarRespuestaFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'edit', 'fn_respuestas_caso_modificar',
            ['id', 'nombre', 'fase_id', 'estado'], 'Se guardó la respuesta.', 'No se pudo guardar la respuesta.');
    }

    public function eliminarRespuestaFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'delete', 'fn_respuestas_caso_eliminar',
            ['id'], 'Se eliminó la respuesta.', 'No se pudo eliminar la respuesta.');
    }

    // Version 1.0 (07-nov, etapa 2): los tableros del tipo y los que se pueden agregar (crm.fn_tipo_caso_tableros_listar).
    // data = {asignados: [{id, nombre, estado, asignado, formulas, requerimientos}], disponibles: [{id, nombre}]}.
    public function listTablerosTipoCasoFlujo($tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_tableros_listar(?::integer) AS datos', [(int) $tipo_caso_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $fila && $fila->datos ? json_decode($fila->datos) : null));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al listar los tableros del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron leer los tableros del tipo de caso.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov, etapa 2): agregar (Nuevo) o quitar (Eliminar) un tablero del tipo de caso.
    public function agregarTableroTipoCasoFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'create', 'fn_tipo_caso_tablero_agregar',
            ['tab_id'], 'Se agregó el tablero al tipo de caso.', 'No se pudo agregar el tablero.');
    }

    public function quitarTableroTipoCasoFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'delete', 'fn_tipo_caso_tablero_quitar',
            ['tab_id'], 'Se quitó el tablero del tipo de caso.', 'No se pudo quitar el tablero.');
    }

    // Version 1.0 (07-nov, etapa 2): cómo quedarían en una fase de este tipo las fórmulas de otra fase o tipo
    // (crm.fn_estados_formulas_copiar_preparar). Solo lee; copiar es Nuevo (create).
    public function prepararCopiaFormulasFlujo(Request $request, $tipo_caso_id)
    {
        return $this->accionEditor($request, $tipo_caso_id, 'create', 'fn_estados_formulas_copiar_preparar',
            ['fase_id', 'tipo_origen_id', 'fase_origen_id', 'existentes'], 'Se listo con éxito', 'No se pudieron leer las fórmulas para copiar.');
    }

    // Version 1.0 (07-nov, etapa 3): revisar TODO el flujo con los cambios sin guardar (crm.fn_tipo_caso_flujo_revisar).
    // body {cambios} (la forma del Guardar). data = {avisos: [{tipo, nivel, mensaje, fase_id, est_id, formula_ids, req_id, nuevo}]}.
    public function revisarFlujoTipoCaso(Request $request, $tipo_caso_id)
    {
        $log = new Funciones();
        try {
            $cambios = $request->input('cambios');
            $fila = DB::selectOne('SELECT crm.fn_tipo_caso_flujo_revisar(?::integer, ?::json) AS resultado', [
                (int) $tipo_caso_id,
                json_encode(is_array($cambios) ? $cambios : new \stdClass(), JSON_UNESCAPED_UNICODE),
            ]);
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;
            if (($resultado->status ?? 'error') !== 'success') {
                return response()->json(RespuestaApi::returnResultado('error', $resultado->mensaje ?? 'No se pudo revisar el flujo.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se revisó el flujo.', ['avisos' => $resultado->avisos ?? []]));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al revisar el flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo revisar el flujo.', $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov): una acción del editor que se guarda al momento: revisa la bandera del perfil, arma el jsonb con el
    // tipo de caso, los campos pedidos y la auditoría, llama a la función y responde con su resultado o con el texto del código.
    private function accionEditor(Request $request, $tipo_caso_id, string $bandera, string $funcion, array $campos, string $exito, string $error)
    {
        if ($sinPermiso = $this->sinPermiso($bandera)) {
            return $sinPermiso;
        }
        $log = new Funciones();
        try {
            $datos = ['tipo_caso_id' => (int) $tipo_caso_id];
            foreach ($campos as $campo) {
                $datos[$campo] = $request->input($campo);
            }
            $datos['auditoria'] = $this->contextoAuditoriaForense($request);
            $fila = DB::selectOne('SELECT crm.' . $funcion . '(?::jsonb) AS resultado', [json_encode($datos, JSON_UNESCAPED_UNICODE)]);
            $resultado = $fila && $fila->resultado ? json_decode($fila->resultado) : null;
            $log->logInfo(EstadosFormulasController::class, $exito . ' (' . $funcion . ', tipo de caso ' . $tipo_caso_id . ')');

            return response()->json(RespuestaApi::returnResultado('success', $exito, $resultado));
        } catch (QueryException $e) {
            return $this->respuestaError($e, $error, 'Error en ' . $funcion . ' desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id);
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error en ' . $funcion . ' desde el editor del flujo del tipo de caso con el ID: ' . $tipo_caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', $error, $e->getMessage()));
        }
    }

    // Version 1.0 (07-nov): el perfil del usuario necesita la bandera (create, edit o delete) del menú del editor
    // (crm.fn_access_verificar, en SQL). null = la tiene; si no, la respuesta con SIN_PERMISO.
    private function sinPermiso(string $bandera)
    {
        $usuario = auth('api')->user();
        $fila = DB::selectOne('SELECT crm.fn_access_verificar(?::bigint, ?::text, ?::text) AS ok', [(int) ($usuario->id ?? 0), self::MENU_EDITOR, $bandera]);
        if ($fila && $fila->ok) {
            return null;
        }
        (new Funciones())->logInfo(EstadosFormulasController::class, 'Sin permiso (' . $bandera . ') en el editor de flujos: usuario ' . ($usuario->id ?? '?'));

        return response()->json(RespuestaApi::returnResultado('error', self::MENSAJES_ERROR['SIN_PERMISO'], ['codigo' => 'SIN_PERMISO']));
    }

    // Version 1.0 (07-nov): quién hace el cambio, para la auditoría forense (como en Anuncios).
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
        ];
    }

    // Version 1.0 (07-nov): el texto del código que levantó la función (se busca en el mensaje de PostgreSQL); si no es uno
    // conocido, el mensaje por defecto y el error al log. data = {codigo} para que la pantalla marque el campo.
    private function respuestaError(QueryException $e, string $porDefecto, string $paraElLog)
    {
        $mensajeBd = (string) ($e->errorInfo[2] ?? $e->getMessage());
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($mensajeBd, $codigo) !== false) {
                // 07-nov: el detalle que manda la función (en qué se usa un estado, una respuesta o un tablero).
                if (preg_match('/DETAIL:\s*([^\r\n]+)/u', $mensajeBd, $detalle)) {
                    $mensaje = rtrim($mensaje) . ' ' . trim($detalle[1]) . '.';
                }

                return response()->json(RespuestaApi::returnResultado('error', $mensaje, ['codigo' => $codigo]));
            }
        }
        (new Funciones())->logError(EstadosFormulasController::class, $paraElLog, $e);

        return response()->json(RespuestaApi::returnResultado('error', $porDefecto, $e->getMessage()));
    }

    public function listarTiposCasoTablero($tabId)
    {
        try {

            $data = DB::select("SELECT * FROM crm.tipo_caso WHERE tab_id = ?", [$tabId]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function addEstadosFormulas(Request $request)
    {
        $log = new Funciones();
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, &$error, &$exitoso) {
                // Validar si ya existe un registro con el mismo est_id_actual y resp_id
                $existingRecord = EstadosFormulas::where('est_id_actual', $request->est_id_actual)
                    ->where('resp_id', $request->resp_id)
                    ->where('tipo_caso_id', $request->tipo_caso_id)
                    ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')
                    ->first();

                if ($existingRecord) {
                    // Si ya existe un registro con los mismos valores, devuelve un error
                    $error = 'Ya EXISTE un registro con los valores tipo caso: ' . $existingRecord->tipoCaso->nombre . ', estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    return null;
                } else {

                    // Si no existe un registro con los mismos valores, crea el nuevo registro
                    $respuestas = EstadosFormulas::create($request->all());

                    $resultado = EstadosFormulas::where('tab_id', $respuestas->tab_id)
                        ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')
                        ->orderBy('id', 'DESC')
                        ->get();

                    $exitoso = $resultado;
                    return null;
                }
            });

            if ($error) {
                $log->logError(EstadosFormulasController::class, $error);

                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                $log->logInfo(EstadosFormulasController::class, 'Se guardo con exito la formula del estado');

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al guardar en addEstadosFormulas', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEstadosFormulas(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, $id, &$error, &$exitoso) {
                $respuestas = EstadosFormulas::findOrFail($id);

                // Validar si la actualización resultaría en valores duplicados
                $existingRecord = EstadosFormulas::where('est_id_actual', $request->est_id_actual)
                    ->where('resp_id', $request->resp_id)
                    ->where('tipo_caso_id', $request->tipo_caso_id)
                    ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                    ->first();

                if ($existingRecord) {
                    // Si la actualización resultaría en valores duplicados, devuelve un error
                    // $error = 'Ya EXISTE un registro con los valores estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    $error = 'Ya EXISTE un registro con los valores tipo caso: ' . $existingRecord->tipoCaso->nombre . ', estado actual: ' . $existingRecord->estado_actual->nombre . ' y respuesta: ' . $existingRecord->respuesta_caso->nombre;
                    return null;
                } else {

                    $respuestas->update($request->all());

                    $resultado = EstadosFormulas::where('id', $respuestas->id)
                        ->with('estado_actual', 'fase_actual', 'respuesta_caso', 'estado_proximo', 'tablero_proximo', 'fase_proxima', 'tipoCaso')
                        ->first();

                    $exitoso = $resultado;
                    return null;
                }
            });

            if ($error) {
                $log->logError(EstadosFormulasController::class, $error);

                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                $log->logInfo(EstadosFormulasController::class, 'Se actualizo con exito la formula del estado con el estado ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $exitoso));
            }
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al actualizar la formula del estado con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteEstadosFormulas($id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($id, &$error, &$exitoso) {
                $respuesta = EstadosFormulas::findOrFail($id);

                $respuesta->delete();

                return $respuesta;
            });

            $log->logInfo(EstadosFormulasController::class, 'Se elimino con exito la formula del estado con el estado ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EstadosFormulasController::class, 'Error al eliminar la formula del estado con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }



    //! START PARA LA PANTALLA DE MOVER CASOS MASIVAMENTE
    public function listRespuestasCaso($tab_id, $tipo_caso_id, $fas_id, $estado_id)
    {
        try {
            $respuestas = EstadosFormulas::where('tab_id', $tab_id)
                ->where('tipo_caso_id', $tipo_caso_id)
                ->where('est_id_actual', $estado_id)
                ->where('fase_id_actual', $fas_id)
                ->whereHas('respuesta_caso', function ($query) use ($fas_id) {
                    $query->where('fase_id', $fas_id);
                })
                ->with([
                    'estado_actual',
                    'fase_actual',
                    'respuesta_caso',
                    'estado_proximo',
                    'tablero_proximo',
                    'fase_proxima',
                    'tipoCaso'
                ])
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuestas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    //! END PARA LA PANTALLA DE MOVER CASOS MASIVAMENTE

}
