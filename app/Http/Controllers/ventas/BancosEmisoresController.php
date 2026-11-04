<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Access;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// 27-oct: los mantenimientos de bancos (public.banco) y emisores (public.emisor) de Dynamo y de los sistemas de
// liquidacion (public.sistema_liquidacion, donde liquida cada emisor). El negocio vive en ventas.fn_banco_*,
// ventas.fn_emisor_* y ventas.fn_sistema_liquidacion_* (PLANES/COTIZADOR/produccion_bancos_emisores.sql): este
// controller solo valida la forma, arma el jsonb con 'auditoria' y traduce la respuesta.
// 27-oct (tarde): son las pantallas de Contabilidad (menús EMISORES, SISTEMAS-LIQUIDACION y BANCOS): antes de todo se
// revisa el permiso del menú del perfil del usuario, el mismo que valida el front. Listar pide ejecutar; guardar pide
// edit y, si es uno nuevo (sin id), además create.
class BancosEmisoresController extends Controller
{
    // Los mensajes del Validator en español (la pantalla siempre manda todo: solo otro cliente de la API los ve).
    private const MENSAJES = [
        'required_with' => 'Falta el campo :attribute.',
        'present'       => 'Falta el campo :attribute.',
        'integer'       => 'El campo :attribute tiene que ser un número entero.',
        'boolean'       => 'El campo :attribute tiene que ser verdadero o falso.',
        'string'        => 'El campo :attribute tiene que ser un texto.',
        'max'           => 'El campo :attribute no puede pasar de :max caracteres.',
    ];

