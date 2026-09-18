<?php

namespace App\Models\fileManager;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FmCarpetaUsuario extends Model
{
    use HasFactory;

    protected $table = 'crm.fm_carpeta_usuario';

    protected $fillable = [
        'carpeta_id',
        'user_id',
        'puede_ver',
        'puede_descargar',
        'puede_subir_archivos',
        'puede_crear_subcarpetas',
        'puede_renombrar',
        'puede_eliminar',
        'puede_mover',
        'puede_gestionar_permisos',
        'otorgado_por',
    ];

    protected $casts = [
        'puede_ver' => 'boolean',
        'puede_descargar' => 'boolean',
        'puede_subir_archivos' => 'boolean',
        'puede_crear_subcarpetas' => 'boolean',
        'puede_renombrar' => 'boolean',
        'puede_eliminar' => 'boolean',
        'puede_mover' => 'boolean',
        'puede_gestionar_permisos' => 'boolean',
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

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function otorgador()
    {
        return $this->belongsTo(User::class, 'otorgado_por');
    }
}
