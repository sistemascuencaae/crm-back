<?php

namespace App\Models\crm;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableroUsuario extends Model
{
    use HasFactory;

    protected $table = 'crm.tablero_user';
    protected $primaryKey = 'tu_id';
    protected $fillable = [
        "user_id",
        "tab_id",
    ];
    public function usuario()
    {
        return $this->hasMany(User::class, "id", "user_id");
    }

}