    // Los nombres de los campos en esos mensajes (sin ellos, Laravel dice "sli id").
    private const ATRIBUTOS = [
        'sli_id'    => 'sistema de liquidación',
        'emi_id'    => 'emisor',
        'ban_id'    => 'banco',
        'cco_id'    => 'cuenta contable',
        'nombre'    => 'nombre',
        'pos'       => 'POS',
        'cuenta'    => 'número de cuenta',
        'activo'    => 'activo',
        'comercial' => 'comercial',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Los sistemas de liquidacion, con cuantos emisores usa cada uno.
    public function listarSistemasLiquidacion()
    {
        try {
            // 27-oct: el permiso del menú SISTEMAS-LIQUIDACION (ejecutar = abrir la pantalla).
            if (!$this->tienePermiso('SISTEMAS-LIQUIDACION', 'ejecutar')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver los sistemas de liquidación.', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_sistema_liquidacion_listar() AS sistemas');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'sistemas' => json_decode($fila->sistemas, true) ?? [],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los sistemas de liquidación', $e->getMessage()));
        }
    }

    // Guarda uno (sin sli_id = nuevo) y devuelve la lista de ahora.
    public function guardarSistemaLiquidacion(Request $request)
    {
        try {
            // 27-oct: el permiso del menú SISTEMAS-LIQUIDACION va antes que revisar los datos.
            $sinPermiso = $this->sinPermisoParaGuardar('SISTEMAS-LIQUIDACION', $request->input('sli_id'), 'sistemas de liquidación');
            if ($sinPermiso !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $sinPermiso, null));
            }

            // Al cambiar uno, activo tiene que venir: vacío, la función lo toma como activo.
            $validator = Validator::make($request->all(), [
                'sli_id' => 'nullable|integer',
                'nombre' => 'nullable|string|max:255',
                'activo' => 'required_with:sli_id|nullable|boolean',
            ], self::MENSAJES, self::ATRIBUTOS);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = [
                'sli_id'    => $this->enteroONulo($request->input('sli_id')),
                'nombre'    => $request->input('nombre'),
                'activo'    => $this->booleanoONulo($request->input('activo')),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_sistema_liquidacion_guardar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'sli_id'   => $respuesta['sli_id'] ?? null,
                    'sistemas' => $respuesta['sistemas'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar el sistema de liquidación', $e->getMessage()));
        }
    }

    // Los emisores (tambien los inactivos) y los sistemas de liquidacion para su combo.
    public function listarEmisores()
    {
        try {
            // 27-oct: el permiso del menú EMISORES (ejecutar = abrir la pantalla).
            if (!$this->tienePermiso('EMISORES', 'ejecutar')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver los emisores.', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_emisor_listar() AS emisores, ventas.fn_sistema_liquidacion_listar() AS sistemas');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'emisores' => json_decode($fila->emisores, true) ?? [],
                'sistemas' => json_decode($fila->sistemas, true) ?? [],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los emisores', $e->getMessage()));
        }
    }

    // Guarda uno (sin emi_id = nuevo) y devuelve la lista de ahora.
    public function guardarEmisor(Request $request)
    {
        try {
            // 27-oct: el permiso del menú EMISORES va antes que revisar los datos.
            $sinPermiso = $this->sinPermisoParaGuardar('EMISORES', $request->input('emi_id'), 'emisores');
            if ($sinPermiso !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $sinPermiso, null));
            }

            // Al cambiar uno, todo tiene que venir: lo que falta, la función lo deja vacío (o activo).
            $validator = Validator::make($request->all(), [
                'emi_id' => 'nullable|integer',
                'nombre' => 'nullable|string|max:255',
                'pos'    => 'present|nullable|string|max:255',
                'sli_id' => 'present|nullable|integer',
                'activo' => 'required_with:emi_id|nullable|boolean',
            ], self::MENSAJES, self::ATRIBUTOS);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = [
                'emi_id'    => $this->enteroONulo($request->input('emi_id')),
                'nombre'    => $request->input('nombre'),
                'pos'       => $request->input('pos'),
                'sli_id'    => $this->enteroONulo($request->input('sli_id')),
                'activo'    => $this->booleanoONulo($request->input('activo')),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_emisor_guardar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'emi_id'   => $respuesta['emi_id'] ?? null,
                    'emisores' => $respuesta['emisores'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar el emisor', $e->getMessage()));
        }
    }

    // Los bancos (tambien los inactivos) y las cuentas contables de bancos para su combo.
    public function listarBancosDynamo()
    {
        try {
            // 27-oct: el permiso del menú BANCOS (ejecutar = abrir la pantalla).
            if (!$this->tienePermiso('BANCOS', 'ejecutar')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver los bancos.', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_banco_listar() AS bancos, ventas.fn_banco_cuentas_contables_buscar() AS cuentas');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'bancos'  => json_decode($fila->bancos, true) ?? [],
                'cuentas' => json_decode($fila->cuentas, true) ?? [],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los bancos', $e->getMessage()));
        }
    }

    // Guarda uno (sin ban_id = nuevo, con su direccion) y devuelve la lista de ahora.
    public function guardarBancoDynamo(Request $request)
    {
        try {
            // 27-oct: el permiso del menú BANCOS va antes que revisar los datos.
            $sinPermiso = $this->sinPermisoParaGuardar('BANCOS', $request->input('ban_id'), 'bancos');
            if ($sinPermiso !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $sinPermiso, null));
            }

            // Al cambiar uno, todo tiene que venir: lo que falta, la función lo deja vacío (o activo, o no comercial).
            $validator = Validator::make($request->all(), [
                'ban_id'    => 'nullable|integer',
                'nombre'    => 'nullable|string|max:600',
                'cuenta'    => 'present|nullable|string|max:255',
                'cco_id'    => 'nullable|integer',
                'activo'    => 'required_with:ban_id|nullable|boolean',
                'comercial' => 'present|nullable|boolean',
            ], self::MENSAJES, self::ATRIBUTOS);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = [
                'ban_id'    => $this->enteroONulo($request->input('ban_id')),
                'nombre'    => $request->input('nombre'),
                'cuenta'    => $request->input('cuenta'),
                'cco_id'    => $this->enteroONulo($request->input('cco_id')),
                'activo'    => $this->booleanoONulo($request->input('activo')),
                'comercial' => $this->booleanoONulo($request->input('comercial')),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_banco_guardar(?::jsonb) AS resultado', [json_encode($payload)]);
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

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // 27-oct: el permiso del menú (EMISORES, SISTEMAS-LIQUIDACION o BANCOS) para el perfil del usuario logueado: crm.access
    // por crm.menu.name, el mismo que valida el front (como UserController::tienePermisoUsuarios).
    private function tienePermiso(string $menu, string $accion): bool
    {
        $usuario = auth('api')->user();

        return $usuario !== null && Access::where('profile_id', $usuario->profile_id)
            ->where($accion, 1)
            ->whereHas('menu', function ($query) use ($menu) {
                $query->where('name', $menu);
            })
            ->exists();
    }

    // Guardar pide edit; uno nuevo (sin id), además create. null = puede; si no, el mensaje para el usuario.
    private function sinPermisoParaGuardar(string $menu, $id, string $plural): ?string
    {
        if (!$this->tienePermiso($menu, 'edit')) {
            return 'Su perfil no tiene permiso para guardar ' . $plural . '.';
        }
        if ($this->sinValor($id) && !$this->tienePermiso($menu, 'create')) {
            return 'Su perfil no tiene permiso para agregar ' . $plural . '.';
        }

        return null;
    }

    // Sin id = uno nuevo (null o texto vacío; otra cosa la revisa el Validator).
    private function sinValor($valor): bool
    {
        return $valor === null || (is_string($valor) && trim($valor) === '');
    }

    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
    }

    // null se queda null (la funcion pone el valor por defecto).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
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
