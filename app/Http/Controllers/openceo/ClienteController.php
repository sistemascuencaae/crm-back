<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Mensaje amigable para cada excepción que puede lanzar crm.fn_clientes_registrar
    private const MENSAJES_ERROR = [
        'REQUIERE_TIPOS_PAGO' => 'Debe registrar al menos un tipo de pago.',
        'REQUIERE_AGENTE' => 'Debe seleccionar un agente.',
        'REQUIRE_IDENTIFICACION' => 'Debe ingresar la identificación.',
        'REQUIERE_UBICACION' => 'Debe seleccionar una ubicación.',
        'REQUIERE_CATEGORIA' => 'Debe seleccionar una categoría de cliente.',
        'REQUIERE_ZONA' => 'Debe seleccionar una zona.',
        'REQUIERE_CANAL' => 'Debe seleccionar un canal.',
        'REQUIERE_LISTAPRE' => 'Debe seleccionar una lista de precios.',
        'REQUIERE_PAIS' => 'Debe seleccionar el país / nacionalidad.',
        'REQUIERE_SEXO' => 'Debe seleccionar el sexo.',
        'REQUIERE_ESTADOCIVIL' => 'Debe seleccionar el estado civil.',
        'REQUIERE_NIVELESTUDIOS' => 'Debe seleccionar el nivel de estudios.',
        'REQUIERE_VIVIENDA' => 'Debe seleccionar el tipo de vivienda.',
        'REQUIERE_SITLABORAL' => 'Debe seleccionar la situación laboral.',
        'REQUIERE_TIPOEMPRESA' => 'Debe seleccionar el tipo de empresa.',
        'REQUIERE_INGRESOSPERSONALES' => 'Debe seleccionar la fuente de ingresos personales (Dinardap).',
        'REQUIERE_CARGASFAMILIARES' => 'Debe ingresar el número de cargas familiares.',
        'REQUIERE_INGRESOSACTIVIDAD' => 'Debe ingresar los ingresos mensuales.',
        'REQUIERE_EGRESOSACTIVIDAD' => 'Debe ingresar los egresos mensuales.',
        'IDENTIFICACION_INVALIDA' => 'La identificación ingresada no es válida.',
        'IDENTIFICACION_DUPLICADA' => 'Ya existe un cliente con esa identificación.',
        'CLIENTE_NO_ENCONTRADO' => 'No se encontró el cliente a modificar.',
        'POLITICA_INVALIDA' => 'La política seleccionada no es válida.',
        'EMAILINCO' => 'El email es requerido para clientes a crédito.',
        'CANTON' => 'Debe seleccionar el cantón para clientes a crédito.',
        'AECONOMICA' => 'Debe seleccionar la actividad económica para clientes a crédito.',
        'EMPRESA' => 'Debe seleccionar la compañía para clientes a crédito.',
        'PARROQUIA' => 'Debe seleccionar la parroquia para clientes a crédito.',
        'REFERENCIAS' => 'Debe registrar al menos 2 referencias para clientes a crédito.',
    ];

    // Campos que se envían tal cual a crm.fn_clientes_registrar/crm.fn_clientes_modificar como jsonb
    // (ent_id/cli_id solo aplican al modificar; si no vienen en el request, $request->only() los omite)
    private const CAMPOS_PAYLOAD = [
        'ent_id',
        'cli_id',
        'ent_identificacion',
        'ent_tipo_identificacion',
        'cli_codigo',
        'ent_nombres',
        'ent_apellidos',
        'ent_email',
        'tit_id',
        'ent_fechanacimiento',
        'cliane_fecha_exp_pasaporte',
        'cliane_fecha_inicio_residencia',
        'cli_observacion',
        'cli_cupo',
        'pol_id',
        'lpr_id',
        'cli_impuesto',
        'cli_bloqueo',
        'cli_tarjeta',
        'cli_ilimitado',
        'cli_activo',
        'emp_id',
        'can_id',
        'ent_nombre_comercial',
        'ent_representante_legal',
        'cli_parterel',
        'ubi_id',
        'zon_id',
        'cat_id',
        'direccion',
        'telefono',
        'demografico',
        'actividad',
        'dinardap',
        'datos_bancarios',
        'tipos_pago',
        'referencias',
        'ruta',
        'direcciones_adicionales',
        'telefonos_adicionales',
    ];

    // Orquestador: arma el jsonb y llama a crm.fn_clientes_registrar, que valida
    // (comunes + bifurcación contado/crédito) e inserta todo en una sola transacción implícita.
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ent_identificacion' => 'required|string',
            'ent_tipo_identificacion' => 'required|integer',
            'ent_nombres' => 'required|string',
            'ent_apellidos' => 'required|string',
            'ubi_id' => 'nullable|integer',
            'cat_id' => 'required|integer',
            'pol_id' => 'required|integer',
            'emp_id' => 'required|integer',
            'tipos_pago' => 'required|array|min:1',
            'direccion.dir_calle_principal' => 'required|string',
            'direccion.dir_calle_secundaria' => 'required|string',
            'telefono.tel_numero' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD);

        try {
            $resultado = DB::selectOne('SELECT crm.fn_clientes_registrar(?::jsonb) AS cli_id', [json_encode($payload)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente creado con éxito', ['cli_id' => $resultado->cli_id]));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear el cliente', $e->getMessage()));
        }
    }

    // Modifica un cliente/entidad existente (encontrado antes vía buscarPorIdentificacion). Misma validación
    // de negocio que crear() (crm.fn_cliente_validaciones, dentro de crm.fn_clientes_modificar), pero nunca
    // duplica entidad/cliente y nunca elimina filas hijas (tipos de pago/referencias/direcciones/teléfonos)
    // que ya existían — solo las modifica o agrega nuevas.
    public function modificar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ent_id' => 'required|integer',
            'ent_identificacion' => 'required|string',
            'ent_tipo_identificacion' => 'required|integer',
            'ent_nombres' => 'required|string',
            'ent_apellidos' => 'required|string',
            'ubi_id' => 'nullable|integer',
            'cat_id' => 'required|integer',
            'pol_id' => 'required|integer',
            'emp_id' => 'required|integer',
            'tipos_pago' => 'required|array|min:1',
            'direccion.dir_calle_principal' => 'required|string',
            'direccion.dir_calle_secundaria' => 'required|string',
            'telefono.tel_numero' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD);

        try {
            $resultado = DB::selectOne('SELECT crm.fn_clientes_modificar(?::jsonb) AS cli_id', [json_encode($payload)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente modificado con éxito', ['cli_id' => $resultado->cli_id]));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al modificar el cliente', $e->getMessage()));
        }
    }

    // Catálogos para poblar los combos del formulario (réplica de mntClienteAux.jsf)
    public function catalogos()
    {
        try {
            $data = (object) [
                'politicas' => DB::select("SELECT pol_id, pol_nombre, pol_diasplazo FROM politica WHERE pol_activo = true AND pol_tipocli = 1 ORDER BY pol_nombre"),
                'categorias' => DB::select("SELECT cat_id, cat_nombre FROM catcliente WHERE cat_activo = true AND cat_tipocli = 1 ORDER BY cat_nombre"),
                'canales' => DB::select("SELECT can_id, can_nombre FROM canal WHERE can_activo = true ORDER BY can_nombre"),
                'agentes' => DB::select("SELECT emp.emp_id, TRIM(COALESCE(ent.ent_nombres, '') || ' ' || COALESCE(ent.ent_apellidos, '')) AS nombre_completo
                    FROM empleado emp
                    INNER JOIN entidad ent ON ent.ent_id = emp.ent_id
                    WHERE emp.emp_activo = true
                    ORDER BY nombre_completo"),
                'zonas' => DB::select("SELECT zon_id, zon_nombre FROM zona WHERE zon_activo = true ORDER BY zon_nombre"),
                'listasPrecio' => DB::select("SELECT lpr_id, lpr_nombre FROM listapre WHERE lpr_activo = true ORDER BY lpr_nombre"),
                'paises' => DB::select("SELECT pai_id, pai_nombre FROM pais ORDER BY pai_nombre"),
                'companias' => DB::select("SELECT com_id, com_nombre FROM compania WHERE com_activo = true ORDER BY com_nombre"),
                'actividadesEconomicas' => DB::select("SELECT aec_id, aec_nombre FROM actividad_economica ORDER BY aec_nombre"),
                'formasPago' => DB::select("SELECT sfp_id, sfp_nombre FROM sri_formas_pago ORDER BY sfp_nombre"),
                'tiposTelefono' => DB::select("SELECT tte_id, tte_nombre FROM tipo_telefono ORDER BY tte_nombre"),
                'ubicaciones' => DB::select("SELECT ubi_id, ubi_nombre FROM ubicacion WHERE ubi_activo = true ORDER BY ubi_nombre"),
                'titulos' => DB::select("SELECT tit_id, tit_nombre FROM titulo WHERE tit_activo = true ORDER BY tit_nombre"),
                'cantones' => DB::select("SELECT ctn_id, ctn_nombre FROM canton ORDER BY ctn_nombre"),
                'parametrosAnexo' => DB::select("SELECT pane_id, pane_grupo_codigo, pane_nombre, pane_principal, pane_cod_dinardap
                    FROM parametro_anexo
                    WHERE pane_grupo_codigo BETWEEN 2 AND 10
                    ORDER BY pane_grupo_codigo, pane_nombre"),
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Catálogos cargados con éxito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los catálogos', $th->getMessage()));
        }
    }

    // Parroquias filtradas por cantón (cascada en la pestaña Dirección)
    public function parroquiasByCanton($ctnId)
    {
        try {
            $parroquias = DB::select("SELECT prq_id, prq_nombre FROM parroquia WHERE ctn_id = ? ORDER BY prq_nombre", [$ctnId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Parroquias cargadas con éxito', $parroquias));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las parroquias', $th->getMessage()));
        }
    }

    // Listado paginado de clientes (grid de list-cliente-dynamo).
    // Sin busqueda -> crm.fn_cliente_listar_paginacion | con busqueda -> crm.fn_cliente_buscar_paginacion
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($busqueda !== '') {
                $registros = DB::select('SELECT * FROM crm.fn_cliente_buscar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            } else {
                $registros = DB::select('SELECT * FROM crm.fn_cliente_listar_paginacion(?, ?)', [$pagina, $tamanio]);
            }

            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Clientes listados con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los clientes', $th->getMessage()));
        }
    }

    // Reporte "Solicitud de Cupo y Aceptación de Cesión de Derechos del Crédito" vía crm.fn_cliente_solicitud_credito.
    // Resuelve el usu_id del ERP comparando UPPER(usu_alias) del usuario logueado contra UPPER(usu_alias) del ERP.
    // Si no hay coincidencia, cae al usu_id del agente (empleado) asignado al cliente.
    public function solicitudCredito($cliId)
    {
        try {
            $usuAlias = optional(auth('api')->user())->usu_alias;
            $usuId = null;

            if ($usuAlias) {
                $usuario = DB::selectOne(
                    'SELECT usu_id FROM usuario WHERE UPPER(usu_alias) = UPPER(?) LIMIT 1',
                    [$usuAlias]
                );
                $usuId = $usuario->usu_id ?? null;
            }

            if (!$usuId) {
                $agente = DB::selectOne(
                    'SELECT emp.usu_id
                     FROM cliente cli
                     INNER JOIN empleado emp ON emp.emp_id = cli.emp_id
                     WHERE cli.cli_id = ?',
                    [$cliId]
                );
                $usuId = $agente->usu_id ?? null;
            }

            $data = DB::select('SELECT * FROM crm.fn_cliente_solicitud_credito(?, ?)', [$cliId, $usuId]);

            return response()->json(RespuestaApi::returnResultado('success', 'Solicitud de crédito generada con éxito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo generar la solicitud de crédito', $th->getMessage()));
        }
    }

    // Busca un cliente existente por identificación: valida formato con crm.fn_validar_identificacion_ecuador
    // y compara solo los primeros 10 dígitos (cédula vs RUC del mismo titular), para no duplicar un cliente
    // que ya existe. Usado por el botón de búsqueda en la pestaña Identificación del modal "Nuevo Cliente".
    public function buscarPorIdentificacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identificacion' => 'required|string',
            'tipo_identificacion' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $resultado = DB::select('SELECT * FROM crm.fn_cliente_buscar_por_identificacion(?, ?)', [
                $request->input('identificacion'),
                $request->input('tipo_identificacion'),
            ]);

            $fila = $resultado[0] ?? null;
            $cliente = $fila ? json_decode($fila->datos, true) : null;

            return response()->json(RespuestaApi::returnResultado(
                'success',
                $cliente ? 'Cliente encontrado' : 'No se encontró un cliente con esa identificación',
                $cliente
            ));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo buscar el cliente', $e->getMessage()));
        }
    }
}
