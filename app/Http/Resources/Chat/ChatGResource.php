<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatGResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        date_default_timezone_set("America/Lima");
        return [
            "friend_first" => $this->resource->first_user != auth('api')->user()->id ?
            [
                "id" => $this->resource->FirstUser->id,
                "full_name" => $this->resource->FirstUser->name.' '.$this->resource->FirstUser->surname,
                "avatar" => $this->resource->FirstUser->avatar ? env("APP_URL")."storage/".$this->resource->FirstUser->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
            ] : NULL,
            "friend_second" => $this->resource->second_user ?
                $this->resource->second_user != auth('api')->user()->id ?
                [
                    "id" => $this->resource->SecondUser->id,
                    "full_name" => $this->resource->SecondUser->name.' '.$this->resource->SecondUser->surname,
                    "avatar" => $this->resource->SecondUser->avatar ? env("APP_URL")."storage/".$this->resource->SecondUser->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
                ] : NULL
            : NULL,
            "group_chat" => $this->resource->chat_group_id ? [
                "id" => $this->resource->ChatGroup->id,
                "name" => $this->resource->ChatGroup->name,
                "avatar" => NULL,

                "last_message" => $this->resource->ChatGroup->last_message,
                "last_message_is_my" => $this->resource->ChatGroup->last_message_user ?  $this->resource->ChatGroup->last_message_user === auth('api')->user()->id : NULL,
                "last_time" => $this->resource->ChatGroup->last_time_created_at,
                "count_message" => $this->resource->ChatGroup->getCountMessages(auth('api')->user()->id),
            ] : NULL,
            "uniqd" => $this->resource->uniqd,
            "is_active" => false,
            "last_message" => $this->resource->last_message,
            "last_message_is_my" => $this->resource->last_message_user ?  $this->resource->last_message_user === auth('api')->user()->id : NULL,
            "last_time" => $this->resource->last_time_created_at,
            "count_message" => $this->resource->getCountMessages(auth('api')->user()->id),
        ];
    }
}
