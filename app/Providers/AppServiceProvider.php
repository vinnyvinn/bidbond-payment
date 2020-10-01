<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('phone', function($attribute, $value, $parameters, $validator) {
            return preg_match("/^0[1-9][0-9][ ]?[0-9]{7}$/", $value);
        });

        Validator::extend('number_format', function($attribute, $value, $parameters, $validator) {
            return preg_match("/^(?:\d{1,3}(?:[,. ]\d{3})*|\d*)$/", $value);
        });
    }
}
