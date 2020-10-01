<?php

namespace App\Http\Controllers;

use App\Jobs\TransactionQuery;
use App\Services\GatewayService;
use App\Wallet;
use App\WalletTransaction;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use App\Payment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Knox\MPESA\Facades\MPESA;
use Illuminate\Support\Str;
use function info;

class MPESAController extends Controller
{

    public $gateway_service;

    use ApiResponser;

    public function __construct(GatewayService $gateway_service)
    {
        $this->gateway_service = $gateway_service;
    }

    public function setPaid(Request $request)
    {

        $payment = Payment::where('account', $request->account)->first();
        $payment->update(['processed' => 1]);

        return response()->json(["payment" => $payment]);
    }

    public function registerC2B(Request $request)
    {
        //$mpesa = MPESA::registerC2bUrl();
        //$mpesa = MPESA::stkPush('254723238631', 1, 'CBL Test');

        //dd($mpesa);
    }

    public function c2bValidation(Request $request)
    {

        $response = $request->all();
         Log::info('C2B Validation: ' . request()->ip());

        $mpesa_transaction_id = $response['TransID'];
        $date_time = Carbon::parse($response['TransTime']);
        $amount = $response['TransAmount'];
        $account = strtoupper(preg_replace('/\s+/', '', $response['BillRefNumber']));
        $phone = $response['MSISDN'];
        $name = ($response['FirstName'] ?? '') . ' ' . ($response['MiddleName'] ?? '') . ' ' . ($response['LastName'] ?? '');
        $payer = preg_replace('!\s+!', ' ', ucwords(strtolower($name)));

        $type_id = $response['type_id'];

        if (!$mpesa_transaction_id || !$date_time || !$amount || !$account || !$phone || !$payer) {
            return response()->json(["ResultCode" => 1, "ResultDesc" => "Failure"]);
        }

        $exists = Payment::where('transaction_number', $mpesa_transaction_id)->count();

        if ($exists == 0) {
            if ($account[0] == "C") {
                $payable_type = "App\Company";
            } else if ($account[0] == "B") {
                $payable_type = "App\Bidbond";
            } else if ($account[0] == "A") {
                $payable_type = "App\Agent";
            }

            $payment = new Payment;
            $payment->transaction_number = $mpesa_transaction_id;
            $payment->transaction_date = $date_time;
            $payment->amount = $amount;
            $payment->account = $account;
            $payment->name = $payer;
            $payment->phone = $phone;
            $payment->payment_method = 'MPESA';
            $payment->payable_type = $payable_type;
            $payment->payable_id = $type_id;
            $payment->save();
        }

        $message = ["ResultCode" => 0, "ResultDesc" => "Success", "ThirdPartyTransID" => "ds2gqALVbSdWKhYHo2qciTuc86YhXDL1P"];

//        if (config('app.url') !== 'http://payment.test') {
//            TransactionQuery::dispatch($mpesa_transaction_id)->delay(now()->addSeconds(3));
//        }

        info("c2bvalidation", $message);
        return response()->json($message);
    }

    public function c2bConfirmation(Request $request)
    {
        $response = $request->all();

        Log::info('C2B Confirmation: ' . request()->ip());
        Log::info($response);

        $mpesa_transaction_id = $response['TransID'];

        if (!$mpesa_transaction_id) {
            return response()->json(["ResultCode" => 1, "ResultDesc" => "Failure"]);
        }

        $this->updatePayment($mpesa_transaction_id);

        return response()->json(["ResultCode" => 0, "ResultDesc" => "Success"]);
    }

    public function trxStatusTimeout(Request $request)
    {
        $response = $request->all();
        Log::info($response);

        return response()->json(["ResultCode" => 0, "ResultDesc" => "Success"]);
    }

    public function trxStatusConfirmation(Request $request)
    {
        $response = $request->all();

        $result_code = $response['Result']['ResultCode'];
        $result_type = $response['Result']['ResultType'];
        if ($result_code == '0' && $result_type == '0') {
            $collection = collect($response['Result']['ResultParameters']['ResultParameter']);

            $transaction = $collection->where('Key', 'ReceiptNo')->first();
            $status = $collection->where('Key', 'TransactionStatus')->first();

            if ($status['Value'] === 'Completed') {
                $this->updatePayment($transaction['Value']);
            }
        }

        return response()->json(["ResultCode" => 0, "ResultDesc" => "Success"]);
    }

    public function stkConfirmation(Request $request)
    {
        $response = $request->all();
        Log::info('STK Confirmation: ' . request()->ip());
        Log::info($response);

        $result_code = $response['Body']['stkCallback']['ResultCode'];
        if ($result_code == '0') {
            $collection = collect($response['Body']['stkCallback']['CallbackMetadata']['Item']);

            $transaction = $collection->where('Name', 'MpesaReceiptNumber')->first();
            $this->updatePayment($transaction['Value']);
        }

        $message = ["ResultCode" => 0, "ResultDesc" => "Success"];

        return response()->json($message);
    }

