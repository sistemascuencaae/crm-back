<?php

namespace App\Models\fileManager;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FmTag extends Model
{
    use HasFactory;

    protected $table = 'crm.fm_tag';

    protected $fillable = [
        'nombre',
        'color',
        'creado_por',
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

    public function archivos()
    {
        return $this->belongsToMany(FmArchivo::class, 'crm.fm_archivo_tag', 'tag_id', 'archivo_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
