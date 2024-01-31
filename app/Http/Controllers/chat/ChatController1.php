<?php

namespace App\Http\Controllers\chat;

use App\Events\SendMsgEvent;
use App\Events\ChatEvent;
use App\Events\RefreshChatRoomEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\chat\Chat;
use App\Models\chat\ChatGroup;
use App\Models\chat\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list'
        ]]);
    }

    public function usuariosChat(){
        try {
            $user_id = Auth::id();
            $data = DB::select("SELECT
                u.id,
                u.name,
                u.email,
                u.usu_alias,
                u.avatar
            from crm.users u
                where u.estado = true and u.usu_tipo <> 1 and u.id <> $user_id");
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function listChatsRooms($userId)
    {
        try {
            $data = $this->chatsUsuario($userId);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function list($uniqd)
    {
        // try {
        //     $data = ChatGroup::with('chat.chatRoom.primerUser', 'chat.chatRoom.segundoUser')->where('uniqd', $uniqd)->first();
        //     return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        // } catch (\Throwable $th) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        // }
    }

    public function sendMessage(Request $request, $uniqd, $userId, $userDosId)
    {
        try {
            $chat = $request->all();
            // $newMsg = Chat::create($chat);
            // $data = DB::select("SELECT
            //     cr.uniqd,
            //     ch.user_id,
            //     usescr.name as usuario,
            //     ch.message,
            //     ch.created_at
            //     FROM crm.chat_groups cg
            //     INNER JOIN crm.chat_rooms cr ON cr.chat_group_id  = cg.id
            //     INNER JOIN crm.chats ch ON ch.chat_group_id = cg.id AND ch.chat_room_id = cr.id
            //     left join crm.users usescr on usescr.id = ch.user_id
            //     WHERE cr.uniqd = ? order by ch.created_at asc", [$uniqd]);
            // $chatsUserPrincipal = $this->chatsUsuario($userId);
            // $chatsUserSecundario = $this->chatsUsuario($userDosId);
            // broadcast(new SendMsgEvent($data, $uniqd));
            // broadcast(new RefreshChatRoomEvent($chatsUserPrincipal, $userId));
            // broadcast(new RefreshChatRoomEvent($chatsUserSecundario, $userDosId));
           // return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function listarMensajes($uniqd)
    {
        try {
            $data = DB::select("SELECT
                cr.uniqd,
                ch.user_id,
                usescr.name as usuario,
                ch.message,
                ch.created_at
                FROM crm.chat_groups cg
                INNER JOIN crm.chat_rooms cr ON cr.chat_group_id  = cg.id
                INNER JOIN crm.chats ch ON ch.chat_group_id = cg.id AND ch.chat_room_id = cr.id
                left join crm.users usescr on usescr.id = ch.user_id
                WHERE cr.uniqd = ? order by ch.created_at asc", [$uniqd]);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function chatsUsuario($userId)
    {
        $data = DB::select("SELECT
	        DISTINCT ON (cr.uniqd)
	        ch.user_id AS escrito_user_id,
            ch.message,
            CASE
             WHEN cr.primer_user = ? THEN u2.name
             WHEN cr.segundo_user  = ? THEN u1.name
             ELSE 'Desconocido'
            END AS remitente,
            ch.created_at,
            cr.primer_user as user_uno_id,
            cr.segundo_user as user_dos_id,
            u1.name as useruno,
            u2.name as userdos,
            cr.uniqd as roomkey,
            u.avatar,
            ch.chat_room_id,
            ch.chat_group_id
        from crm.chats ch
        inner join crm.chat_rooms cr on cr.id = ch.chat_room_id and ch.chat_group_id = ch.chat_group_id
        inner join crm.users u on u.id = ch.user_id
        inner join crm.users u1 on u1.id = cr.primer_user
        inner join crm.users u2 on u2.id = cr.segundo_user
        where cr.primer_user = ? or cr.segundo_user = ? order by cr.uniqd, ch.created_at desc
        ", [$userId, $userId, $userId, $userId]);
        return $data;
    }




    public function chatsUsuario1($userId)
    {
        $data = DB::select("SELECT
                DISTINCT ON (COALESCE(us2.name))
                cr.primer_user as user_id,
                cr.segundo_user as user_secudario_id,
                cg.uniqd as chatskey,
                us2.name as username,
                (select
                ch.message
                from crm.chat_rooms cr2
                inner join crm.chats ch on ch.chat_room_id = cr2.id
                where cr2.uniqd = cr.uniqd
                order by ch.created_at desc limit 1) as message,
                ch.created_at,
                cr.uniqd as roomkey,
                us2.avatar,
                cr.id as chat_room_id,
                cg.id as chat_group_id
                FROM crm.chat_groups cg
                left JOIN crm.chat_rooms cr ON cr.chat_group_id  = cg.id
                left JOIN crm.chats ch ON ch.chat_group_id = cg.id AND ch.chat_room_id = cr.id
                left join crm.users us1 on us1.id = cr.primer_user
                left join crm.users us2 on us2.id = cr.segundo_user
                where cr.primer_user = ? ORDER BY COALESCE(us2.name), ch.created_at desc;", [$userId]);
        return $data;
    }


    public function verificarChat($userLoginId,$user_dos_id){
        try{
            $chatRoomCreado = DB::selectOne("SELECT * from crm.chat_rooms cr
            where cr.primer_user = ? and cr.segundo_user = ? or cr.primer_user = ? and cr.segundo_user = ?",
            [$userLoginId,$user_dos_id,$user_dos_id,$userLoginId]);

            if($chatRoomCreado){
                return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $chatRoomCreado));
            }else{
                $longitudClave = 10; // ajusta la longitud según tus necesidades
                $claveUnica = Str::random($longitudClave);
                //crear un nuevo grupo
                // $newGroup = ChatGroup::create([
                //     'nombre' => "CHAT 2" ,
                //     'uniqd' => $claveUnica."-" . $userLoginId . '-' . $user_dos_id
                // ]);
                // //si no existe el chat crear un nuevo chat
                // $newRoom = ChatRoom::create([
                //     'primer_user' => $userLoginId,
                //     'segundo_user' => $user_dos_id,
                //     'chat_group_id' => $newGroup->id,
                //     'uniqd' => $userLoginId.'-'.$user_dos_id
                // ]);
             //  return response()->json(RespuestaApi::returnResultado('success', 'Creado con éxito.', $newRoom));
            }



        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }
}

