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
        'POLITICA_INVALIDA' => 'La política seleccionada no es válida.',
        'EMAILINCO' => 'El email es requerido para clientes a crédito.',
        'CANTON' => 'Debe seleccionar el cantón para clientes a crédito.',
        'AECONOMICA' => 'Debe seleccionar la actividad económica para clientes a crédito.',
        'EMPRESA' => 'Debe seleccionar la compañía para clientes a crédito.',
        'PARROQUIA' => 'Debe seleccionar la parroquia para clientes a crédito.',
        'REFERENCIAS' => 'Debe registrar al menos 2 referencias para clientes a crédito.',
    ];

    // Campos que se envían tal cual a crm.fn_clientes_registrar como jsonb
    private const CAMPOS_PAYLOAD = [
        'identificacion',
        'tipo_identificacion',
        'codigo',
        'nombres',
        'apellidos',
        'email',
        'titulo_id',
        'fecha_nacimiento',
        'observacion',
        'cupo',
        'pol_id',
        'lpr_id',
        'impuesto',
        'bloqueo',
        'tarjeta',
        'ilimitado',
        'activo',
        'emp_id',
        'can_id',
        'nombre_comercial',
        'representante_legal_ent_id',
        'parterel',
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
            'identificacion' => 'required|string',
            'tipo_identificacion' => 'required|integer',
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'ubi_id' => 'nullable|integer',
            'cat_id' => 'required|integer',
            'pol_id' => 'required|integer',
            'emp_id' => 'required|integer',
            'tipos_pago' => 'required|array|min:1',
            'direccion.calle_principal' => 'required|string',
            'direccion.calle_secundaria' => 'required|string',
            'telefono.numero' => 'required|string',
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
}
