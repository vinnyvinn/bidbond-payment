<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('safaricom/c2b/validation/callback/{secret}', 'MPESAController@c2bValidation');

Route::post('safaricom/c2b/confirmation/callback/{secret}', 'MPESAController@c2bConfirmation');

Route::post('safaricom/requestPayment', 'MPESAController@requestPayment');

Route::post('safaricom/confirmPayment', 'MPESAController@confirmPayment');

Route::post('safaricom/confirmTransaction', 'MPESAController@confirmTRX');

Route::post('safaricom/trx-status/timeout/callback{secret}', 'MPESAController@trxStatusTimeout');

Route::post('safaricom/trx-status/confirmation/callback/{secret}', 'MPESAController@trxStatusConfirmation');

Route::post('safaricom/stk/confirmation/callback/{secret}', 'MPESAController@stkConfirmation');

Route::get('payments', 'PaymentController@index');

Route::post('payment/paid', 'MPESAController@setPaid');

Route::get('payment/{account}/account', 'PaymentController@getByAccount');

Route::get('payments/account', 'PaymentController@getByPayableIds');

Route::get('wallet/balance/{type}/{type_id}', 'WalletController@getBalance');

Route::post('wallet/balance','WalletController@getWallets');

Route::post('wallet/pay', 'WalletController@transact');
Route::post('wallet/pay/atm', 'WalletController@transactAtm');

Route::get('wallet/{type}/{type_id}/transactions', 'WalletController@walletStatement');

Route::get('wallet/transactions', 'WalletController@index');

Route::get('reports/transactions', 'WalletController@getTransactions');

Route::get('migrate',function (){
    Artisan::call('migrate:fresh --force');
    return response()->json(["message"=>"payment migrate fresh success"]);
});
