<?php

namespace App\Http\Controllers\MigracionNovasoft;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;

class MigracionController extends Controller
{

    private $funciones;

    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'aav_migracion_cartera',
                'aav_migracion_cliente',
                'aav_migracion_referencias_cliente',
                'aav_migracion_CobrosxSecretaria_PeriodoyMes_Actual',
                'aav_migracion_rutaje'
            ]
        ]);

        $this->funciones = new Funciones();
    }

    public function aav_migracion_cartera()
    {
        try {
            $data = DB::table('public.aav_migracion_cartera')->get();

            // Generar el archivo .txt
            $archivo = $this->funciones->descargarTxt($data, 'almespana_cartera.txt');

            return $archivo;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_cliente()
    {
        try {
            $data = DB::table('public.aav_migracion_cliente')->get();

            // Generar el archivo .txt
            $archivo = $this->funciones->descargarTxt($data, 'almespana_clientes.txt');

            return $archivo;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_referencias_cliente()
    {
        try {
            $data = DB::table('public.aav_migracion_referencias_cliente')->get();

            // Generar el archivo .txt
            $archivo = $this->funciones->descargarTxt($data, 'almespana_referencias_clientes.txt');

            return $archivo;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_CobrosxSecretaria_PeriodoyMes_Actual()
    {
        try {
            $data = DB::table('public.aav_migracion_cobrosxsecretaria_periodoymes_actual')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_rutaje()
    {
        try {
            $data = DB::table('public.aav_migracion_rutaje')
            ->select('ent_id', 'ent_identificacion', 'ent_nombres', 'ent_apellidos', 'emp_abreviacion', 'cedula_cobrador', 'nombre_cobrador', 'fecha_reporte')
            ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
