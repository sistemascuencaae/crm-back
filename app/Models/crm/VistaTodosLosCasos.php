<?php

namespace App\Models\crm;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class VistaTodosLosCasos extends Model
{

    protected $table = 'crm.av_todos_los_casos';

    protected $fillable = [
        'agencia',
        'caso_id',
        'nombre',
        'fecha_inicio',
        'fecha_vencimiento',
        'tab_id',
        'nombre_tablero',
        'fas_id',
        'fase_nombre',
        'fase_color',
        'user_id',
        'dueno_caso',
        'estado_2',
        'prioridad',
        'ent_id',
        'cliente',
        'tc_id',
        'user_creador_id',
        'descripcion',
        'cliente_caso',
        'identificacion',
        'comprobante',
        'datos_formulario',
        'tipo_caso',
        'nombre_categoria_caso',
        'acc_publico',
        'acceso_caso',
        'requerimientos',
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

    public function estadodos()
    {
        return $this->belongsTo(Estados::class, "estado_2", "id");
    }
}
