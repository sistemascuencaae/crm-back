<?php

namespace App\Models\Formulario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampoLikert extends Model
{
    protected $table = 'crm.campo_likert';

    protected $fillable = [
        'campo_id',
        'fcl_id',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    public function formCampo()
    {
        return $this->belongsTo(FormCampo::class, 'campo_id', 'id');
    }

    public function formCampoLikert()
    {
        return $this->belongsTo(FormCampoLikert::class, 'fcl_id', 'id');
    }
}
