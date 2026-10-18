<?php

namespace App\Models\crm;

// use App\Models\crm\TipoCaso;

use App\Models\crm\formularios2\Form;
use App\Models\Formulario\FormularioTipoCaso;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use App\Models\configuracion\TipoCasoTablero;

class TipoCaso extends Model
{

    use HasFactory;

    protected $table = 'crm.tipo_caso';

    protected $fillable = ["nombre", "estado", "form_id", "tab", "categoria_caso", "tiempo_vencimiento"];

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

    // public function cTipoTarea()
    // {
    //     return $this->belongsTo(CTipoTarea::class, 'ctt_id');
    // }

    public function tipoCasoCTipoTarea()
    {
        return $this->belongsTo(TipoCasoCTipoTarea::class, 'id','tipo_caso_id');
    }

    public function formTipoCaso()
    {
        return $this->belongsTo(FormularioTipoCaso::class, 'id', 'tc_id');
    }

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function tipo_caso_tablero()
    {
        return $this->hasMany(TipoCasoTablero::class, 'tipo_caso_id');
    }

}
