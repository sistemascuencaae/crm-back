<?php

namespace App\Models;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    // protected $table = 'hclinico.access';
    protected $table = 'crm.access';

    protected $fillable = [
        'profile_id',
        'menu_id',
        'view',
        'create',
        'edit',
        'delete',
        'report',
        // 'other',
        'ejecutar'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

}
