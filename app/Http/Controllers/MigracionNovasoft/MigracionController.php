<?php

namespace App\Http\Controllers\MigracionNovasoft;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;

class MigracionController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'aav_migracion_cartera',
                'aav_migracion_cliente'
            ]
        ]);
    }

    public function aav_migracion_cartera()
    {
        try {
            $data = DB::table('public.aav_migracion_cartera')->get();

            // Convertir los datos a una cadena JSON
            $jsonString = json_encode($data);

            // Generar el archivo .txt y enviarlo como descarga automática
            $archivo = $this->descargarTxt($jsonString, 'almespana_cartera.txt');

            return $archivo;

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function aav_migracion_cliente()
    {
        try {
            $data = DB::table('public.aav_migracion_cliente')->get();

            // Convertir los datos a una cadena JSON
            $jsonString = json_encode($data);

            // Generar el archivo .txt y enviarlo como descarga automática
            $archivo = $this->descargarTxt($jsonString, 'almespana_clientes.txt');

            return $archivo;

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function descargarTxt($data, $nombreArchivo)
    {
        try {
            // Establecer los encabezados de la respuesta para la descarga del archivo .txt
            $headers = [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
            ];

            // Crear la respuesta HTTP con el contenido del archivo adjunto
            return response($data, 200, $headers);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
