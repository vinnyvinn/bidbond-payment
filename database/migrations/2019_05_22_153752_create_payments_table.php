<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->integer('amount');
            $table->string('account');
            $table->integer('customer_id')->nullable()->default(null);
            $table->string('companyid')->nullable();
            $table->string('transaction_number');
            $table->dateTime('transaction_date');
            $table->string('payable_type');
            $table->string('payable_id');
            $table->boolean('processed')->default(0);
            $table->boolean('confirmed')->default(0);
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
