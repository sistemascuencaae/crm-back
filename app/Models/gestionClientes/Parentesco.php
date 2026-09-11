<?php

namespace App\Models\gestionClientes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parentesco extends Model
{
    use HasFactory;

    protected $table = 'crm.parentesco';

    protected $fillable = [
        'nombre',
    ];

    public $timestamps = false;

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'parentesco_id', 'id');
    }
}
