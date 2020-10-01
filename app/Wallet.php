<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['type', 'type_id', 'balance'];


    public function scopeOfType($builder, $type):void
    {
        $builder->where('type', $type);
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
