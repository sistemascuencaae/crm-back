<?php

namespace App\Models;

use App\Models\crm\UsuarioDynamo;
use App\Models\crm\Departamento;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $table = 'public.users';
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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
        'created_at',
        'updated_at',
        'phone',
        'birthdate',
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
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
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

}
























