<?php

namespace App\Models\crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'crm.departamento';

    // protected $primaryKey = 'dep_id';

    protected $fillable = [
        'dep_nombre',
        'dep_descripcion',
        'estado'
    ];

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

    public function users()
    {
        return $this->hasMany(User::class, "dep_id");
    }
}