<?php

namespace App\Models;

use App\Models\configuracion\ZonaAgencia;
use Illuminate\Database\Eloquent\Model;

class Servidor extends Model
{
    protected $table = 'crm.servidores';

    protected $fillable = [
        'nombre',
        'ip',
        'usuario',
        'clave',
        'archivo',
        'estado',
        'link',
        'id_zona_agencia',
    ];

    protected $hidden = [
        'usuario',
        'clave',
        'archivo',
    ];

    public function zona()
    {
        return $this->belongsTo(ZonaAgencia::class, "id_zona_agencia");
    }
}
