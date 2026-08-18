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

    /** abreviacion de la fila de crm.parametro que gobierna el modulo */
    public const ABREVIACION_PARAMETRO = 'ANUNCIOS';

    /** extra reconocido dentro de la columna 'valor' */
    public const EXTRA_MARCA_AGUA = 'marca_agua';

    /**
     * ÚNICO lugar de todo el backend que consulta crm.parametro para anuncios.
     *
     * Todo lo demas -los dos controladores y el endpoint de configuracion que
     * consume el frontend- pasa por aqui. Antes la consulta estaba repetida y
     * ademas el frontend tenia su propio interruptor en codigo comentado, asi
     * que apagar el modulo eran tres cosas que mantener en sincronia a mano.
     *
     * No se cachea a proposito: la gracia es que un UPDATE surta efecto en la
     * siguiente peticion, sin reiniciar nada.
     */
    private static function parametro()
    {
        return DB::table('crm.parametro')
            ->where('abreviacion', self::ABREVIACION_PARAMETRO)
            ->first();
    }

    /**
     * Interruptor general del modulo: columna 'activar'.
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
        $parametro = self::parametro();

        return $parametro && $parametro->activar == true;
    }

    /**
     * Todo lo que el frontend necesita saber, con UNA sola consulta.
     *
     * Resuelve las dos banderas de la misma fila en una pasada. Antes esto se
     * armaba llamando a moduloActivo() y a un extraActivo() por separado, y
     * cada uno releia crm.parametro: tres SELECT para responder una peticion.
     *
     * La columna 'valor' dice QUE se muestra y se lee como lista separada por
     * comas aunque hoy solo lleve un elemento, asi se le pueden sumar extras
     * despues sin migrar la tabla ni cambiar esta firma.
     *
     * Los extras cuelgan del modulo: con activar = false salen todos en false
     * aunque 'valor' los nombre. Sin anuncios no hay nada que mostrar.
     */
    public static function configuracion(): array
    {
        $parametro = self::parametro();
        $activo = $parametro && $parametro->activar == true;

        $extras = $activo
            ? array_map('trim', explode(',', strtolower($parametro->valor ?? '')))
            : [];

        return [
            'modulo_activo' => $activo,
            'marca_agua'    => in_array(self::EXTRA_MARCA_AGUA, $extras, true),
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
