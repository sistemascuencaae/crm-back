<?php

namespace App\Http\Controllers\contabilidad;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Http\Traits\PermisoMenuTrait;
use App\Models\contabilidad\Banco;
use App\Models\openceo\Direccion;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// CONTABILIDAD > Bancos (menú BANCOS): los bancos de Dynamo (public.banco, también los inactivos: el listado de Dynamo solo muestra
// los activos), con su cuenta, su cuenta contable y si es comercial, y las cuentas contables de Bancos para el combo del modal. Se
// agregan (con su dirección) y se cambian; no se eliminan: se desactivan.
// El negocio vive en ventas.fn_banco_* (PLANES/COTIZADOR/produccion_bancos_emisores.sql): este controller revisa el permiso del menú
// (el mismo que valida el front: listar pide ejecutar; guardar pide edit y, si es uno nuevo, además create), valida la forma, arma
// el jsonb con los campos del modelo Banco y el bloque 'auditoria', y traduce la respuesta.
// Viene de ventas/BancosEmisoresController (listarBancosDynamo y guardarBancoDynamo; 11-nov-2026, reestructura de Contabilidad), con
// las mismas funciones SQL, las mismas reglas y los mismos mensajes. La lista y su combo, antes un solo endpoint, son ahora listBanco
// y listCombosBanco, y guardar se partió en addBanco (sin id) y editBanco/{ban_id}, que llaman a la misma función.
// 27-nov (pedido del usuario): también el cheque actual, los programas de impresión de cheques y de pagos y la dirección principal.
class BancoController extends Controller
{
    use AuditoriaForenseTrait;
    use PermisoMenuTrait;

    // El menú cuyo permiso se revisa (crm.menu.name).
    private const MENU = 'BANCOS';

    // Los mensajes del Validator en español (la pantalla siempre manda todo: solo otro cliente de la API los ve).
    private const MENSAJES = [
        'required_with' => 'Falta el campo :attribute.',
        'present'       => 'Falta el campo :attribute.',
        'integer'       => 'El campo :attribute tiene que ser un número entero.',
        'boolean'       => 'El campo :attribute tiene que ser verdadero o falso.',
        'string'        => 'El campo :attribute tiene que ser un texto.',
        'max'           => 'El campo :attribute no puede pasar de :max caracteres.',
        // 27-nov: la dirección llega como objeto.
        'array'         => 'El campo :attribute tiene que ser un objeto.',
    ];

