<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    public $guarded = [];

    public function scopeOfType($builder, $type)
    {
        return $builder->where('payable_type', $type);
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
