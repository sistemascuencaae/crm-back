<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Tablero;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableroController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function addTablero(Request $request)
    {
        try {
            $tab = $request->all();
            DB::transaction(function () use ($tab) {
                $tablero = Tablero::create($tab);
                for ($i = 0; $i < sizeof($tab['usuarios']); $i++) {
                    DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id) values (?, ?)', [$tab['usuarios'][$i]['id'], $tablero['id']]);
                }
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo el tablero con éxito', $tab));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listTableroByUser()
    {
        $tableros = Tablero::with('tableroUsuario.usuario')->orderBy("id", "desc")->get();

        return response()->json([
            "tableros" => $tableros,
        ]);
    }

    public function updateTablero(Request $request, $id)
    {
        try {
            $tab = Tablero::findOrFail($id);
            // DELETE FROM crm.tablero_user WHERE user_id in (3,4);

            DB::transaction(function () use ($tab) {
                $tablero = Tablero::create($tab);
                //.$tab['eliminados'] = '(3,4,5,6)'
                DB::delete(' DELETE FROM crm.tablero_user WHERE user_id in' . $tab['eliminados']);
                for ($i = 0; $i < sizeof($tab['usuarios']); $i++) {
                    DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id) values (?, ?)', [$tab['usuarios'][$i]['id'], $tablero['id']]);
                }
            });

            return response()->json(["tablero" => $tab]);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function edit2(Request $request, $id)
    // {
    //     try {
    //         $tablero = Tablero::findOrFail($id);

    //         $tablero->update($request->all());

    //         return response()->json(["tablero" => $tablero]);
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }
}