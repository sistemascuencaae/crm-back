<?php

namespace App\Models\configuracion;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\crm\Tablero;
use App\Models\crm\TipoCaso;
use App\Models\crm\EstadosFormulas;

class TipoCasoTablero extends Model
{
    use HasFactory;

    protected $table = 'crm.tipo_caso_tablero';
    protected $fillable = ["tipo_caso_id", "tab_id"];

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

    public function tablero()
    {
        return $this->belongsTo(Tablero::class, 'tab_id', 'id');
    }

    public function tipo_caso()
    {
        return $this->belongsTo(TipoCaso::class, 'tipo_caso_id', 'id');
    }

    public function estado_formula()
    {
        return $this->hasMany(EstadosFormulas::class, 'tipo_caso_id', 'tipo_caso_id');
    }

}
