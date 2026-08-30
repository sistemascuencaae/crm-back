<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\Agencia;
use App\Models\crm\Caso;
use App\Models\gestiones\Gestion;
use App\Models\gestiones\GestionCaso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GestionController extends Controller
{
    public function __construct()
    {
    }

    public function listGestionByIdentificacion($identificacion)
    {
        try {
            $data = DB::select("SELECT * FROM crm.af_gestionmora(?) ORDER BY secuencia ASC",[$identificacion]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Este listado va a ver la CAJERA - FACTURADORA, CUANDO VAYA A GENERAR LA GESTION DEL DIA
    public function listGestionesByIdAgencia()
    {
        try {
            $user = auth('api')->user();

            // Array casos ya en gestion
            $casosYaRegistrados = GestionCaso::pluck('caso_id')->toArray();

            // Traer solo los casos que no estén en GestionCaso
            $data = Caso::where('tc_id', 142) // 142 => Tipo caso de gestion de verito vanegas
                        ->where('codigo_agencia', $user->alm_id)
                        ->whereNotIn('id', $casosYaRegistrados)
                        ->with('cform.form2.field.dform')
                        ->orderBy('id', 'asc')
                        ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addGestion(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $gestion = Gestion::create($request->all());

                // Insertamos el array de casos
                if (isset($request->casos) && count($request->casos) > 0) {
                    foreach ($request->casos as $caso) {

                            GestionCaso::create([
                                'gestion_id' => $gestion->id,
                                'caso_id' => $caso['id'],
                            ]);

                    }
                }
    
                return Gestion::where('id', $gestion->id)->first();
            });
    
            // Si la transacción fue exitosa, respondemos con un mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Gestión #'. $data->id.' guardada con éxito.', $data));
        } catch (Exception $e) {
            // En caso de error, respondemos con un mensaje de error
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Esto lo ve Veronica Vanegas
    public function listAllGestiones()
    {
        try {

            $data = Gestion::with('agencia', 'gestion_caso.caso.cform.form2.field.dform')
                            ->orderBy('id', 'asc')
                            ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}