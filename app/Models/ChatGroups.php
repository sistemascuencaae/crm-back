<?php

namespace App\Models;

use App\Models\Miembros;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroups extends Model
{
    use HasFactory;

    protected $table = 'crm.chat_groups';
    protected $fillable = ["nombre", "uniqd"];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["created_at"] = Carbon::now();
    }
    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["updated_at"] = Carbon::now();
    }

    public function chatMiembros()
    {
        return $this->hasMany(Miembros::class, "chat_group_id", "id");
    }

}
