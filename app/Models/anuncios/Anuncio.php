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

    public const ABREVIACION_PARAMETRO = 'ANUNCIOS';

    public const EXTRA_MARCA_AGUA = 'marca_agua';

    private static function parametro()
    {
        return DB::table('crm.parametro')
            ->where('abreviacion', self::ABREVIACION_PARAMETRO)
            ->first();
    }

    public static function moduloActivo(): bool
    {
        $parametro = self::parametro();

        return $parametro && $parametro->activar == true;
    }

    public static function configuracion(): array
    {
        $parametro = self::parametro();
        $activo = $parametro && $parametro->activar == true;

        $extras = $activo
            ? array_map('trim', explode(',', strtolower($parametro->valor ?? '')))
            : [];

        return [
            'modulo_activo' => $activo,
            'marca_agua' => in_array(self::EXTRA_MARCA_AGUA, $extras, true),
        ];
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
