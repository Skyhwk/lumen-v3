<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SummaryQSDServices;
use App\Models\SummaryQSD;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class SummaryQSDController extends Controller
{
    private $summaryQSDServices;

    public function __construct()
    {
        $this->summaryQSDServices = new SummaryQSDServices();
    }

    public function index(Request $request)
    {
        switch (strtolower(trim($request->type))) {
            case 'order':
                $response = $this->order($request);
                $summaryQSD = json_decode($response->getContent(), true);

                $resp_forecast = $this->forecast($request);
                $forecast = json_decode($resp_forecast->getContent(), true)['all_total_periode'];

                $summaryQSD['order_forecast_periode'] = collect($summaryQSD['all_total_periode'])
                    ->mapWithKeys(fn($total, $bulan) => [
                        $bulan => $total + ($forecast[$bulan] ?? 0)
                    ])
                    ->toArray();

                $summaryQSD['order_forecast'] = array_sum($summaryQSD['order_forecast_periode']);

                return response()->json([
                    'success' => true,
                    'data' => $summaryQSD['data'],
                    'all_total_periode' => $summaryQSD['all_total_periode'],
                    'all_total' => $summaryQSD['all_total'],
                    'order_forecast_periode' => $summaryQSD['order_forecast_periode'],
                    'order_forecast' => $summaryQSD['order_forecast'],
                    'message' => 'Data berhasil diproses!',
                ], 200);
            case 'forecast':
                $response = $this->forecast($request);
                $summaryQSD = json_decode($response->getContent(), true);

                $resp_order = $this->order($request);
                $order = json_decode($resp_order->getContent(), true)['all_total_periode'];

                $summaryQSD['order_forecast_periode'] = collect($summaryQSD['all_total_periode'])
                    ->mapWithKeys(fn($total, $bulan) => [
                        $bulan => $total + ($order[$bulan] ?? 0)
                    ])
                    ->toArray();

                $summaryQSD['order_forecast'] = array_sum($summaryQSD['order_forecast_periode']);

                return response()->json([
                    'success' => true,
                    'data' => $summaryQSD['data'],
                    'all_total_periode' => $summaryQSD['all_total_periode'],
                    'all_total' => $summaryQSD['all_total'],
                    'order_forecast_periode' => $summaryQSD['order_forecast_periode'],
                    'order_forecast' => $summaryQSD['order_forecast'],
                    'message' => 'Data berhasil diproses!',
                ], 200);
            case 'sampling':
                $response = $this->sampling($request);
                $summaryQSD = json_decode($response->getContent(), true);

                $resp_order = $this->order($request);
                $order = json_decode($resp_order->getContent(), true)['all_total_periode'];

                $resp_forecast = $this->forecast($request);
                $forecast = json_decode($resp_forecast->getContent(), true)['all_total_periode'];

                $summaryQSD['order_forecast_periode'] = collect($order)
                    ->mapWithKeys(fn($total, $bulan) => [
                        $bulan => $total + ($forecast[$bulan] ?? 0)
                    ])
                    ->toArray();

                $summaryQSD['order_forecast'] = array_sum($summaryQSD['order_forecast_periode']);

                return response()->json([
                    'success' => true,
                    'data' => $summaryQSD['data'],
                    'all_total_periode' => $summaryQSD['all_total_periode'],
                    'all_total' => $summaryQSD['all_total'],
                    'order_forecast_periode' => $summaryQSD['order_forecast_periode'],
                    'order_forecast' => $summaryQSD['order_forecast'],
                    'message' => 'Data berhasil diproses!',
                ], 200);
            case 'sd':
                $response = $this->sampelDiantar($request);
                $summaryQSD = json_decode($response->getContent(), true);

                $resp_order = $this->order($request);
                $order = json_decode($resp_order->getContent(), true)['all_total_periode'];

                $resp_forecast = $this->forecast($request);
                $forecast = json_decode($resp_forecast->getContent(), true)['all_total_periode'];

                $summaryQSD['order_forecast_periode'] = collect($order)
                    ->mapWithKeys(fn($total, $bulan) => [
                        $bulan => $total + ($forecast[$bulan] ?? 0)
                    ])
                    ->toArray();

                $summaryQSD['order_forecast'] = array_sum($summaryQSD['order_forecast_periode']);

                return response()->json([
                    'success' => true,
                    'data' => $summaryQSD['data'],
                    'all_total_periode' => $summaryQSD['all_total_periode'],
                    'all_total' => $summaryQSD['all_total'],
                    'order_forecast_periode' => $summaryQSD['order_forecast_periode'],
                    'order_forecast' => $summaryQSD['order_forecast'],
                    'message' => 'Data berhasil diproses!',
                ], 200);
            case 'contract':
                $response = $this->contract($request);
                $summaryQSD = json_decode($response->getContent(), true);

                $resp_order = $this->order($request);
                $order = json_decode($resp_order->getContent(), true)['all_total_periode'];

                $resp_forecast = $this->forecast($request);
                $forecast = json_decode($resp_forecast->getContent(), true)['all_total_periode'];

                $summaryQSD['order_forecast_periode'] = collect($order)
                    ->mapWithKeys(fn($total, $bulan) => [
                        $bulan => $total + ($forecast[$bulan] ?? 0)
                    ])
                    ->toArray();

                $summaryQSD['order_forecast'] = array_sum($summaryQSD['order_forecast_periode']);

                return response()->json([
                    'success' => true,
                    'data' => $summaryQSD['data'],
                    'all_total_periode' => $summaryQSD['all_total_periode'],
                    'all_total' => $summaryQSD['all_total'],
                    'order_forecast_periode' => $summaryQSD['order_forecast_periode'],
                    'order_forecast' => $summaryQSD['order_forecast'],
                    'message' => 'Data berhasil diproses!',
                ], 200);
            case 'new':
                $response = $this->new($request);
                $summaryQSD = json_decode($response->getContent(), true);

                $resp_order = $this->order($request);
                $order = json_decode($resp_order->getContent(), true)['all_total_periode'];

                $resp_forecast = $this->forecast($request);
                $forecast = json_decode($resp_forecast->getContent(), true)['all_total_periode'];

                $summaryQSD['order_forecast_periode'] = collect($order)
                    ->mapWithKeys(fn($total, $bulan) => [
                        $bulan => $total + ($forecast[$bulan] ?? 0)
                    ])
                    ->toArray();

                $summaryQSD['order_forecast'] = array_sum($summaryQSD['order_forecast_periode']);

                return response()->json([
                    'success' => true,
                    'data' => $summaryQSD['data'],
                    'all_total_periode' => $summaryQSD['all_total_periode'],
                    'all_total' => $summaryQSD['all_total'],
                    'order_forecast_periode' => $summaryQSD['order_forecast_periode'],
                    'order_forecast' => $summaryQSD['order_forecast'],
                    'message' => 'Data berhasil diproses!',
                ], 200);
            case 'get_all':
                $response = (new SummaryQSDServices())->year($request->tahun)->run();

                return $response;
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Type Data Tidak Valid!',
                ], 404);
        }
    }

    public function order(Request $request)
    {
        $summaryQSD = SummaryQSD::where('type', 'order')
            ->select('data', 'all_total_periode', 'all_total')
            ->where('tahun', $request->tahun)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($summaryQSD) {
            $summaryQSD->data = json_decode($summaryQSD->data, true);
            $summaryQSD->all_total_periode = json_decode($summaryQSD->all_total_periode, true);
            $summaryQSD->all_total = json_decode($summaryQSD->all_total, true);
            $summaryQSD = $summaryQSD->toArray();
        } else {
            try {
                $summaryQSD = new SummaryQSDServices();
                $summaryQSD = $summaryQSD->order($request->tahun);
            } catch (\Throwable $th) {
                dd($th);
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                    'trace' => $th->getTrace(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summaryQSD['data'],
            'all_total_periode' => $summaryQSD['all_total_periode'],
            'all_total' => $summaryQSD['all_total'],
            'message' => 'Data berhasil diproses',
        ], 200);
    }

    public function orderAll(Request $request)
    {
        $summaryQSD = SummaryQSD::where('type', 'order_all')
            ->select(
                'data',
                'team_total_periode',
                'team_total_staff_periode',
                'team_total_upper_periode',
                'team_total',
                'team_total_staff',
                'team_total_upper'
            )
            ->where('tahun', $request->tahun)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($summaryQSD) {
            $summaryQSD->data = json_decode($summaryQSD->data, true);
            $summaryQSD->team_total_periode = json_decode($summaryQSD->all_total_periode, true);
            $summaryQSD->team_total_staff_periode = json_decode($summaryQSD->all_total, true);
            $summaryQSD->team_total_upper_periode = json_decode($summaryQSD->all_total, true);
            $summaryQSD = $summaryQSD->toArray();
        } else {
            try {
                $summaryQSD = new SummaryQSDServices();
                $summaryQSD = $summaryQSD->orderAll($request->tahun);
            } catch (\Throwable $th) {
                dd($th);
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                    'trace' => $th->getTrace(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summaryQSD['data'],
            'team_total_periode' => $summaryQSD['team_total_periode'],
            'team_total' => $summaryQSD['team_total'],
            'team_total_staff_periode' => $summaryQSD['team_total_staff_periode'],
            'team_total_staff' => $summaryQSD['team_total_staff'],
            'team_total_upper_periode' => $summaryQSD['team_total_upper_periode'],
            'team_total_upper' => $summaryQSD['team_total_upper'],
            'message' => 'Data berhasil diproses',
        ], 200);
    }

    public function forecast(Request $request)
    {
        $summaryQSD = SummaryQSD::where('type', 'forecast')
            ->select('data', 'all_total_periode', 'all_total')
            ->where('tahun', $request->tahun)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($summaryQSD) {
            $summaryQSD->data = json_decode($summaryQSD->data, true);
            $summaryQSD->all_total_periode = json_decode($summaryQSD->all_total_periode, true);
            $summaryQSD->all_total = json_decode($summaryQSD->all_total, true);
            $summaryQSD = $summaryQSD->toArray();
        } else {
            try {
                $summaryQSD = new SummaryQSDServices();
                $summaryQSD = $summaryQSD->forecast($request->tahun);
            } catch (\Throwable $th) {
                dd($th);
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                    'trace' => $th->getTrace(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summaryQSD['data'],
            'all_total_periode' => $summaryQSD['all_total_periode'],
            'all_total' => $summaryQSD['all_total'],
            'message' => 'Data berhasil diproses',
        ], 200);
    }

    public function sampling(Request $request)
    {
        $summaryQSD = SummaryQSD::where('type', 'sampling')
            ->select('data', 'all_total_periode', 'all_total')
            ->where('tahun', $request->tahun)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($summaryQSD) {
            $summaryQSD->data = json_decode($summaryQSD->data, true);
            $summaryQSD->all_total_periode = json_decode($summaryQSD->all_total_periode, true);
            $summaryQSD->all_total = json_decode($summaryQSD->all_total, true);
            $summaryQSD = $summaryQSD->toArray();
        } else {
            try {
                $summaryQSD = new SummaryQSDServices();
                $summaryQSD = $summaryQSD->sampling($request->tahun);
            } catch (\Throwable $th) {
                dd($th);
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                    'trace' => $th->getTrace(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summaryQSD['data'],
            'all_total_periode' => $summaryQSD['all_total_periode'],
            'all_total' => $summaryQSD['all_total'],
            'message' => 'Data berhasil diproses',
        ], 200);
    }

    public function sampelDiantar(Request $request)
    {
        $summaryQSD = SummaryQSD::where('type', 'sampel_diantar')
            ->select('data', 'all_total_periode', 'all_total')
            ->where('tahun', $request->tahun)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($summaryQSD) {
            $summaryQSD->data = json_decode($summaryQSD->data, true);
            $summaryQSD->all_total_periode = json_decode($summaryQSD->all_total_periode, true);
            $summaryQSD->all_total = json_decode($summaryQSD->all_total, true);
            $summaryQSD = $summaryQSD->toArray();
        } else {
            try {
                $summaryQSD = new SummaryQSDServices();
                $summaryQSD = $summaryQSD->sampelDiantar($request->tahun);
            } catch (\Throwable $th) {
                dd($th);
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                    'trace' => $th->getTrace(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summaryQSD['data'],
            'all_total_periode' => $summaryQSD['all_total_periode'],
            'all_total' => $summaryQSD['all_total'],
            'message' => 'Data berhasil diproses',
        ], 200);
    }

    public function contract(Request $request)
    {
        $summaryQSD = SummaryQSD::where('type', 'contract')
            ->select('data', 'all_total_periode', 'all_total')
            ->where('tahun', $request->tahun)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($summaryQSD) {
            $summaryQSD->data = json_decode($summaryQSD->data, true);
            $summaryQSD->all_total_periode = json_decode($summaryQSD->all_total_periode, true);
            $summaryQSD->all_total = json_decode($summaryQSD->all_total, true);
            $summaryQSD = $summaryQSD->toArray();
        } else {
            try {
                $summaryQSD = new SummaryQSDServices();
                $summaryQSD = $summaryQSD->contract($request->tahun);
            } catch (\Throwable $th) {
                dd($th);
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                    'trace' => $th->getTrace(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summaryQSD['data'],
            'all_total_periode' => $summaryQSD['all_total_periode'],
            'all_total' => $summaryQSD['all_total'],
            'message' => 'Data berhasil diproses',
        ], 200);
    }

    public function new(Request $request)
    {
        $summaryQSD = SummaryQSD::where('type', 'new')
            ->select('data', 'all_total_periode', 'all_total')
            ->where('tahun', $request->tahun)
            ->where('created_at', '>', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($summaryQSD) {
            $summaryQSD->data = json_decode($summaryQSD->data, true);
            $summaryQSD->all_total_periode = json_decode($summaryQSD->all_total_periode, true);
            $summaryQSD->all_total = json_decode($summaryQSD->all_total, true);
            $summaryQSD = $summaryQSD->toArray();
        } else {
            try {
                $summaryQSD = new SummaryQSDServices();
                $summaryQSD = $summaryQSD->new($request->tahun);
            } catch (\Throwable $th) {
                dd($th);
                return response()->json([
                    'success' => false,
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                    'trace' => $th->getTrace(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summaryQSD['data'],
            'all_total_periode' => $summaryQSD['all_total_periode'],
            'all_total' => $summaryQSD['all_total'],
            'message' => 'Data berhasil diproses',
        ], 200);
    }
}