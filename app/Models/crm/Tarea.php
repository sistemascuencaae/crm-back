<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarea extends Model
{
    protected $table = 'crm.tarea';
    protected $primaryKey = 'id';
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'flujo_id',
        'orden',
        'estado',
        'nombre',
        'descripcion',
        'info1',
        'info2',
        'info3',
        'info4',
        'info5',
        'info6',
        'info7',
        'ent_id',
        'fecha_vencimiento',
    ];
    protected $casts = [
        'created_at'  => 'datetime:Y-m-d H:00',
    ];
    protected $hidden = [
        'deleted_at',
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

    public function Flujo()
    {
       return $this->belongsTo(Flujo::class,"flujo_id");
    }

    public function Etiqueta()
    {
        return $this->hasMany(Etiqueta::class,"tar_id");
    }

    public function Galeria()
    {
        return $this->hasMany(Galeria::class,"tar_id");
    }

    public function Archivo()
    {
        return $this->hasMany(Archivo::class,"tar_id");
    }

}
