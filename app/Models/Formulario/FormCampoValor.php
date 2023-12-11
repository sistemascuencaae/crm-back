<?php

namespace App\Models\Formulario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormCampoValor extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.form_campo_valor';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'user_id',
        'campo_id',
        'valor_id',
        'fcl_id',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    public function campoLinkert()
    {
        return $this->belongsTo(FormCampoLikert::class, "id", "fcl_id");
    }
    public function campo()
    {
        return $this->belongsTo(FormCampo::class, "id", "campo_id");
    }


}
