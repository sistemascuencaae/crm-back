<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
        'CODIGO_DUPLICADO' => 'Ya existe un cliente con ese código.',
        'CLIENTE_NO_ENCONTRADO' => 'No se encontró el cliente a modificar.',
        'POLITICA_INVALIDA' => 'La política seleccionada no es válida.',
        'EMAILINCO' => 'El email es requerido para clientes a crédito.',
        'CANTON' => 'Debe seleccionar el cantón para clientes a crédito.',
        'AECONOMICA' => 'Debe seleccionar la actividad económica para clientes a crédito.',
        'EMPRESA' => 'Debe seleccionar la compañía para clientes a crédito.',
        'PARROQUIA' => 'Debe seleccionar la parroquia para clientes a crédito.',
        'REFERENCIAS' => 'Debe registrar al menos 3 referencias para clientes a crédito.',
        // Rutaje (crm.fn_cliente_registrar_rutaje)
        'CLIENTE_NO_EXISTE' => 'El cliente no existe.',
        'REQUIERE_VISITADOR' => 'Debe seleccionar un visitador.',
        'VISITADOR_INVALIDO' => 'El visitador no es válido o no está activo.',
        'REQUIERE_SECRETARIA' => 'Debe seleccionar una secretaria.',
        'SECRETARIA_INVALIDA' => 'La secretaria no es válida o no está activa.',
        'ZONA_INVALIDA' => 'La zona no es válida o no está activa.',
        'REQUIERE_DIA' => 'Debe seleccionar el día de visita.',
        'DIA_INVALIDO' => 'El día debe estar entre 1 y 30, o ser 99.',
        'RUTA_DUPLICADA' => 'El cliente tiene más de un rutaje registrado. Debe depurarse antes de continuar.',
        // Canal del cliente (crm.fn_cliente_registrar_canal)
        'CANAL_INVALIDO' => 'El canal no es válido o no está activo.',
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
        'cli_credito',
        'lpr_id',
        'cli_impuesto',
        'cli_bloqueo',
        'cli_tarjeta',
        'cli_ilimitado',
        'cli_activo',
        'emp_id',
        // Flag del front: true si en esta alta/edición se consultó una fuente externa (SRI/Ecuador Legal).
        // fn_entidad_crear/modificar lo usan para sellar entidad.ent_fecha_ultima_consulta_identidad = now().
        'consulto_identidad',
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
            'cat_id' => 'nullable|integer', // crm.fn_cliente_validaciones lo defaultea a catcliente.cat_pordefecto (cat_id=1)
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
        // usuario_auditoria: lo setea el servidor (no viene del request) para que la función PG llene
        // cliente.created_by/updated_by. auth('api') es el guard JWT usado en este controller.
        // Se guarda la etiqueta "usu_alias - APELLIDOS NOMBRES" (no el id).
        $payload['usuario_auditoria'] = $this->etiquetaUsuarioAuditoria();

        try {
            // El bloque 'auditoria' (usuario/IP/user agent/request_id) va dentro del jsonb;
            // la función PG lo usa para la auditoría forense y NO lo persiste como dato del cliente.
            $resultado = DB::selectOne('SELECT crm.fn_clientes_registrar(?::jsonb) AS cli_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

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
            'cat_id' => 'nullable|integer', // crm.fn_cliente_validaciones lo defaultea a catcliente.cat_pordefecto (cat_id=1)
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
        // Etiqueta "usu_alias - APELLIDOS NOMBRES" del usuario JWT, para cliente.updated_by.
        $payload['usuario_auditoria'] = $this->etiquetaUsuarioAuditoria();

        try {
            // El bloque 'auditoria' (usuario/IP/user agent/request_id) va dentro del jsonb;
            // la función PG lo usa para la auditoría forense (auditoria.logs_cambios, donde
            // ella misma captura el ANTES) y NO lo persiste como dato del cliente.
            $resultado = DB::selectOne('SELECT crm.fn_clientes_modificar(?::jsonb) AS cli_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

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

    // Etiqueta del usuario autenticado para cliente.created_by/updated_by (varchar(100)):
    // "usu_alias - APELLIDOS NOMBRES" (surname antes que name). Se trunca a 100 por la columna.
    private function etiquetaUsuarioAuditoria(): ?string
    {
        $u = auth('api')->user();
        if (!$u) {
            return null;
        }

        $etiqueta = trim(trim($u->usu_alias) . ' - ' . trim($u->surname) . ' ' . trim($u->name));

        return mb_substr($etiqueta, 0, 100);
    }

    // NOTA: la auditoría de cliente ya NO escribe en crm.audits (quedó de lado).
    // La única auditoría es la FORENSE en auditoria.logs_cambios: la escriben las
    // funciones PG (crear/modificar) o el trigger trg_cliente_audit (cambios directos
    // en BD), y el modal la lee vía crm.fn_cliente_auditoria_listar_paginacion.

    // Bloque de contexto que viaja DENTRO del jsonb hacia las funciones PG
    // (fn_clientes_registrar / fn_clientes_modificar) para la auditoría FORENSE
    // (auditoria.fn_registrar_evento — UNA sola fila por operación). Si la función se
    // ejecuta directo en la BD sin este bloque, la auditoría registra al usuario de
    // base de datos con la marca DIRECT_DB_OPERATION.
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }

    // Catálogos para poblar los combos del formulario (réplica de mntClienteAux.jsf)
    public function catalogos()
    {
        try {
            $data = (object) [
                'politicas' => DB::select("SELECT pol_id, pol_nombre, pol_diasplazo FROM politica WHERE pol_activo = true AND pol_tipocli = 1 ORDER BY pol_nombre"),
                'paises' => DB::select("SELECT pai_id, pai_nombre FROM pais ORDER BY pai_nombre"),
                'tiposTelefono' => DB::select("SELECT tte_id, tte_nombre FROM tipo_telefono ORDER BY tte_nombre"),
                'titulos' => DB::select("SELECT tit_id, tit_nombre FROM titulo WHERE tit_activo = true ORDER BY tit_nombre"),
                'cantones' => DB::select("SELECT ctn_id, ctn_nombre, prv_id FROM canton ORDER BY ctn_nombre"),
                'provincias' => DB::select("SELECT prv_id, prv_nombre FROM provincia ORDER BY prv_nombre"),
                'tiposDireccion' => DB::select("SELECT id, nombre FROM crm.fn_tipo_direccion_listar()"),
                'parametrosAnexo' => DB::select("SELECT pane_id, pane_grupo_codigo, pane_nombre, pane_principal, pane_cod_dinardap
                    FROM parametro_anexo
                    WHERE pane_grupo_codigo BETWEEN 2 AND 10
                    ORDER BY pane_grupo_codigo, pane_nombre"),
            ];

            // Defaults para precargar los inputs del selector-modal ({id, label}), resueltos server-side.
            // Piloto: agente, canal, forma de pago. Agente y forma de pago no tienen flag _pordefecto -> menor id.
            $data->defaults = (object) [
                'emp_id' => DB::selectOne("SELECT emp.emp_id AS id, TRIM(COALESCE(ent.ent_nombres,'') || ' ' || COALESCE(ent.ent_apellidos,'')) AS label
                    FROM empleado emp INNER JOIN entidad ent ON ent.ent_id = emp.ent_id
                    WHERE emp.emp_activo = true ORDER BY emp.emp_id LIMIT 1"),
                // Agente del VENDEDOR logueado: mapeo usu_alias del usuario CRM -> usuario ERP -> empleado.usu_id
                // (mismo patrón que solicitudCredito). Solo se resuelve si mapea a un empleado ACTIVO; null si no.
                // El front lo usa como Agente por defecto SOLO cuando el modal se abre desde el caso; si es null,
                // add cae a 'ventas directa' (emp_id de arriba) y edit conserva el agente ya guardado del cliente.
                'emp_id_vendedor' => ($aliasVendedor = optional(auth('api')->user())->usu_alias)
                    ? DB::selectOne("SELECT emp.emp_id AS id, TRIM(COALESCE(ent.ent_nombres,'') || ' ' || COALESCE(ent.ent_apellidos,'')) AS label
                        FROM empleado emp
                        INNER JOIN entidad ent ON ent.ent_id = emp.ent_id
                        INNER JOIN usuario usu ON usu.usu_id = emp.usu_id
                        WHERE emp.emp_activo = true AND UPPER(TRIM(usu.usu_alias)) = UPPER(TRIM(?)) LIMIT 1", [$aliasVendedor])
                    : null,
                'can_id' => DB::selectOne("SELECT can_id AS id, can_nombre AS label FROM canal
                    WHERE can_activo = true AND can_id = (SELECT to_number(par_texto,'999999')::integer FROM parametro WHERE par_abreviacion='CAN' AND mod_abreviatura='CLI' LIMIT 1) LIMIT 1")
                    ?? DB::selectOne("SELECT can_id AS id, can_nombre AS label FROM canal WHERE can_activo = true ORDER BY can_id LIMIT 1"),
                // Forma de pago fija/default del cliente: 'SIN UTILIZACION DEL SISTEMA FINANCIERO' (sfp_id=1).
                // El tab "Tipo de Pago" se quitó del modal; esto va transparente por detrás en el payload.
                'sfp_id' => DB::selectOne("SELECT sfp_id AS id, sfp_nombre AS label FROM sri_formas_pago
                    WHERE UPPER(TRIM(sfp_nombre)) = 'SIN UTILIZACION DEL SISTEMA FINANCIERO' LIMIT 1")
                    ?? DB::selectOne("SELECT sfp_id AS id, sfp_nombre AS label FROM sri_formas_pago ORDER BY sfp_id LIMIT 1"),
                // 'ubi_id' se quitó de los defaults (2026-07-27): el campo "Ubicación para Dinardap" ya no se
                // captura en el modal, y crm.fn_cliente_validaciones resuelve el valor con el mismo parámetro
                // UBI/CLI (10150 = CUENCA) cuando llega vacío, respetando el que el cliente ya tenga.
                // Categoría por defecto: catcliente.cat_pordefecto (cat_id=1 = CLIENTES). Mismo default que aplica PG.
                'cat_id' => DB::selectOne("SELECT cat_id AS id, cat_nombre AS label FROM catcliente
                    WHERE cat_pordefecto = true AND cat_tipocli = 1 LIMIT 1"),
                // Defaults de la geo que precarga el modal "+ agregar dirección" (solo direcciones NUEVAS;
                // al editar una existente el modal nunca los aplica). Los tres filtran por su bandera de
                // ACTIVO (2026-07-28): sin el filtro, el primer cantón alfabético de AZUAY era ASUNCION —
                // inactivo, de los 225 que se desactivaron por ser parroquias disfrazadas de cantón — y su
                // única parroquia, "SIN PARROQUIAS". Resultado: el modal precargaba un cantón que el propio
                // selector ya no ofrece (y así nacieron las direcciones que hoy apuntan a cantones inactivos).
                // OJO: esto filtra solo lo que se PROPONE al crear. Lo que un cliente ya tiene guardado se
                // respeta aunque esté inactivo: el diccionario de etiquetas (cantones/provincias de arriba) y
                // los endpoints cantonesByProvincia/parroquiasByCanton NO filtran, a propósito.
                'prv_id' => DB::selectOne("SELECT prv_id AS id, prv_nombre AS label FROM provincia
                    WHERE UPPER(TRIM(prv_nombre)) = 'AZUAY' AND prv_activo = true LIMIT 1"),
                // Cantón por defecto: el PRIMER cantón ACTIVO (alfabético) de la provincia por defecto (AZUAY).
                'ctn_id' => DB::selectOne("SELECT ctn_id AS id, ctn_nombre AS label FROM canton
                    WHERE prv_id = (SELECT prv_id FROM provincia WHERE UPPER(TRIM(prv_nombre)) = 'AZUAY' AND prv_activo = true LIMIT 1)
                      AND ctn_activo = true
                    ORDER BY ctn_nombre LIMIT 1"),
                // Parroquia por defecto: la PRIMERA parroquia ACTIVA (alfabética) del cantón por defecto de arriba.
                'prq_id' => DB::selectOne("SELECT prq_id AS id, prq_nombre AS label FROM parroquia
                    WHERE ctn_id = (SELECT ctn_id FROM canton
                        WHERE prv_id = (SELECT prv_id FROM provincia WHERE UPPER(TRIM(prv_nombre)) = 'AZUAY' AND prv_activo = true LIMIT 1)
                          AND ctn_activo = true
                        ORDER BY ctn_nombre LIMIT 1)
                      AND prq_activo = true
                    ORDER BY prq_nombre LIMIT 1"),
                // Nacionalidad por defecto: ECUADOR (parámetro PAI/CLI; fallback por nombre).
                'pai_id' => DB::selectOne("SELECT pai_id AS id, pai_nombre AS label FROM pais
                    WHERE pai_codigo = (SELECT par_texto FROM parametro WHERE par_abreviacion='PAI' AND mod_abreviatura='CLI' LIMIT 1) LIMIT 1")
                    ?? DB::selectOne("SELECT pai_id AS id, pai_nombre AS label FROM pais WHERE UPPER(TRIM(pai_nombre))='ECUADOR' LIMIT 1"),
                // Título por defecto: SEÑOR(A) (tit_id=2); si no está activo, cae a la primera opción alfabética.
                'tit_id' => DB::selectOne("SELECT tit_id AS id, tit_nombre AS label FROM titulo WHERE tit_activo=true ORDER BY (tit_id = 2) DESC, tit_nombre LIMIT 1"),
                // Tipo de teléfono por defecto: CELULAR.
                'tte_id' => DB::selectOne("SELECT tte_id AS id, tte_nombre AS label FROM tipo_telefono WHERE UPPER(TRIM(tte_nombre))='CELULAR' LIMIT 1"),
                // Defaults demográficos/actividad (parametro_anexo). Sexo/Nivel/Vivienda/Tipo Empresa = el
                // "principal" del ERP (pane_principal=true). Estado Civil = SOLTERO (decisión del usuario;
                // el "principal" del ERP es CASADO, se sobreescribe a propósito).
                // 'pane_id_sla' (Situación Laboral) se quitó (2026-07-27): el combo salió del formulario y
                // crm.fn_cliente_validaciones fija 26 (EMPLEADO E INDEPENDIENTE) cuando llega vacío.
                'pane_id_sex' => DB::selectOne("SELECT pane_id AS id, pane_nombre AS label FROM parametro_anexo WHERE pane_grupo_codigo=2 AND pane_principal=true LIMIT 1"),
                'pane_id_eci' => DB::selectOne("SELECT pane_id AS id, pane_nombre AS label FROM parametro_anexo WHERE pane_grupo_codigo=3 AND UPPER(TRIM(pane_nombre))='SOLTERO' LIMIT 1"),
                'pane_id_nes' => DB::selectOne("SELECT pane_id AS id, pane_nombre AS label FROM parametro_anexo WHERE pane_grupo_codigo=4 AND pane_principal=true LIMIT 1"),
                'pane_id_tvi' => DB::selectOne("SELECT pane_id AS id, pane_nombre AS label FROM parametro_anexo WHERE pane_grupo_codigo=5 AND pane_principal=true LIMIT 1"),
                'pane_id_tem' => DB::selectOne("SELECT pane_id AS id, pane_nombre AS label FROM parametro_anexo WHERE pane_grupo_codigo=7 AND pane_principal=true LIMIT 1"),
                // Parentesco/relación por defecto de las referencias: la primera opción del catálogo
                // (en mayúsculas, igual que crm.fn_parentesco_listar_paginacion).
                'parentesco' => DB::selectOne("SELECT UPPER(TRIM(nombre)) AS label FROM crm.parentesco ORDER BY nombre LIMIT 1"),
                // Fecha de nacimiento sugerida: 18 años atrás (mayoría de edad). Se calcula con CURRENT_DATE de
                // POSTGRES a propósito — no con el reloj del navegador ni con el de PHP: la fecha del servidor de
                // BD es la única autoridad, y el equipo del usuario puede tener la hora corrida.
                // El front la usa SOLO para precargar el campo vacío (alta nueva, o cliente viejo con
                // ent_fechanacimiento en NULL); si el usuario la cambia, manda la suya.
                'fecha_nacimiento' => DB::selectOne("SELECT (CURRENT_DATE - INTERVAL '18 years')::date AS valor"),
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Catálogos cargados con éxito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los catálogos', $th->getMessage()));
        }
    }

    // Catálogo paginado server-side para el selector-modal del formulario. Cada función PG devuelve
    // (id, label, total_registros). El match solo elige el NOMBRE de la función fija (no concatena
    // input al SQL) — si la clave no está mapeada, se rechaza.
    public function catalogoPaginado(Request $request)
    {
        $catalogo = (string) $request->query('catalogo', '');
        $pagina   = max((int) $request->query('pagina', 1), 1);
        $tamanio  = max((int) $request->query('tamanio', 10), 1);
        $busqueda = trim((string) $request->query('busqueda', ''));
        // p_filtro: solo lo usa parroquias (ctn_id); el resto de funciones lo ignoran. null si no viene.
        $filtroRaw = $request->query('filtro');
        $filtro = ($filtroRaw === null || $filtroRaw === '') ? null : (int) $filtroRaw;

        $funcion = match ($catalogo) {
            'agentes' => 'crm.fn_agente_listar_paginacion',
            'canales' => 'crm.fn_canal_listar_paginacion',
            'formasPago' => 'crm.fn_forma_pago_listar_paginacion',
            'titulos' => 'crm.fn_titulo_listar_paginacion',
            'paises' => 'crm.fn_pais_listar_paginacion',
            'provincias' => 'crm.fn_provincia_listar_paginacion',
            'cantones' => 'crm.fn_canton_listar_paginacion',
            'parroquias' => 'crm.fn_parroquia_listar_paginacion',
            'actividadesEconomicas' => 'crm.fn_actividad_economica_listar_paginacion',
            'cargos' => 'crm.fn_cargo_listar_paginacion',
            'companias' => 'crm.fn_compania_listar_paginacion',
            'ubicaciones' => 'crm.fn_ubicacion_listar_paginacion',
            'zonas' => 'crm.fn_zona_listar_paginacion',
            'listasPrecio' => 'crm.fn_lista_precio_listar_paginacion',
            'categorias' => 'crm.fn_categoria_cliente_listar_paginacion',
            'tiposTelefono' => 'crm.fn_tipo_telefono_listar_paginacion',
            'parentescos' => 'crm.fn_parentesco_listar_paginacion',
            // Rutaje: visitador (age_tipo 2/3), secretaria (usu_tipo 1), zonas hoja
            'visitadores' => 'crm.fn_visitador_listar_paginacion',
            'secretarias' => 'crm.fn_secretaria_listar_paginacion',
            'zonasRuta' => 'crm.fn_zona_ruta_listar_paginacion',
            default => null,
        };

        if ($funcion === null) {
            return response()->json(RespuestaApi::returnResultado('error', 'Catálogo no válido', null));
        }

        try {
            $registros = DB::select("SELECT * FROM {$funcion}(?, ?, ?, ?)", [$pagina, $tamanio, $busqueda, $filtro]);
            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Catálogo listado con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo listar el catálogo', $th->getMessage()));
        }
    }

    // Árbol completo de ubicaciones (provincia → cantón → parroquia) para el modal del tab Dinardap.
    // Devuelve la lista plana; el front la arma como árbol con ubi_reporta. Solo las hojas son seleccionables.
    public function ubicacionArbol()
    {
        try {
            $nodos = DB::select("SELECT * FROM crm.fn_ubicacion_arbol()");
            return response()->json(RespuestaApi::returnResultado('success', 'Árbol de ubicaciones cargado con éxito', $nodos));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el árbol de ubicaciones', $th->getMessage()));
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

    // Dirección y teléfono de una empresa (compania) — SOLO VISUAL en el modal de cliente (tab Actividad).
    // El cliente solo guarda la referencia com_id; estos datos pertenecen a la compania y no se persisten.
    public function empresaInfo($comId)
    {
        try {
            $empresa = DB::selectOne("SELECT com_id, com_nombre, com_direccion, com_telefono1, com_telefono2 FROM compania WHERE com_id = ?", [$comId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Datos de la empresa', $empresa));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los datos de la empresa', $th->getMessage()));
        }
    }

    // ─── EMPRESA (compania): crear / editar desde el modal de cliente ───────────
    // Campos básicos que acepta el modal de empresa (se mandan a crm.fn_compania_crear/modificar como jsonb).
    private const CAMPOS_COMPANIA = [
        'com_id', 'com_nombre', 'com_ruc', 'com_direccion',
        'com_telefono1', 'com_telefono2', 'com_actividad', 'com_contacto', 'ctn_id', 'com_tipo',
    ];

    public function companiaCrear(Request $request)
    {
        $validator = Validator::make($request->all(), ['com_nombre' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }
        try {
            $payload = $request->only(self::CAMPOS_COMPANIA);
            $resultado = DB::selectOne('SELECT crm.fn_compania_crear(?::jsonb) AS com_id', [json_encode($payload)]);
            return response()->json(RespuestaApi::returnResultado('success', 'Empresa creada con éxito', ['com_id' => $resultado->com_id]));
        } catch (QueryException $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear la empresa', $e->getMessage()));
        }
    }

    public function companiaModificar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'com_id' => 'required|integer',
            'com_nombre' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }
        try {
            $payload = $request->only(self::CAMPOS_COMPANIA);
            DB::statement('SELECT crm.fn_compania_modificar(?::jsonb)', [json_encode($payload)]);
            return response()->json(RespuestaApi::returnResultado('success', 'Empresa modificada con éxito', ['com_id' => (int) $request->input('com_id')]));
        } catch (QueryException $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al modificar la empresa', $e->getMessage()));
        }
    }

    // Datos de una empresa para precargar el modal de editar.
    public function companiaBuscar($comId)
    {
        try {
            $fila = DB::selectOne('SELECT crm.fn_compania_buscar(?) AS datos', [$comId]);
            $datos = $fila && $fila->datos ? json_decode($fila->datos, true) : null;
            return response()->json(RespuestaApi::returnResultado('success', 'Datos de la empresa', $datos));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la empresa', $th->getMessage()));
        }
    }

    // true si la empresa la usa solo el cliente actual (o ninguno) -> editable. cli_id opcional (cliente nuevo = null).
    public function companiaEsEditable(Request $request)
    {
        try {
            $comId = (int) $request->query('com_id');
            $cliRaw = $request->query('cli_id');
            $cliId = ($cliRaw === null || $cliRaw === '') ? null : (int) $cliRaw;
            $fila = DB::selectOne('SELECT crm.fn_compania_es_editable(?, ?) AS editable', [$comId, $cliId]);
            return response()->json(RespuestaApi::returnResultado('success', 'OK', ['editable' => (bool) $fila->editable]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo verificar la empresa', $th->getMessage()));
        }
    }

    // Cantones filtrados por provincia (cascada provincia -> cantón -> parroquia)
    public function cantonesByProvincia($prvId)
    {
        try {
            $cantones = DB::select("SELECT ctn_id, ctn_nombre FROM canton WHERE prv_id = ? ORDER BY ctn_nombre", [$prvId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Cantones cargados con éxito', $cantones));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los cantones', $th->getMessage()));
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

    // Auditoría de UN cliente (modal desde el listado). Combina dos fuentes:
    //   resumen -> crm.fn_cliente_auditoria_resumen (created_by/updated_by/fechas de public.cliente)
    //   eventos -> crm.fn_cliente_auditoria_listar_paginacion (historial paginado de crm.audits)
    // El acceso se gatea en el front con el permiso crm.access.audit.
    public function clienteAuditoria(Request $request)
    {
        try {
            $cliId = (int) $request->query('cli_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($cliId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM crm.fn_cliente_auditoria_resumen(?)', [$cliId]);
            $eventos = DB::select('SELECT * FROM crm.fn_cliente_auditoria_listar_paginacion(?, ?, ?, ?)', [$cliId, $pagina, $tamanio, $busqueda]);
            $total = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del cliente', $th->getMessage()));
        }
    }

    // Historial de impresiones de la "Solicitud de Crédito" de UN cliente (modal desde el listado).
    // Cada fila = una impresión (crm.solicitudes_credito) con referencias/telefonos/direcciones como
    // jsonb anidado (se decodifican aquí para que el front reciba arreglos). Paginado server-side.
    // El acceso se gatea en el front con el permiso crm.access.solicitud_credito.
    public function solicitudCreditoHistorial(Request $request)
    {
        try {
            $cliId = (int) $request->query('cli_id', 0);
            $identificacion = $request->query('identificacion');
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = $request->query('busqueda');
            // Filtro opcional por caso (lo manda el botón de informacion-caso): solo las solicitudes
            // generadas en ESE caso. Si no viene, NULL -> la función no filtra (historial completo del cliente).
            $casoId = $request->query('caso_id');
            $casoId = ($casoId !== null && $casoId !== '') ? (int) $casoId : null;

            // Alternativa por identificación (p.ej. desde el caso del CRM, que no tiene cli_id):
            // se resuelve el cli_id de public.cliente. Si el cliente no existe en public.cliente,
            // no hay historial -> se devuelve vacío (no es un error).
            if ($cliId <= 0 && !empty($identificacion)) {
                $row = DB::selectOne(
                    'SELECT c.cli_id FROM public.cliente c INNER JOIN public.entidad e ON e.ent_id = c.ent_id WHERE e.ent_identificacion = ? LIMIT 1',
                    [$identificacion]
                );
                $cliId = (int) ($row->cli_id ?? 0);

                if ($cliId <= 0) {
                    return response()->json(RespuestaApi::returnResultado('success', 'Sin historial de solicitudes de crédito', [
                        'registros' => [],
                        'total' => 0,
                        'pagina' => $pagina,
                        'tamanio' => $tamanio,
                    ]));
                }
            }

            if ($cliId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no válido', null));
            }

            $registros = DB::select('SELECT * FROM crm.fn_cliente_solicitud_credito_listar_paginacion(?, ?, ?, ?, ?)', [$cliId, $pagina, $tamanio, $busqueda, $casoId]);
            $total = $registros[0]->total_registros ?? 0;

            foreach ($registros as $r) {
                $r->referencias = $r->referencias ? json_decode($r->referencias, true) : [];
                $r->telefonos = $r->telefonos ? json_decode($r->telefonos, true) : [];
                $r->direcciones = $r->direcciones ? json_decode($r->direcciones, true) : [];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Historial de solicitudes cargado con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el historial de solicitudes de crédito', $th->getMessage()));
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

            // Teléfonos ACTIVOS del cliente (principal + adicionales) para la tabla "4) TELÉFONOS".
            $telefonos = DB::select('SELECT * FROM crm.fn_cliente_solicitud_credito_telefonos(?)', [$cliId]);

            // Guarda un snapshot de la solicitud cada vez que se imprime (header + referencias). Best-effort:
            // si falla el guardado, no debe impedir que se genere/imprima el reporte. CONTADO (sin datos)
            // no persiste nada (la función devuelve NULL internamente). El id devuelto es el N° de solicitud
            // que el front inyecta en el encabezado para mostrarlo en el reporte (igual que el flujo del caso).
            $solicitudId = null;
            try {
                $guardado = DB::selectOne('SELECT crm.fn_solicitud_credito_guardar(?, ?, ?) AS impresion_id', [$cliId, $usuId, auth('api')->id()]);
                $solicitudId = $guardado->impresion_id ?? null;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo guardar el historial de solicitud de crédito: ' . $e->getMessage());
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Solicitud de crédito generada con éxito', [
                'filas' => $data,
                'telefonos' => $telefonos,
                'solicitud_id' => $solicitudId,
            ]));
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

            if ($cliente) {
                // Tipo Persona deducido del número (Natural/Jurídica), para cuando el modal NO puede preguntarle
                // al SRI: el throttle de consulta de identidad (ent_fecha_ultima_consulta_identidad) deja al edit
                // sin 'tipoContribuyente'. Se calcula sobre la identificación DEL CLIENTE, no sobre la buscada:
                // el buscador compara solo los primeros 10 dígitos, así que se puede buscar por cédula y traer
                // al mismo titular guardado con RUC. Es solo la deducción; quién decide qué hacer con ella
                // (corregir o respetar lo guardado) es el modal.
                $cliente['tipo_sujeto_derivado'] = ValidacionCedulaRucService::tipoSujetoPorIdentificacion(
                    $cliente['ent_identificacion'] ?? null,
                    isset($cliente['ent_tipo_identificacion']) ? (int) $cliente['ent_tipo_identificacion'] : null
                );
            }

            return response()->json(RespuestaApi::returnResultado(
                'success',
                $cliente ? 'Cliente encontrado' : 'No se encontró un cliente con esa identificación',
                $cliente
            ));
        } catch (QueryException $e) {
            // La identificación inválida (crm.fn_validar_identificacion_ecuador → IDENTIFICACION_INVALIDA) y
            // cualquier otro fallo de la consulta se devuelven con HTTP 500, para que el front lo trate por la
            // rama de error (marca el campo Identificación en rojo).
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null), 500);
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo buscar el cliente', $e->getMessage()), 500);
        }
    }

    // Rutaje actual del cliente (cliruta_gestion) para precargar el modal. Los campos que
    // falten llegan completados con los valores por defecto (CLI/AGE, CLI/USU, CLI/ZON, día 1);
    // 'existe' dice si había fila y 'defectos' qué campos se rellenaron.
    // Línea de tiempo de las DIRECCIONES del cliente (botón "Datos Históricos" del tab Direcciones).
    // La función reconstruye cada versión de cada dirección desde auditoria.logs_cambios, le suma el
    // estado vigente de la tabla direccion (así siempre devuelve al menos una fila) y le atribuye el
    // caso/solicitud cuando esa dirección fue la que se imprimió en una Solicitud de Cupo.
    // Devuelve las filas YA ordenadas: histórico cronológico arriba y el estado vigente al final.
    public function direccionesHistorico(Request $request)
    {
        return $this->historicoColeccion($request, 'crm.fn_cliente_direcciones_historico_paginado', 'direcciones');
    }

    // Línea de tiempo de los TELÉFONOS del cliente (botón "Datos Históricos" del tab Teléfonos).
    // Misma mecánica que direccionesHistorico, sobre las claves `telefono`/`telefonos_adicionales`
    // de la foto auditada + las tablas telefono/telefono_entidad.
    public function telefonosHistorico(Request $request)
    {
        return $this->historicoColeccion($request, 'crm.fn_cliente_telefonos_historico_paginado', 'teléfonos');
    }

    // Línea de tiempo de las REFERENCIAS del cliente (botón "Datos Históricos" del tab Referencias).
    // Sobre la colección `referencias` de la foto auditada + la tabla referencias_anexo.
    public function referenciasHistorico(Request $request)
    {
        return $this->historicoColeccion($request, 'crm.fn_cliente_referencias_historico_paginado', 'referencias');
    }

    // Contrato común de los tres históricos (direcciones/teléfonos/referencias): mismos parámetros de
    // entrada (cli_id, pagina, tamanio, busqueda) y misma forma de salida (filas/total/pagina/tamanio)
    // que clienteAuditoria, así el modal del front es el mismo componente con otras columnas.
    // $funcion se arma en el código, NUNCA con datos del request: no entra nada del usuario al SQL.
    private function historicoColeccion(Request $request, string $funcion, string $etiqueta)
    {
        try {
            $cliId = (int) $request->query('cli_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($cliId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no válido', null));
            }

            $filas = DB::select(
                'SELECT * FROM ' . $funcion . '(?, ?, ?, ?)',
                [$cliId, $pagina, $tamanio, $busqueda]
            );
            // total_registros viaja repetido en cada fila (COUNT(*) OVER()), igual que en clienteAuditoria.
            $total = $filas[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Histórico de ' . $etiqueta . ' cargado con éxito', [
                'filas' => $filas,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener el histórico de ' . $etiqueta, $th->getMessage()));
        }
    }

    public function obtenerRutajeCliente($cliId)
    {
        try {
            $fila = DB::selectOne('SELECT crm.fn_cliente_obtener_rutaje(?) AS datos', [(int) $cliId]);
            $rutaje = $fila && $fila->datos ? json_decode($fila->datos, true) : null;

            return response()->json(RespuestaApi::returnResultado(
                'success',
                ($rutaje['existe'] ?? false) ? 'Rutaje encontrado' : 'El cliente no tiene rutaje registrado',
                $rutaje
            ));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener el rutaje', $th->getMessage()));
        }
    }

    // Guarda el rutaje del cliente (upsert en cliruta_gestion). La función PG valida
    // visitador/secretaria/zona/día y registra la auditoría forense (módulo RUTAJE_CLIENTE).
    public function registrarRutajeCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cli_id' => 'required|integer',
            'emp_id' => 'required|integer',  // visitador
            'usu_id' => 'required|integer',  // secretaria
            'zon_id' => 'required|integer',
            'crg_dia' => 'required|integer|min:1|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(['cli_id', 'emp_id', 'usu_id', 'zon_id', 'crg_dia']);
        $payload['usuario_auditoria'] = $this->etiquetaUsuarioAuditoria();

        try {
            $resultado = DB::selectOne('SELECT crm.fn_cliente_registrar_rutaje(?::jsonb) AS crg_id', [
                json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)]),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Rutaje guardado con éxito', ['crg_id' => $resultado->crg_id]));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el rutaje', $e->getMessage()));
        }
    }

    // Canal actual del cliente (cliente.can_id) para precargar el modal.
    public function obtenerCanalCliente($cliId)
    {
        try {
            $fila = DB::selectOne('SELECT crm.fn_cliente_obtener_canal(?) AS datos', [(int) $cliId]);
            $canal = $fila && $fila->datos ? json_decode($fila->datos, true) : null;

            if (!$canal) {
                return response()->json(RespuestaApi::returnResultado('error', 'El cliente no existe', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Canal encontrado', $canal));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener el canal', $th->getMessage()));
        }
    }

    // Canales elegibles: los activos + el actual del cliente aunque esté inactivo (el front
    // lo muestra deshabilitado para que se vea el valor real sin poder volver a elegirlo).
    public function canalesCliente($cliId)
    {
        try {
            $canales = DB::select('SELECT * FROM crm.fn_canal_cliente_listar(?)', [(int) $cliId]);

            return response()->json(RespuestaApi::returnResultado('success', 'Canales', $canales));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron obtener los canales', $th->getMessage()));
        }
    }

    // Cambia el canal del cliente. La función PG valida el canal, actualiza SOLO can_id y
    // registra la auditoría forense (módulo CANAL_CLIENTE). Si el canal no cambia, no escribe.
    public function registrarCanalCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cli_id' => 'required|integer',
            'can_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(['cli_id', 'can_id']);
        $payload['usuario_auditoria'] = $this->etiquetaUsuarioAuditoria();

        try {
            $resultado = DB::selectOne('SELECT crm.fn_cliente_registrar_canal(?::jsonb) AS cli_id', [
                json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)]),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Canal guardado con éxito', ['cli_id' => $resultado->cli_id]));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el canal', $e->getMessage()));
        }
    }
}
