<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'hclinico.company';

    protected $fillable = [
        'id',
        'name',
        'nickname',
        'ruc',
        'city',
        'address',
        'phone',
        'closedate',
        'iva',
        'mora',
        'image',
        'social_name',
        'cash',
        'final_customer',
        'print_direct',
        'edit_price',
        'edit_discount',
        'isstore',
        'isseries',
        'num_decimal',
        'account_now',
        'account_old',
        'apikey',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

}