    private function updatePayment($transaction)
    {
        $payment = Payment::where('transaction_number', $transaction)->first();

        if ($payment) {
            if (!$payment->confirmed) {
                $payment->confirmed = 1;

                if (substr($payment->account, 1, 1) === 'W') {
                    $this->topUp($payment);
                    $payment->processed = 1;
                }

                $payment->save();
            }
        }
    }

    /**
     * @param $payment
     */
    protected function topUp($payment): void
    {
        if ($payment->account[0] == "C") {
            $type = "Company";
        } else {
            $type = "Agent";
        }

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

    public function requestPayment(Request $request)
    {
        $phone = $request->input('phone');
        $phone = preg_replace('/0/', '254', $phone, 1);
        $amount = $request->input('amount');

        $account = $request->input('real_account');
          info('amount -> '.$amount.' phone -> '.$phone.' account -> '.$account);
        $mpesa = MPESA::stkPush($phone, $amount, $account);
    //  info('coool now------------');
        return response()->json($mpesa);
    }

    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account' => 'required',
            'expected_amount' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'confirmed' => false,
                'error' => ['message' => $validator->errors()->all()]
            ], 422);
        }

        if (substr($request->input('account'), 1, 1) === 'W') {
            $payment = Payment::where('account', $request->input('account'))
                ->latest()->first();

            return response()->json([
                    'status' => 'success',
                    'confirmed' => true,
                    'account' => $request->input('account'),
                    'transaction_number' => $payment->transaction_number,
                    'message' => 'Payment confirmed and processed.'
                ], 200);
        }

        //Handle edge case
        $payment = Payment::where('account', $request->input('account'))
            ->where('processed', 0)
            ->where('confirmed', 1)->latest()->first();;
        //check sum of total payments
        $total_paid = Payment::where('account', $request->input('account'))->where('processed', 0)
            ->where('confirmed', 1)
            ->sum('amount');

        if ($total_paid == 0) {
            return response()->json([
                'status' => 'error',
                'confirmed' => false,
                'error' => [
                    'code' => 'input_invalid',
                    'message' => 'Payment not received. Make payment and try again.'
                ]
            ], 200);
        } else if ($total_paid < $request->expected_amount) {
            $bal = $request->expected_amount - $total_paid;
            return response()->json([
                'status' => 'error',
                'confirmed' => false,
                'code' => 'amount_insufficient',
                'total_paid' => $total_paid,
                'error' => [
                    'code' => 'input_invalid',
                    'message' => 'Top up ' . $bal . ' to complete your payment.'
                ]], 200);
        }

        return response()->json([
                'status' => 'success',
                'confirmed' => true,
                'account' => $request->input('account'),
                'total_paid' => $total_paid,
                'transaction_number' => $payment->transaction_number,
                'message' => 'Payment confirmed and processed.'
            ], 200);
    }

    public function confirmTRX(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_code' => 'bail|exists:payments,transaction_number',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'no_payment',
                'confirmed' => false,
                'error' => [
                    'code' => 'input_invalid',
                    'message' => $validator->errors()->all()
                ]
            ], 422);
        }

        $payment = Payment::where('transaction_number', strtoupper($request->input('transaction_code')))
            ->where('processed', 0)->where('confirmed', 1)->first();

        $expected_amount = $request->input('amount');

        if ($payment) {
            $total_paid = Payment::where('account', $payment->account)->where('processed', 0)
                ->where('confirmed', 1)
                ->sum('amount');

//            if ($expected_amount > $total_paid) {
//                $diff = $expected_amount - $total_paid;
//                return response()->json([
//                    'status' => 'error',
//                    'confirmed' => false,
//                    'error' => [
//                        'code' => 'amount_insufficient',
//                        'balance' => $diff,
//                        'total_paid' => $total_paid,
//                        'message' => 'Payment received for a total of KSHS ' . $total_paid . ' .Pay KES ' . $diff . ' more to complete transaction',
//                    ]
//                ], 422);
//            }
            return response()->json([
                'status' => 'success',
                'confirmed' => true,
                'account' => $payment->real_account,
                'transaction_number' => $payment->transaction_number,
                'total_paid' => $total_paid,
                'message' => 'Payment received for a total of KSHS ' . $total_paid
            ], 200);

        } else {
            return response()->json([
                'status' => 'error',
                'confirmed' => false,
                'code' => 'no_payment',
                'error' => [
                    'code' => 'input_invalid',
                    'message' => 'Payment not received. Make payment and try again.'
                ]
            ], 200);
        }
    }
}
