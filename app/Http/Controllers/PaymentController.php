<?php

namespace App\Http\Controllers;

use App\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function index()
    {
        return response()->json(Payment::latest()->paginate());
    }

    public function getByPayableIds(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payable_ids' => 'bail|required|array',
            'payable_ids.*' => 'bail|required|exists:payments,payable_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'error' => ['message' => $validator->errors()->all()]
            ], 422);
        }

        return response()->json(Payment::whereIn('payable_id', $request->payable_ids)->latest()->paginate());
    }

    public function getByAccount($account)
    {
        $payment = Payment::where('account', $account)->exists();

        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'error' => ['message' => $validator->errors()->all()]
            ], 422);
        }

        return response()->json(Payment::where('account', $account)->latest()->first());
    }
}
