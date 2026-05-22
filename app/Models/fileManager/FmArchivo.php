<?php

namespace App\Models\fileManager;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FmArchivo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm.fm_archivo';

    protected $fillable = [
        'carpeta_id',
        'nombre',
        'extension',
        'mime_type',
        'tamano_bytes',
        'ruta_fisica',
        'disk',
        'hash_sha256',
        'version',
        'archivo_padre_id',
        'es_version_actual',
        'creado_por',
        'descripcion',
    ];

    protected $casts = [
        'tamano_bytes' => 'integer',
        'version' => 'integer',
        'es_version_actual' => 'boolean',
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['created_at'] = Carbon::now();
    }

    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['updated_at'] = Carbon::now();
    }

    public function carpeta()
    {
        return $this->belongsTo(FmCarpeta::class, 'carpeta_id');
    }

    public function permisos()
    {
        return $this->hasMany(FmArchivoUsuario::class, 'archivo_id');
    }

    public function tags()
    {
        return $this->belongsToMany(FmTag::class, 'crm.fm_archivo_tag', 'archivo_id', 'tag_id');
    }

    public function versiones()
    {
        return $this->hasMany(FmArchivo::class, 'archivo_padre_id');
    }

    public function archivoPadre()
    {
        return $this->belongsTo(FmArchivo::class, 'archivo_padre_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
