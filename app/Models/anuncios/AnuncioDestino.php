<?php

namespace App\Models\anuncios;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnuncioDestino extends Model
{
    use HasFactory;

    protected $table = 'crm.anuncios_destinos';

    /**
     * La tabla NO tiene CHECK sobre 'tipo' a proposito, para poder agregar
     * tipos nuevos sin ALTER. Por eso la validacion vive aqui: un valor
     * fuera de esta lista entraria a la base sin error y el anuncio no le
     * llegaria a nadie, en silencio.
     */
    public const TIPO_DEPARTAMENTO = "departamento";
    public const TIPO_PERFIL       = "perfil";
    public const TIPO_USUARIO      = "usuario";

    public const TIPOS = [
        self::TIPO_DEPARTAMENTO,
        self::TIPO_PERFIL,
        self::TIPO_USUARIO,
    ];

    /** contra que columna de crm.users se compara destino_id segun el tipo */
    public const COLUMNA_USUARIO = [
        self::TIPO_DEPARTAMENTO => "dep_id",
        self::TIPO_PERFIL       => "profile_id",
        self::TIPO_USUARIO      => "id",
    ];

    protected $fillable = [
        "anuncio_id",
        "tipo",
        "destino_id",
    ];

    protected $casts = [
        "destino_id" => "integer",
    ];

    public function anuncio()
    {
        return $this->belongsTo(Anuncio::class, "anuncio_id", "id");
    }

    public static function tipoValido($tipo): bool
    {
        return in_array($tipo, self::TIPOS, true);
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
