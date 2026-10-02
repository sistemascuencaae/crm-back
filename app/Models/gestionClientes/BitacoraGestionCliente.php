<?php

namespace App\Models\gestionClientes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BitacoraGestionCliente extends Model
{
    use HasFactory;

    protected $table = 'crm.bitacora_gestion_clientes';

    protected $fillable = [
        'user_id',
        'accion',
        'valores_anteriores',
        'valores_nuevos',
        'actividad_cliente_id',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array'
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

    // Relación con usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
