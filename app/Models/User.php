<?php

namespace App\Models;

use App\Models\crm\Almacen;
use App\Models\crm\PerfilAnalistas;
use App\Models\crm\Tablero;
use App\Models\crm\TableroUsuario;
use App\Models\crm\UsuarioDynamo;
use App\Models\crm\Departamento;
use App\Models\openceo\PuntoVenta;
use App\Models\Profile;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $table = 'crm.users';
    use HasFactory, Notifiable;

    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    //     'usu_id',
    // ];

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'phone',
        'fecha_nacimiento',
        'website',
        'address',
        'surname',
        'avatar',
        'fb',
        'tw',
        'inst',
        'linke',
        'usu_id',
        'usu_dep_id',
        'usu_tipo_analista',
        'dep_id',
        'estado',
        'usu_tipo',
        'usu_alias',
        "tab_id",
        "pve_id",
        "estado_sesion",
        "en_linea",
        "profile_id",
        "alm_id",
        "cedula",
        "emp_id",
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function setPasswordAttribute($password)
    {
        if ($password) {
            $this->attributes["password"] = bcrypt($password);
        }
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function UsuarioDynamo()
    {
        return $this->belongsTo(UsuarioDynamo::class, "usu_id");
    }

    public function Departamento()
    {
        return $this->belongsTo(Departamento::class, "dep_id", "id");
    }

    public function tablero()
    {
        return $this->hasMany(TableroUsuario::class, "user_id", "id");
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
    public function setDeletedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["deleted_at"] = Carbon::now();
    }

    public function puntoVenta()
    {
        return $this->belongsTo(PuntoVenta::class, "pve_id", "pve_id");
    }

    public function perfil_analista()
    {
        return $this->belongsTo(PerfilAnalistas::class, "usu_tipo_analista");
    }

    public function perfil()
    {
        return $this->belongsTo(Profile::class, "profile_id", "id");
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, "alm_id", "alm_id");
    }

}
