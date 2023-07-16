<?php

namespace App\Http\Controllers;

use App\Http\Resources\RespuestaApi;
use App\Models\ChatGroups;
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
}
