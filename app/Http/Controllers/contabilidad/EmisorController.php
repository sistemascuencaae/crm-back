<?php

namespace App\Http\Controllers\contabilidad;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Http\Traits\PermisoMenuTrait;
use App\Models\contabilidad\Emisor;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// CONTABILIDAD > Emisores (menú EMISORES): los emisores de Dynamo (public.emisor, también los inactivos) con su POS y su sistema
// de liquidación, y los sistemas para el combo del modal. Se agregan y se cambian; no se eliminan: se desactivan.
// El negocio vive en ventas.fn_emisor_* (PLANES/COTIZADOR/produccion_bancos_emisores.sql): este controller revisa el permiso del
// menú (el mismo que valida el front: listar pide ejecutar; guardar pide edit y, si es uno nuevo, además create), valida la forma,
// arma el jsonb con los campos del modelo Emisor y el bloque 'auditoria', y traduce la respuesta.
// Viene de ventas/BancosEmisoresController (listarEmisores y guardarEmisor; 11-nov-2026, reestructura de Contabilidad), con las
// mismas funciones SQL, las mismas reglas y los mismos mensajes. La lista y su combo, antes un solo endpoint, son ahora listEmisor y
// listCombosEmisor, y guardar se partió en addEmisor (sin id) y editEmisor/{emi_id}, que llaman a la misma función.
class EmisorController extends Controller
{
    use AuditoriaForenseTrait;
    use PermisoMenuTrait;

    // El menú cuyo permiso se revisa (crm.menu.name).
    private const MENU = 'EMISORES';

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
        'emi_id' => 'emisor',
        'sli_id' => 'sistema de liquidación',
        'nombre' => 'nombre',
        'pos'    => 'POS',
        'activo' => 'activo',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/contabilidad/listEmisor — los emisores (también los inactivos), cada uno con su POS, su sistema de liquidación, si
    // Dynamo lo tiene en edición y cuántas tarjetas activas lo usan (ventas.fn_emisor_listar). Antes, la mitad 'emisores' de
    // listarEmisores.
    public function listEmisor()
    {
        try {
            // El permiso del menú EMISORES (ejecutar = abrir la pantalla).
            if (!$this->tienePermiso(self::MENU, 'ejecutar')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver los emisores.', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_emisor_listar() AS emisores');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'emisores' => json_decode($fila->emisores, true) ?? [],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los emisores', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/contabilidad/listCombosEmisor — el combo Sistema de liquidación del modal: todos los sistemas, con su activo (la
    // pantalla ofrece los activos y el que ya tiene el emisor) (ventas.fn_sistema_liquidacion_listar). Antes, la mitad 'sistemas'
    // de listarEmisores: mismo permiso y mismos mensajes.
    public function listCombosEmisor()
    {
        try {
            if (!$this->tienePermiso(self::MENU, 'ejecutar')) {
                return response()->json(RespuestaApi::returnResultado('error', 'Su perfil no tiene permiso para ver los emisores.', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_sistema_liquidacion_listar() AS sistemas');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'sistemas' => json_decode($fila->sistemas, true) ?? [],
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los emisores', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/contabilidad/addEmisor — agrega uno (nace activo; pos y sli_id tienen que venir, aunque vacíos) y devuelve la
    // lista de ahora. Antes guardarEmisor sin emi_id (un emi_id en el cuerpo no cuenta: siempre es uno nuevo).
    public function addEmisor(Request $request)
    {
        return $this->guardar($request, null);
    }

    // Version 1.0
    // POST /api/contabilidad/editEmisor/{emi_id} — cambia uno (todo tiene que venir: lo que falta, la función lo deja vacío o
    // activo) y devuelve la lista de ahora. Antes guardarEmisor con emi_id (el del cuerpo ya no cuenta: vale el de la URL).
    public function editEmisor(Request $request, $emi_id)
    {
        return $this->guardar($request, $emi_id);
    }

    // Lo que hacía guardarEmisor, con el id de la URL (null = uno nuevo): el permiso del menú va antes que revisar los datos, y
    // las reglas y los mensajes del Validator son los de antes (el id entra a la validación como antes venía en el cuerpo, así
    // 'activo' se sigue pidiendo solo al cambiar uno). Devuelve {emi_id, emisores} como antes.
    private function guardar(Request $request, $emiId)
    {
        try {
            $sinPermiso = $this->sinPermisoParaGuardar(self::MENU, $emiId, 'emisores');
            if ($sinPermiso !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $sinPermiso, null));
            }

            // Al cambiar uno, todo tiene que venir: lo que falta, la función lo deja vacío (o activo).
            $validator = Validator::make(array_merge($request->all(), ['emi_id' => $emiId]), [
                'emi_id' => 'nullable|integer',
                'nombre' => 'nullable|string|max:255',
                'pos'    => 'present|nullable|string|max:255',
                'sli_id' => 'present|nullable|integer',
                'activo' => 'required_with:emi_id|nullable|boolean',
            ], self::MENSAJES, self::ATRIBUTOS);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            // Los campos del modelo, cada uno con la clave que espera la función.
            $payload = ['emi_id' => $this->enteroONulo($emiId)];
            foreach ((new Emisor())->getFillable() as $columna) {
                $clave = Emisor::CLAVES_FUNCION[$columna];
                $payload[$clave] = $this->valorCampo($clave, $request->input($clave));
            }
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_emisor_guardar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
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

    // Cada valor como lo recibía la función: el sistema de liquidación como entero (vacío = null), activo como booleano (null = la
    // función lo toma como activo) y lo demás (el nombre y el POS) tal cual, para que la función los valide con sus mensajes.
    private function valorCampo(string $clave, $valor)
    {
        switch ($clave) {
            case 'sli_id':
                return $this->enteroONulo($valor);
            case 'activo':
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
