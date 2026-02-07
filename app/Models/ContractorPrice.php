<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorPrice extends Model
{
    protected $table = 'contractor_prices';

    protected $fillable = [
        'delivery_date',
        'amount',
        'price',
        'article_id',
        'contractor_id',
    ];
}
