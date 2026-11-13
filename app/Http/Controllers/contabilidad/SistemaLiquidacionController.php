<?php

namespace App\Http\Controllers\contabilidad;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Http\Traits\PermisoMenuTrait;
use App\Models\contabilidad\SistemaLiquidacion;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// CONTABILIDAD > Sistemas de liquidación (menú SISTEMAS-LIQUIDACION): donde liquida cada emisor (public.sistema_liquidacion), con
// cuántos emisores usa cada uno. Se agregan y se cambian; no se eliminan: se desactivan (no si lo usa un emisor activo).
// El negocio vive en ventas.fn_sistema_liquidacion_* (PLANES/COTIZADOR/produccion_bancos_emisores.sql): este controller revisa el
// permiso del menú (el mismo que valida el front: listar pide ejecutar; guardar pide edit y, si es uno nuevo, además create),
// valida la forma, arma el jsonb con los campos del modelo SistemaLiquidacion y el bloque 'auditoria', y traduce la respuesta.
// Viene de ventas/BancosEmisoresController (listarSistemasLiquidacion y guardarSistemaLiquidacion; 11-nov-2026, reestructura de
// Contabilidad), con las mismas funciones SQL, las mismas reglas y los mismos mensajes. Guardar se partió en addSistemaLiquidacion
// (sin id) y editSistemaLiquidacion/{sli_id}, que llaman a la misma función.
class SistemaLiquidacionController extends Controller
{
    use AuditoriaForenseTrait;
    use PermisoMenuTrait;

    // El menú cuyo permiso se revisa (crm.menu.name).
    private const MENU = 'SISTEMAS-LIQUIDACION';

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
        'sli_id' => 'sistema de liquidación',
        'nombre' => 'nombre',
        'activo' => 'activo',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/contabilidad/listSistemaLiquidacion — los sistemas de liquidación (también los inactivos), con cuántos emisores
    // usa cada uno (ventas.fn_sistema_liquidacion_listar). Antes listarSistemasLiquidacion.
    public function listSistemaLiquidacion()
    {
        try {
            // El permiso del menú SISTEMAS-LIQUIDACION (ejecutar = abrir la pantalla).
            if (!$this->tienePermiso(self::MENU, 'ejecutar')) {
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

    // Version 1.0
    // POST /api/contabilidad/addSistemaLiquidacion — agrega uno (nace activo) y devuelve la lista de ahora. Antes
    // guardarSistemaLiquidacion sin sli_id (un sli_id en el cuerpo no cuenta: siempre es uno nuevo).
    public function addSistemaLiquidacion(Request $request)
    {
        return $this->guardar($request, null);
    }

    // Version 1.0
    // POST /api/contabilidad/editSistemaLiquidacion/{sli_id} — cambia uno (activo tiene que venir: vacío, la función lo toma como
    // activo) y devuelve la lista de ahora. Antes guardarSistemaLiquidacion con sli_id (el del cuerpo ya no cuenta: vale el de la
    // URL).
    public function editSistemaLiquidacion(Request $request, $sli_id)
    {
        return $this->guardar($request, $sli_id);
    }

    // Lo que hacía guardarSistemaLiquidacion, con el id de la URL (null = uno nuevo): el permiso del menú va antes que revisar los
    // datos, y las reglas y los mensajes del Validator son los de antes (el id entra a la validación como antes venía en el cuerpo,
    // así 'activo' se sigue pidiendo solo al cambiar uno). Devuelve {sli_id, sistemas} como antes.
    private function guardar(Request $request, $sliId)
    {
        try {
            $sinPermiso = $this->sinPermisoParaGuardar(self::MENU, $sliId, 'sistemas de liquidación');
            if ($sinPermiso !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $sinPermiso, null));
            }

            // Al cambiar uno, activo tiene que venir: vacío, la función lo toma como activo.
            $validator = Validator::make(array_merge($request->all(), ['sli_id' => $sliId]), [
                'sli_id' => 'nullable|integer',
                'nombre' => 'nullable|string|max:255',
                'activo' => 'required_with:sli_id|nullable|boolean',
            ], self::MENSAJES, self::ATRIBUTOS);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            // Los campos del modelo, cada uno con la clave que espera la función.
            $payload = ['sli_id' => $this->enteroONulo($sliId)];
            foreach ((new SistemaLiquidacion())->getFillable() as $columna) {
                $clave = SistemaLiquidacion::CLAVES_FUNCION[$columna];
                $payload[$clave] = $this->valorCampo($clave, $request->input($clave));
            }
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_sistema_liquidacion_guardar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
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

    // Cada valor como lo recibía la función: activo como booleano (null = la función lo toma como activo) y el nombre tal cual,
    // para que la función lo valide con sus mensajes.
    private function valorCampo(string $clave, $valor)
    {
        return $clave === 'activo' ? $this->booleanoONulo($valor) : $valor;
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
