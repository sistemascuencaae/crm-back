<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Tablero;
use App\Models\crm\TableroUsuario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableroController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function listTableroByUser()
    {
        $tableros = Tablero::with('tableroUsuario.usuario')->where('estado', true)->orderBy("id", "desc")->get();

        return response()->json([
            "tableros" => $tableros,
        ]);
    }

    public function listTableroInactivos()
    {
        $tableros = Tablero::with('tableroUsuario.usuario')->where('estado', false)->orderBy("id", "desc")->get();

        return response()->json([
            "tableros" => $tableros,
        ]);
    }

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

            $dataRe = Tablero::with('tableroUsuario.usuario')->orderBy("id", "desc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo el tablero con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateTablero(Request $request, $id)
    {
        try {
            $eliminados = $request->input('eliminados');
            $usuarios = $request->input('usuarios');
            $tablero = $request->all();

            //echo(json_encode($eliminados[0]['id']));
            $tab = DB::transaction(function () use ($tablero, $id, $eliminados, $usuarios) {
                Tablero::where('id', $id)
                    ->update([
                        'dep_id' => $tablero['dep_id'],
                        'titab_id' => $tablero['titab_id'],
                        'nombre' => $tablero['nombre'],
                        'descripcion' => $tablero['descripcion'],
                        'estado' => $tablero['estado'],
                    ]);

                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.tablero_user WHERE tab_id = " . $id . " and user_id = " . $eliminados[$i]['id']);
                    }
                }

                for ($i = 0; $i < sizeof($usuarios); $i++) {
                    $tabl = TableroUsuario::where('tab_id', $id)->where('user_id', $usuarios[$i])->first();
                    if (!$tabl) {
                        DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id) values (?, ?)', [$usuarios[$i]['id'], $id]);
                    }
                }

                return $tablero;
            });

            $dataRe = Tablero::with('tableroUsuario.usuario')->where('id', $id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo el tablero con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}