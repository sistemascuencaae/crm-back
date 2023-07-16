<?php

namespace App\Http\Controllers;

use App\Http\Resources\RespuestaApi;
use App\Models\ChatGroups;
use App\Models\Miembros;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function addChatGrupal(Request $request)
    {
        try {
            $chatG = $request->all();

            DB::transaction(function () use ($chatG) {
                $chatGrupal = ChatGroups::create($chatG);
                for ($i = 0; $i < sizeof($chatG['usuarios']); $i++) {
                    DB::insert('INSERT INTO crm.miembros (user_id, chat_group_id) values (?, ?)', [$chatG['usuarios'][$i]['id'], $chatGrupal['id']]);
                }
            });

            // $dataRe = ChatGroups::with('chatMiembros.miembros')->orderBy("id", "DESC")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo el chat grupal con éxito', $chatG));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listChatByCasoId($id)
    {
        try {
            $chatGroup = ChatGroups::where('caso_id', $id)->with('chatMiembros.usuario')->orderBy("id", "desc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $chatGroup));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editChatGrupal(Request $request, $id)
    {
        try {
            $eliminados = $request->input('eliminados');
            $usuarios = $request->input('usuarios');
            $tablero = $request->all();

            //echo(json_encode($eliminados[0]['id']));
            $tab = DB::transaction(function () use ($tablero, $id, $eliminados, $usuarios) {
                ChatGroups::where('id', $id)
                    ->update([
                        // 'dep_id' => $tablero['dep_id'],
                        'caso_id' => $tablero['caso_id'],
                        'nombre' => $tablero['nombre'],
                        // 'descripcion' => $tablero['descripcion'],
                        // 'estado' => $tablero['estado'],
                    ]);

                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.miembros WHERE chat_group_id = " . $id . " and user_id = " . $eliminados[$i]['id']);
                    }
                }

                for ($i = 0; $i < sizeof($usuarios); $i++) {
                    $tabl = Miembros::where('chat_group_id', $id)->where('user_id', $usuarios[$i])->first();
                    if (!$tabl) {
                        DB::insert('INSERT INTO crm.miembros (user_id, chat_group_id) values (?, ?)', [$usuarios[$i]['id'], $id]);
                    }
                }

                return $tablero;
            });

            $dataRe = ChatGroups::with('chatMiembros.usuario')->where('id', $id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo el Chat Grupal con éxito', $dataRe));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}