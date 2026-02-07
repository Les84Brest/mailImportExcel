<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaraPolcarItem extends Model
{

    protected $table = 'lara_polcar_items';

    protected $fillable = [
        'title',
        'part_title',
        'oem',
        'producer',
        'polcar_car_id',
        'table_info',
        'images'
    ];


    protected $casts = [
        'table_info' => 'array',
        'images' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];


    public function polcarCar(): BelongsTo
    {
        return $this->belongsTo(PolcarCar::class, 'polcar_car_id');
    }


    public function getFirstImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }


    public function scopeByOem($query, $oem)
    {
        return $query->where('oem', 'like', "%{$oem}%");
    }


    public function scopeByProducer($query, $producer)
    {
        return $query->where('producer', 'like', "%{$producer}%");
    }
}