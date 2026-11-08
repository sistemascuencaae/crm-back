<?php

namespace App\Models;

use App\Events\SesionUsuarioEvent; // 08-nov: aviso de cambio de agencia o perfil
use App\Models\configuracion\Agencia;
use App\Models\configuracion\UsuarioCHorario;
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
        "bod_id",
        "bod_id_dos",
        "bod_id_tres",
        "ult_inicio_sesion",
        "id_usu_crea_actualiza",
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // **START**
    // Este bloque se llama accessor y sirve para que no tenga que estar llamando en cada select al usuario_completo
    // El $appends se agrega para no estar llamando usuario_completo cada vez que lo necesite, sino ya viene en el objeto usuario
    public function getUsuarioCompletoAttribute()
    {
        return $this->usu_alias . ' - ' . $this->name . ' ' . $this->surname;
    }

    protected $appends = ['usuario_completo'];
    // **END**

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

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, "alm_id", "codigo");
    }

    public function horario()
    {
        return $this->belongsTo(UsuarioCHorario::class, "id", "user_id");
    }

    public function usuario_crea_actualiza()
    {
        return $this->belongsTo(User::class, "id_usu_crea_actualiza", "id");
    }

    // 08-nov: si al guardar cambió la agencia (alm_id) o el perfil (profile_id), se avisa al usuario por su canal usuario.{id}
    // (SesionUsuarioEvent): el front muestra el aviso con Entendido y recarga. Cubre todo lo que guarda con el modelo: Editar usuario
    // (UserController::editUser) y la agencia de la cajera desde Dynamo (UserDynamoController). Un cambio directo en la base no pasa
    // por aquí: lo detecta el front en el siguiente clic o al volver a la app.
    // Cambio de agencia (soporte) guarda con una función SQL y avisa desde su controlador (CambioAgenciaController).
    // El aviso sale después del commit y nunca tumba el guardado (SesionUsuarioEvent::avisar).
    protected static function booted()
    {
        static::updated(function (User $usuario) {
            $cambios = [];
            if ($usuario->wasChanged('alm_id')) {
                $cambios[] = 'agencia';
            }
            if ($usuario->wasChanged('profile_id')) {
                $cambios[] = 'perfil';
            }
            if (!$cambios) {
                return;
            }

            SesionUsuarioEvent::avisar((int) $usuario->id, $cambios);
        });
    }
}
