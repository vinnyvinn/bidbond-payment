<?php

namespace App\Http\Controllers;

use App\Payment;
use App\Wallet;
use App\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SidianController extends Controller
{
    public function c2bConfirmation(Request $request)
    {
        $response = $request->all();

        Log::info('Sidian Confirmation: ' . request()->ip());
        Log::info($response);

        $transaction_id = $response['TransactionID'];
        $date_time = Carbon::parse($response['TransactionDate']);
        $amount = $response['TransactionAmount'];
        $account = strtoupper(preg_replace('/\s+/', '', $response['account']));
        $phone = 'N/A';
        $payer = $response['PayerName'];
        $type_id = $response['type_id'];

        if (!$transaction_id || !$date_time || !$amount || !$account || !$phone || !$payer) {
            return response()->json(["code" => 422, "error" => ["Failure: Fields missing"]]);
        }

        $exists = Payment::where('transaction_number', $transaction_id)->count();

        if ($exists == 0) {
            if ($account[0] == "C") {
                $payable_type = "App\Company";
            } else if ($account[0] == "A") {
                $payable_type = "App\Agent";
            }

            $payment = new Payment;
            $payment->transaction_number = $transaction_id;
            $payment->transaction_date = $date_time;
            $payment->amount = $amount;
            $payment->account = $account;
            $payment->name = $payer;
            $payment->phone = $phone;
            $payment->payment_method = 'SIDIAN BANK TOPUP';
            $payment->payable_type = $payable_type;
            $payment->payable_id = $type_id;
            $payment->confirmed = 1;
            $payment->processed = 1;
            $payment->save();

            $this->topUp($payment);
        }


        $message = ["code" => 0, "message" => "success"];

        return response()->json($message);
    }

    /**
     * @param $payment
     */
    protected function topUp($payment): void
    {
        if ($payment->account[0] === "C") {
            $type = "Company";
        } else {
            $type = "Agent";
        }

        if($payment->account !== "C_UNKNOWN"){
            $wallet = Wallet::firstOrCreate(['type' => $type, 'type_id' => $payment->payable_id], ['balance' => 0]);
            $wallet->balance = $wallet->balance + $payment->amount;
            $wallet->save();

            //create wallet transaction
            $wallet_transaction = new WalletTransaction;
            $wallet_transaction->amount = $payment->amount;
            $wallet_transaction->user_id = $payment->id;
            $wallet_transaction->account = $payment->account;
            $wallet_transaction->transaction_number = getToken(10, 'capnum', 'WX');
            $wallet_transaction->transaction_date = Carbon::now();
            $wallet_transaction->payable_type = $type;
            $wallet_transaction->payable_id = $payment->payable_id;
            $wallet_transaction->type = "debit";
            $wallet_transaction->save();
        }

    }
}
