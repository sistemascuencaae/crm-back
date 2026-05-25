<?php

namespace App\Models\fileManager;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FmCarpeta extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm.fm_carpeta';

    protected $fillable = [
        'nombre',
        'parent_id',
        'materialized_path',
        'nivel',
        'creado_por',
        'color',
        'icono',
    ];

    protected $casts = [
        'nivel' => 'integer',
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

    public function parent()
    {
        return $this->belongsTo(FmCarpeta::class, 'parent_id');
    }

    public function hijos()
    {
        return $this->hasMany(FmCarpeta::class, 'parent_id');
    }

    public function archivos()
    {
        return $this->hasMany(FmArchivo::class, 'carpeta_id');
    }

    public function permisos()
    {
        return $this->hasMany(FmCarpetaUsuario::class, 'carpeta_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
