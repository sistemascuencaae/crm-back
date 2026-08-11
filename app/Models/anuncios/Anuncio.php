<?php

namespace App\Models\anuncios;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Anuncio extends Model
{
    use HasFactory;

    protected $table = 'crm.anuncios';

    protected $fillable = [
        "titulo",
        "descripcion",
        "fecha_inicio",
        "fecha_fin",
        "activo",
        "ver_todos",
        "orden",
        "created_by",
    ];

    protected $casts = [
        "activo" => "boolean",
        "ver_todos" => "boolean",
        "orden" => "integer",
    ];

    /**
     * Interruptor general del modulo, en crm.parametro / abreviacion = 'ANUNCIOS'.
     *
     * Sirve para desplegar el codigo a un ambiente donde todavia NO existen
     * las tablas crm.anuncios*: mientras esto devuelva false, ningun endpoint
     * llega a consultarlas y nada revienta.
     *
     * Si la fila no existe, el modulo queda APAGADO. El default seguro es no
     * funcionar, no al reves.
     */
    public static function moduloActivo(): bool
    {
        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'ANUNCIOS')
            ->first();

        return $parametro && $parametro->activar == true;
    }

    public function imagenes()
    {
        return $this->hasMany(AnuncioImagen::class, "anuncio_id", "id")
            ->orderBy("orden")
            ->orderBy("id");
    }

    public function destinos()
    {
        return $this->hasMany(AnuncioDestino::class, "anuncio_id", "id");
    }

    public function vistos()
    {
        return $this->hasMany(AnuncioVisto::class, "anuncio_id", "id");
    }

    public function creador()
    {
        return $this->belongsTo(User::class, "created_by", "id");
    }

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
}
