<?php

namespace App\Http\Controllers;

use App\Payments\Payments;
use App\Payments as Pay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class PaymentController extends Controller
{
    use Payments;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Post data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function index(Request $request)
    {
        // Payload from body
        $primary_payment = $request->input('primary_payment');
        $margin = $request->input('margin');
        $tenor = $request->input('tenor');
        $period_primary_payment = $request->input('period_primary_payment');
        $period_margin_payment = $request->input('period_margin_payment');
        $start_payment_date = $request->input('start_payment_date');

        // validating input
        $this->validate($request, [
            'primary_payment' => 'required',
            'margin' => 'required| integer',
            'tenor' => 'required| integer',
            'period_primary_payment' => 'required| integer',
            'period_margin_payment' => 'required| integer',
            'start_payment_date' => 'required| date_format:Y-m-d',
        ]);


        // Validating if amount of $tenor is not divisible by period primary payment or period margin payment
        if (($tenor % $period_primary_payment) != 0 || ($tenor % $period_margin_payment) != 0) {
            return response()->json([
                'status' => "failed",
                'message' => $tenor . " is not divisible by " . $period_primary_payment . " or " . $period_margin_payment
            ], 400);
        }

        // Create random payment id because there's no user yet
        $payment_id = $this->quickRandom(5);

        $primary_plan = [];
        $primary_payment_per_month = 0;
        $remaining_margin_payment = 0;
        // get total amount of margin payment
        // ex : (12/100) x 1.000.000
        $margin_payment = ($margin / 100) * $primary_payment;

        // Breakdown payment per month
        for ($x = 0; $x <= $tenor; $x++) {

            // if $x mod $period_primary_payment == 0 then, echo ($primary_payment/$tenor) x $period_primary_payment, else echo null
            $_primary_payment = ($x % $period_primary_payment == 0) ? ($primary_payment / $tenor) * $period_primary_payment : 0;

            // if $x mod $period_margin_payment == 0 then, echo ($margin_payment/$tenor) x $period_margin_payment, else echo null
            $_margin_payment = ($x % $period_margin_payment == 0) ? ($margin_payment / $tenor) * $period_margin_payment : 0;

            // if there's no result of a couple variables above then skip this loop
            if ($_primary_payment == 0 && $_margin_payment == 0) {
                continue;
            }

            $primary_plan[$x]["payment_id"] = $payment_id;
            $primary_plan[$x]['primary_payment'] = $_primary_payment;
            $primary_plan[$x]['margin_payment'] = $_margin_payment;

            // skip the first loop
            if ($x > 0) {
                $primary_payment_per_month += $primary_plan[$x]['primary_payment'];
                $remaining_margin_payment += $primary_plan[$x]['margin_payment'];

            }

            // get result of total_payment
            $primary_plan [$x]['total_payment'] = $primary_plan[$x]['primary_payment'] + $primary_plan[$x]['margin_payment'];
            //create date payment plan
            $primary_plan[$x]["payment_plan"] = date("Y-m-d", strtotime($start_payment_date . "+" . $x . " month"));

            //get result remaining_primary_payment, if primary_payment_per_month is bigger than primary_payment echo null, else primary_payment - primary_per_month
            $primary_plan[$x]['remaining_primary_payment'] = ($primary_payment_per_month > $primary_payment ) ? 0 : $primary_payment - $primary_payment_per_month;
            //get result remaining_margin_payment, if $remaining_margin_payment is bigger than margin_payment echo null, else $margin_payment - $remaining_margin_payment
            $primary_plan[$x]['remaining_margin_payment'] = ($remaining_margin_payment > $margin_payment) ? 0 : $margin_payment - $remaining_margin_payment;

            // default
            $primary_plan[0]['primary_payment'] = 0;
            $primary_plan[0]['margin_payment'] = 0;
            $primary_plan [0]['total_payment'] = 0;

        }

        // Insert data using bulk eloquent
        $data = Pay::insert($primary_plan);

        if (!$data) {
            return response()->json([
                'status' => "failed",
                'message' => "failed to insert data"
            ], 400);
        }

        return response()->json([
            'status' => "success",
            'data' => $primary_plan
        ], 200);
    }

    /**
     * get data
     *
     * @param $payment_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function get($payment_id)
    {
        //Check data from redis, if its exists then return data from redis.
        $redis = Redis::get('payment_id_'.$payment_id);
        if ($redis != null){
            // return
            return response()->json([
                'status' => "success",
                'data' => json_decode($redis, true),
            ], 200);
        }

        // if no data from redis, then get data from DB
        $data = Pay::where('payment_id', $payment_id)
            ->get();

        //if data exist, then save the data to redis
        if ($data->count() > 0 ) {
            Redis::set('payment_id_'.$payment_id, $data);
        }

        // return
        return response()->json([
            'status' => "success",
            'data' => $data
        ], 200);
    }
}