    // Los nombres de los campos en esos mensajes (sin ellos, Laravel dice "cco id").
    private const ATRIBUTOS = [
        'ban_id'    => 'banco',
        'cco_id'    => 'cuenta contable',
        'nombre'    => 'nombre',
        'cuenta'    => 'número de cuenta',
        'activo'    => 'activo',
        'comercial' => 'comercial',
        // 27-nov (pedido del usuario): los campos de Dynamo que faltaban.
        'cheque_actual' => 'cheque actual',
        'pgr_id'        => 'programa de impresión de cheques',
        'pgr_pag_id'    => 'programa de impresión de pagos',
        'direccion'     => 'dirección',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/contabilidad/listBanco — los bancos (también los inactivos), cada uno con su cuenta, su cuenta contable, si es
    // comercial, si Dynamo lo tiene en edición y cuántas tarjetas activas tiene (ventas.fn_banco_listar). Antes, la mitad 'bancos'
    // de listarBancosDynamo.
    public function listBanco()
    {
        try {
            // El permiso del menú BANCOS (ejecutar = abrir la pantalla).
            if (!$this->tienePermiso(self::MENU, 'ejecutar')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver los bancos.', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_banco_listar() AS bancos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'bancos' => json_decode($fila->bancos, true) ?? [],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los bancos', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/contabilidad/listCombosBanco — el combo Cuenta contable del modal: las cuentas contables de Bancos (las de
    // movimiento), con su activo y el banco que ya la tiene (la pantalla ofrece las activas libres y la del banco)
    // (ventas.fn_banco_cuentas_contables_buscar). Antes, la mitad 'cuentas' de listarBancosDynamo: mismo permiso y mismos mensajes.
    // 27-nov (pedido del usuario): y 'programas', los de impresión de cheques y de pagos (ventas.fn_banco_programas_listar).
    public function listCombosBanco()
    {
        try {
            if (!$this->tienePermiso(self::MENU, 'ejecutar')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver los bancos.', null));
            }

            // QUITADO 27-nov: $fila = DB::selectOne('SELECT ventas.fn_banco_cuentas_contables_buscar() AS cuentas');
            // 27-nov: las cuentas y los programas en la misma consulta.
            $fila = DB::selectOne('SELECT ventas.fn_banco_cuentas_contables_buscar() AS cuentas, ventas.fn_banco_programas_listar() AS programas');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'cuentas' => json_decode($fila->cuentas, true) ?? [],
                'programas' => json_decode($fila->programas, true) ?? [],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los bancos', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/contabilidad/bancoAuditoria?id&pagina&tamanio&busqueda — 27-nov (pedido del usuario): la auditoría de UN banco (lo
    // guardado desde el CRM: resumen + historial paginado), con el permiso audit del menú BANCOS
    // (ventas.fn_banco_auditoria_resumen y ventas.fn_banco_auditoria_paginacion).
    public function bancoAuditoria(Request $request)
    {
        try {
            if (!$this->tienePermiso(self::MENU, 'audit')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver la auditoría de los bancos.', null));
            }

            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Banco no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_banco_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_banco_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del banco', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/contabilidad/addBanco — agrega uno (nace activo, con su dirección; cuenta y comercial tienen que venir, aunque
    // vacíos) y devuelve la lista de ahora. Antes guardarBancoDynamo sin ban_id (un ban_id en el cuerpo no cuenta: siempre es uno
    // nuevo).
    public function addBanco(Request $request)
    {
        return $this->guardar($request, null);
    }

    // Version 1.0
    // POST /api/contabilidad/editBanco/{ban_id} — cambia uno (todo tiene que venir: lo que falta, la función lo deja vacío, activo
    // o no comercial) y devuelve la lista de ahora. Antes guardarBancoDynamo con ban_id (el del cuerpo ya no cuenta: vale el de la
    // URL).
    public function editBanco(Request $request, $ban_id)
    {
        return $this->guardar($request, $ban_id);
    }

    // Version 1.0
    // Lo que hacía guardarBancoDynamo, con el id de la URL (null = uno nuevo): el permiso del menú va antes que revisar los datos, y
    // las reglas y los mensajes del Validator son los de antes (el id entra a la validación como antes venía en el cuerpo, así
    // 'activo' se sigue pidiendo solo al cambiar uno). Devuelve {ban_id, bancos} como antes.
    // 27-nov (pedido del usuario): además el cheque actual, los 2 programas de impresión y la dirección principal (aquí solo la
    // forma: las reglas y sus mensajes son de la función).
    private function guardar(Request $request, $banId)
    {
        try {
            $sinPermiso = $this->sinPermisoParaGuardar(self::MENU, $banId, 'bancos');
            if ($sinPermiso !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $sinPermiso, null));
            }

            // Al cambiar uno, todo tiene que venir: lo que falta, la función lo deja vacío (o activo, o no comercial).
            $validator = Validator::make(array_merge($request->all(), ['ban_id' => $banId]), [
                'ban_id'    => 'nullable|integer',
                'nombre'    => 'nullable|string|max:600',
                'cuenta'    => 'present|nullable|string|max:255',
                'cco_id'    => 'nullable|integer',
                'activo'    => 'required_with:ban_id|nullable|boolean',
                'comercial' => 'present|nullable|boolean',
                // 27-nov: tienen que venir (vacíos, la función dice qué falta).
                'cheque_actual' => 'present|nullable',
                'pgr_id'        => 'present|nullable|integer',
                'pgr_pag_id'    => 'present|nullable|integer',
                'direccion'     => 'present|nullable|array',
            ], self::MENSAJES, self::ATRIBUTOS);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            // Los campos del modelo, cada uno con la clave que espera la función.
            $payload = ['ban_id' => $this->enteroONulo($banId)];
            foreach ((new Banco())->getFillable() as $columna) {
                $clave = Banco::CLAVES_FUNCION[$columna];
                $payload[$clave] = $this->valorCampo($clave, $request->input($clave));
            }
            // 27-nov: la dirección principal con las claves del modelo Direccion, cada valor tal cual (la función lo valida).
            $payload['direccion'] = [];
            foreach (Direccion::CLAVES_FUNCION_BANCO as $clave) {
                $payload['direccion'][$clave] = $this->valorCampo($clave, $request->input('direccion.' . $clave));
            }
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_banco_guardar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'ban_id' => $respuesta['ban_id'] ?? null,
                    'bancos' => $respuesta['bancos'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar el banco', $e->getMessage()));
        }
    }

    // Version 1.0
    // Cada valor como lo recibía la función: la cuenta contable como entero (vacío = null), activo y comercial como booleanos
    // (null = la función pone el valor por defecto) y lo demás (el nombre y el número de cuenta) tal cual, para que la función
    // los valide con sus mensajes.
    // 27-nov: los 2 programas de impresión también como entero; el cheque actual y la dirección, tal cual.
    private function valorCampo(string $clave, $valor)
    {
        switch ($clave) {
            case 'cco_id':
            case 'pgr_id':
            case 'pgr_pag_id':
                return $this->enteroONulo($valor);
            case 'activo':
            case 'comercial':
                return $this->booleanoONulo($valor);
            default:
                return $valor;
        }
    }

    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
    }

    // null se queda null (la función pone el valor por defecto).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
