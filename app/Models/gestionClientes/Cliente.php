<?php

namespace App\Models\gestionClientes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'crm.clientes';

    protected $fillable = [
        'id',
        'zona_id',
        'identificacion',
        'tipo_identificacion',
        'nombres',
        'apellidos',
        'email',
        'telefono_1',
        'telefono_2',
        'telefono_3',
        'calle_principal',
        'calle_secundaria',
        'estado',
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

    // Relación con zona
    public function zona()
    {
        return $this->belongsTo(Zona::class, 'zona_id', 'id');
    }

    // Relación con contactos
    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'cliente_id', 'id');
    }

    // Relación con usuarios asignados
    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'crm.cliente_usuario', 'cliente_id', 'user_id')
            ->withTimestamps();
    }

    // Relación con actividades
    public function actividades()
    {
        return $this->hasMany(ActividadCliente::class, 'cliente_id', 'id');
    }

    // Relación con adjuntos
    public function adjuntos()
    {
        return $this->hasMany(AdjuntoCliente::class, 'cliente_id', 'id');
    }
}
