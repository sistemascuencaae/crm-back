<?php

namespace App\Models\crm;

use App\Models\crm\credito\ClienteFae;
use App\Models\crm\Cliente;
use App\Models\crm\credito\ReferenAnexo;
use App\Models\crm\Direccion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Entidad extends Model
{

    use HasFactory;
    protected $table = 'public.entidad';

    protected $primaryKey = 'ent_id';

    public $timestamps = false;

    protected $fillable = [
        "ent_identificacion",
        "ent_nombres",
        "ent_apellidos",
        "ent_contacto",
        "tit_id",
        "ent_fechanacimiento",
        "ent_direccion_principal",
        "locked",
        "ent_foto",
        "ent_tipo_identificacion",
        "alm_id",
        "ent_email",
        "ent_telefono_principal",
        "tii_id",
    ];

    public function cliente()
    {
        return $this->hasMany(Cliente::class, "ent_id");
    }

    public function direccion()
    {
        return $this->belongsTo(Direccion::class, "ent_direccion_principal");
    }

    public function clientefae()
    {
        return $this->hasMany(ClienteFae::class, "ent_id");
    }
    public function referenanexo()
    {
        return $this->hasMany(ReferenAnexo::class, "ent_id");
    }

}

// select * from public.entidad ent inner join public.cliente cli on cli.ent_id = ent.ent_id
