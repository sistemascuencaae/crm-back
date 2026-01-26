<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    use HasFactory;

    protected $table = 'crm.zona';

    protected $fillable = [
        'id',
        'codigo',
        'nombre',
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

    // Relación con clientes
    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'zona_id', 'id');
    }
}
