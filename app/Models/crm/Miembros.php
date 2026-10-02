<?php

namespace App\Models\crm;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Miembros extends Model
{

    use HasFactory;

    use SoftDeletes;

    protected $table = 'crm.miembros';
    protected $fillable = [
        "user_id",
        "chat_group_id",
        "created_at",
        "updated_at",
        "caso_id",
    ];

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
    public function setDeletedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["deleted_at"] = Carbon::now();
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, "user_id", "id")->select(
            'id',
            'name',
            'surname',
            'usu_id',
            'usu_tipo_analista',
            'dep_id',
            'estado',
            'usu_tipo',
            'usu_alias',
            'tab_id',
            'en_linea'
        );
    }
}
