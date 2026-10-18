<?php

namespace App\Models\fileManager;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FmArchivoUsuario extends Model
{
    use HasFactory;

    protected $table = 'crm.fm_archivo_usuario';

    protected $fillable = [
        'archivo_id',
        'user_id',
        'puede_ver',
        'puede_descargar',
        'puede_renombrar',
        'puede_editar_contenido',
        'puede_eliminar',
        'puede_mover',
        'puede_gestionar_permisos',
        'otorgado_por',
    ];

    protected $casts = [
        'puede_ver' => 'boolean',
        'puede_descargar' => 'boolean',
        'puede_renombrar' => 'boolean',
        'puede_editar_contenido' => 'boolean',
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

    public function archivo()
    {
        return $this->belongsTo(FmArchivo::class, 'archivo_id');
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
