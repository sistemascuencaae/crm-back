<?php

namespace App\Models\Formulario;

use App\Models\crm\Caso;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormValor extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'crm.form_valor';
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'user_id',
        'pac_id',
        'caso_id',
        'valor_texto',
        'valor_date',
        'valor_array',
        'valor_json',
        'valor_boolean',
        'valor_decimal',
        'valor_entero',
        'key'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    public function caso()
    {
        return $this->belongsTo(Caso::class, 'caso_id', 'id');
    }

    public function campoValores()
    {
        return $this->hasMany(FormCampoValor::class, 'valor_id', 'id');
    }
}
