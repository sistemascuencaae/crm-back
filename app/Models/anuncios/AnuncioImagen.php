<?php

namespace App\Models\anuncios;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnuncioImagen extends Model
{
    use HasFactory;

    protected $table = 'crm.anuncios_imagenes';

    protected $fillable = [
        "anuncio_id",
        "ruta",
        "alt",
        "orden",
    ];

    protected $casts = [
        "orden" => "integer",
    ];

    public function anuncio()
    {
        return $this->belongsTo(Anuncio::class, "anuncio_id", "id");
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
