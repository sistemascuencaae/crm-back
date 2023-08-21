<?php

namespace App\Http\Controllers\Chat;

use App\User;
use App\Models\Chat\Chat;
use Illuminate\Http\Request;
use App\Models\Chat\ChatFile;
use App\Models\Chat\ChatRoom;
use App\Events\SendMessageChat;
use App\Events\RefreshMyChatRoom;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\Chat\ChatGResource;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    public function startChat(Request $request)
    {
        date_default_timezone_set("America/Lima");
        if($request->to_user_id == auth('api')->user()->id){
            return response()->json(["error" => "No pueedes iniciar un chat contigo mismo"]);
        }

        $isExistRooms = ChatRoom::whereIn("first_user", [$request->to_user_id,auth('api')->user()->id])
                        ->whereIn("second_user", [$request->to_user_id,auth('api')->user()->id])
                        ->count();

        //si ya existe un chat
        if($isExistRooms > 0){
            $chatRoom = ChatRoom::whereIn("first_user", [$request->to_user_id,auth('api')->user()->id])
                        ->whereIn("second_user", [$request->to_user_id,auth('api')->user()->id])
                        ->first();

            Chat::where('from_user_id',$request->to_user_id)
            ->where('chat_room_id',$chatRoom->id)
            ->where("read_at",NULL)
            ->update(["read_at" => now()]);

            $chats = Chat::where("chat_room_id", $chatRoom->id)->orderBy("created_at","desc")->paginate(10);

            $data = [];
            $data["room_id"] = $chatRoom->id;
            $data["room_uniqd"] = $chatRoom->uniqd;
            $to_user = User::find($request->to_user_id);
            $data["user"] = [
                "id" => $to_user->id,
                "full_name" => $to_user->name.' '.$to_user->surname,
                "avatar" => $to_user->avatar ? env("APP_URL")."storage/".$to_user->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
            ];

            if(count($chats) > 0){
                foreach ($chats as $key => $chat) {
                    $data["messages"][] = [
                        "id" => $chat->id,
                        "sender" => [
                            "id" => $chat->FromUser->id,
                            "full_name" => $chat->FromUser->name.' '.$chat->FromUser->surnme,
                            "avatar" => $chat->FromUser->avatar ? env("APP_URL")."storage/".$chat->FromUser->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
                        ],
                        "message" => $chat->message,
                        // "filw"
                        "file" => $chat->ChatFile ? [
                            "id" => $chat->ChatFile->id,
                            "file_names" => $chat->ChatFile->file_names,
                            "resolution" => $chat->ChatFile->resolution,
                            "type" => $chat->ChatFile->type,
                            "size" => $chat->ChatFile->size,
                            "file" => env("APP_URL")."storage/".$chat->ChatFile->file,
                            "uniqd" => $chat->ChatFile->uniqd,
                            "created_at" =>  $chat->ChatFile->created_at->format("Y-m-d h:i A"),
                        ]: null,
                        "read_at" => $chat->read_at,
                        "time" => $chat->created_at->diffForHumans(),
                        "created_at" => $chat->created_at,
                    ];
                }
            }else{
                $data["messages"] = [];
            }

            $data["exist"] = 1;
            $data["last_page"] = $chats->lastPage();
            return response()->json($data);
        }else{

            $chatRoom = ChatRoom::create([
                "first_user" => auth()->user()->id,//14
                "second_user" => $request->to_user_id,
                "last_at" =>  now()->format("Y-m-d H:i:s.u"),
                "uniqd" => uniqid(),
            ]);

            $data = [];
            $data["room_id"] = $chatRoom->id;
            $data["room_uniqd"] = $chatRoom->uniqd;
            $to_user = User::find($request->to_user_id);
            $data["user"] = [
                "id" => $to_user->id,
                "full_name" => $to_user->name.' '.$to_user->surname,
                "avatar" => $to_user->avatar ? env("APP_URL")."storage/".$to_user->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
            ];

            $data["messages"] = [];

            $data["exist"] = 0;
            $data["last_page"] = 1;
            return response()->json($data);
        }
    }
    public function chatRoomPaginate(Request $request)
    {
        $chats = [];
        $chats = Chat::where("chat_room_id", $request->chat_room_id)->orderBy("created_at","desc")->paginate(10);
        $data = [];
        if(count($chats) > 0){
            foreach ($chats as $key => $chat) {
                $data["messages"][] = [
                    "id" => $chat->id,
                    "sender" => [
                        "id" => $chat->FromUser->id,
                        "full_name" => $chat->FromUser->name.' '.$chat->FromUser->surnme,
                        "avatar" => $chat->FromUser->avatar ? env("APP_URL")."storage/".$chat->FromUser->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
                    ],
                    "message" => $chat->message,
                    // "filw"
                    "file" => $chat->ChatFile ? [
                        "id" => $chat->ChatFile->id,
                        "file_names" => $chat->ChatFile->file_names,
                        "resolution" => $chat->ChatFile->resolution,
                        "type" => $chat->ChatFile->type,
                        "size" => $chat->ChatFile->size,
                        "file" => env("APP_URL")."storage/".$chat->ChatFile->file,
                        "uniqd" => $chat->ChatFile->uniqd,
                        "created_at" =>  $chat->ChatFile->created_at->format("Y-m-d h:i A"),
                    ]: null,
                    "read_at" => $chat->read_at,
                    "time" => $chat->created_at->diffForHumans(),
                    "created_at" => $chat->created_at,
                ];
            }
        }else{
            $data["messages"] = [];
        }
        $data["last_page"] = $chats->lastPage();
        return response()->json($data);
    }
    public function sendMessageText(Request $request)
    {
        date_default_timezone_set("America/Lima");

        $request->request->add(["from_user_id" => auth('api')->user()->id]);
        $chat = Chat::create($request->all());
        $chat->ChatRoom->update(["last_at" => now()->format("Y-m-d H:i:s.u")]);
        //NOTIFICAR AL SEGUNDO USUARIO Y HACER UN PUSH DE MENSAJE
        broadcast(new SendMessageChat($chat));
        broadcast(new RefreshMyChatRoom($request->to_user_id));
        broadcast(new RefreshMyChatRoom(auth('api')->user()->id));
        //NOTIFICAR AL NUESTRA SALA DE CHAT
        //NOTIFICAR ALA SALA DE CHAT  DEL SEGUNDO

        return response()->json(["message"=> 200]);
    }

    public function sendFileMessageText(Request $request)
    {
        date_default_timezone_set("America/Lima");

        if($request->file("files")){
            foreach ($request->file("files") as $key => $file) {
                $extension = $file->getClientOriginalExtension();
                $size = $file->getSize();
                $nombre = $file->getClientOriginalName();
                $data = [];
                if(in_array(strtolower($extension),["jpeg","bmp","jpg","png","gif"])){
                    $data = getimagesize($file);
                }
                $uniqd = uniqid();
                $path = Storage::putFile('chats',$file);

                $chatfile = ChatFile::create([
                    "file_names"=> $nombre,
                    "resolution" => $data ? $data[0]."X".$data[1] : null,
                    "type" => $extension,
                    "size" => $size,
                    "file" => $path,
                    "uniqd" => $uniqd,
                ]);

                $request->request->add(["chat_file_id" => $chatfile->id]);
                $request->request->add(["from_user_id" => auth('api')->user()->id]);
                $chat = Chat::create($request->all());
                $chat->ChatRoom->update(["last_at" => now()->format("Y-m-d H:i:s.u")]);

                broadcast(new SendMessageChat($chat));
                broadcast(new RefreshMyChatRoom($request->to_user_id));
                broadcast(new RefreshMyChatRoom(auth('api')->user()->id));

            }
        }

        return response()->json(["message"=>200]);
    }

    public function listMyChats()
    {
        $chatrooms = ChatRoom::where("first_user", auth('api')->user()->id)->orWhere("second_user", auth('api')->user()->id)
                               ->orderBy("last_at","desc")
                               ->get();
        // 1 - NULL - 2
        return response()->json([
            "chatrooms" => $chatrooms->map(function($item){
                return ChatGResource::make($item);
            }),
        ]);
    }
}


