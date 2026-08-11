<?php

namespace App\Models\anuncios;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnuncioVisto extends Model
{
    use HasFactory;

    protected $table = 'crm.anuncios_vistos';

    protected $fillable = [
        "anuncio_id",
        "user_id",
    ];

    public function anuncio()
    {
        return $this->belongsTo(Anuncio::class, "anuncio_id", "id");
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, "user_id", "id");
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
