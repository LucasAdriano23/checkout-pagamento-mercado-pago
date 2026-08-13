<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FeatureSku extends Pivot
{
    use SoftDeletes;

    protected $fillable = [
        'sku_id',
        'feature_id',
        'value',
    ];
}
