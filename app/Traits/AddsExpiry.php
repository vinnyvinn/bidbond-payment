<?php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;


trait AddsExpiry
{
  public static function bootAddsExpiry()
  {
    static::creating(function ($model) {
      $model->saveExpiry($model);
    });
  }

  protected function saveExpiry($model)
  {

    $model->email_code_expiry = Carbon::now()->addDay();

  
  }
}
