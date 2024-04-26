<?php

namespace App\Http\Controllers\comercializacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\renegociaciones\Doctran;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RenegociacionController extends Controller
{
    public function getDoctranOpenceo($ddo_doctran)
    {
        try {

            $data = DB::select("SELECT * from public.ddocumento doc where ddo_doctran = '$ddo_doctran' order by  doc.ddo_num_pago asc");

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addHistorialDoctran(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                $tamanoArray = count($request->all());
                $contador = 0;

                $codigoUnicoHistorial = Str::uuid();

                $arrayRegistros = [];

                // Recorro el array agregando el código único a cada registro de mi array
                foreach ($request->all() as $registro) {
                    $registro['codigo_historial'] = $codigoUnicoHistorial;
                    $arrayRegistros[] = $registro;
                    $contador++;
                }

                if ($tamanoArray == $contador) {
                    foreach ($arrayRegistros as $registro) {
                        $nuevoRegistro = Doctran::create($registro);

                        // Agregar el nuevo registro al array de registros
                        $arrayRegistros[] = $nuevoRegistro;
                    }

                    return $arrayRegistros;
                }

            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listHistorialDoctran($ddo_doctran)
    {
        try {
            $data = Doctran::where('ddo_doctran', $ddo_doctran)->orderBy("id", "asc")->orderBy('ddo_num_pago', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
