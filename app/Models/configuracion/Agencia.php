<?php

namespace App\Models\configuracion;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agencia extends Model
{
    use HasFactory;

    protected $table = 'crm.agencia';
    // 30-oct: consulta_proveedor (GARANCHECK | YUPANA | vacío = sin calificador) y codigo_tienda_proveedor (codTienda de
    // Yupana; solo Yupana lo usa). Las escriben crm.fn_agencia_crear/_modificar; aquí solo para leerlas.
    protected $fillable = ["codigo", "nombre", "estado", "id_zona_agencia", "consulta_proveedor", "codigo_tienda_proveedor"];

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

    public function zona_agencia()
    {
        return $this->belongsTo(ZonaAgencia::class, 'id_zona_agencia');
    }
}
