<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolcarCar extends Model
{
    use HasFactory;
    protected $table = 'polcar_cars';

    protected $fillable = [
        'brand',
        'model',
        'production_year',
    ];


    protected $casts = [

        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
