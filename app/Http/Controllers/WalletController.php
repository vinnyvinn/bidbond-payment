<?php

namespace App\Http\Controllers;

use App\Payment;
use App\Wallet;
use App\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function index()
    {
        return WalletTransaction::paginate();
    }

    public function getBalance($type, $type_id)
    {

        $wallet = Wallet::firstOrCreate(['type' => $type, 'type_id' => $type_id], ['balance' => 0]);

        return response()->json(['status' => 'success', 'balance' => $wallet->balance]);
    }


    public function getWallets(Request $request)
    {
        return response()->json([
            'wallets' => Wallet::ofType($request->type)->whereIn('type_id', $request->type_id)->get()
        ], 200);
    }

    public function transactAtm(Request $request){

        WalletTransaction::create([
            'amount' => $request->total,
            'user_id' => $request->user,
            'account' => "BP" . strtoupper($request->bidbond),
            'transaction_number' => $request->reference,
            'transaction_date' => Carbon::now(),
            'payable_type' => $request->role =='agent' ? 'agent' : 'company',
            'payable_id' => $request->account,
            'type' => "credit"
        ]);

        //set payment
        Payment::create([
            "transaction_number" => $request->reference,
            "transaction_date" => Carbon::now(),
            "amount" => $request->total,
            "account" => "BP" . strtoupper($request->bidbond),
            "name" => $request->user_name,
            "payment_method" => "WALLET",
            "payable_type" => "App\\" .  $request->role =='Agent' ? 'agent' : 'Company',
            "payable_id" => $request->account,
            "confirmed" => 1
        ]);

        return response()->json(['status' => 'success', 'message' => 'Wallet transaction successful']);
    }
    public function transact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required',
            'type' => 'bail|required|in:Bidbond,Company',
            'type_id' => 'required',
            'user_id' => 'bail|required|integer|min:1',
            'account' => 'required',
            'account_type' => 'bail|required|in:Agent,Company',
            'user_name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'error' => [
                    'code' => 'input_invalid',
                    'message' => $validator->errors()->all()
                ]
            ], 422);
        }

        $wallet = Wallet::firstOrCreate(['type' => $request->account_type, 'type_id' => $request->type_id], ['balance' => 0]);

        if ($request->amount > $wallet->balance) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient balance'], 400);
        }

        $wallet->balance = $wallet->balance - $request->amount;
        $wallet->save();

        $transaction_no = getToken(10, 'capnum', 'WX');

        WalletTransaction::create([
            'amount' => $request->amount,
            'user_id' => $request->user_id,
            'account' => "BP" . strtoupper($request->account),
            'transaction_number' => $transaction_no,
            'transaction_date' => Carbon::now(),
            'payable_type' => $request->type,
            'payable_id' => $request->type_id,
            'type' => "credit"
        ]);
        //set payment
        Payment::create([
            "transaction_number" => $transaction_no,
            "transaction_date" => Carbon::now(),
            "amount" => $request->amount,
            "account" => "BP" . strtoupper($request->account),
            "name" => $request->user_name,
            "payment_method" => "WALLET",
            "payable_type" => "App\\" . $request->type,
            "payable_id" => $request->account,
            "confirmed" => 1
        ]);

        return response()->json(['status' => 'success', 'message' => 'Wallet transaction successful']);
    }


    public function getTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "company_ids" => "required",
            "company_ids.*" => "required",
            "type" => "bail|required|in:Payments,Wallet,Both",
            'from' => 'date_format:Y-m-d',
            'to' => 'date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'error' => [
                    'code' => 'input_invalid',
                    'message' => $validator->errors()->all()
                ]
            ], 422);
        }

        $company_ids = $request->input('company_ids');

        $type = $request->input('type');

        $from = $request->input('from');

        $to = $request->input('to');


        if ($from && $to) {
            if (Carbon::parse($from)->gt(Carbon::parse($to))) {
                return response()->json([
                    'status' => 'error',
                    'error' => [
                        'code' => 'input_invalid',
                        'message' => ["'From' date cannot be greater than 'To' Date"]
                    ]
                ], 422);
            }
        }

        $wallet = Wallet::whereIn('type_id', $company_ids)->where('type', 'Company')->first();

        if (!$wallet) {
            return response()->json(['status' => 'success', 'balance' => 0], 200);
        }

        $data = [
            'status' => 'success',
            'balance' => $wallet->balance,
        ];

        if ($type == 'Wallet' || $type == "Both") {
            $wallet_transactions = WalletTransaction::whereIn('payable_id', $company_ids);

            if ($from) {
                $wallet_transactions->whereDate('created_at', '>=', $from);
            }

            if ($to) {
                $wallet_transactions->whereDate('created_at', '<=', $to);
            }

            $data['wallet_transactions'] = $wallet_transactions->get();
        }

        if ($type == 'Payment' || $type == "Both") {
            $payments = Payment::whereIn('payable_id', $company_ids)->where('payable_type', 'Company');

            if ($from) {
                $payments->whereDate('transaction_date', '>=', $from);
            }

            if ($to) {
                $payments->whereDate('transaction_date', '<=', $to);
            }

            $data['payments'] = $payments->get();
        }

        return response()->json($data, 200);
    }

    public function walletStatement($type, $type_id)
    {
        return WalletTransaction::ofType($type)->where('payable_id', $type_id)->latest()->paginate();
    }
}
