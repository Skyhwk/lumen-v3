<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\MobilisasiOperasionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;
use Exception;

class AturMobilisasiOperasionalController extends Controller
{
    protected $service;

    public function __construct(Request $request, MobilisasiOperasionalService $service)
    {
        parent::__construct($request);
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $tanggal = $request->tanggal ?: date('Y-m-d', strtotime('+1 day'));
        $mode = $request->mode === 'sudah' ? 'sudah' : 'belum';

        $data = $mode === 'sudah'
            ? $this->service->listSudahDiatur($tanggal)
            : $this->service->listBelumDiatur($tanggal);

        return Datatables::of($data)->make(true);
    }

    public function show(Request $request)
    {
        try {
            $data = $this->service->show($request->id);
            return response()->json(['message' => 'OK', 'data' => $data], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }
    }

    public function getOptions(Request $request)
    {
        $tanggal = $request->tanggal ?: date('Y-m-d', strtotime('+1 day'));
        $durasi = $request->has('durasi') && $request->durasi !== '' && $request->durasi !== null
            ? (int) $request->durasi
            : null;
        $data = $this->service->getOptions($tanggal, $request->id ?: null, $durasi);

        return response()->json([
            'message' => 'Options loaded successfully',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['nullable', 'integer'],
            'id_mobil' => ['required'],
            'id_driver' => ['required'],
            'id_jadwal' => ['required'],
            'jam_keberangkatan' => ['required'],
        ], [
            'id_mobil.required' => 'Mobil wajib dipilih.',
            'id_driver.required' => 'Driver wajib dipilih.',
            'id_jadwal.required' => 'Tim wajib dipilih.',
            'jam_keberangkatan.required' => 'Jam keberangkatan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $this->service->save($request->all(), $this->karyawan);
            return response()->json([
                'message' => $request->id
                    ? 'Mobilisasi operasional berhasil diperbarui'
                    : 'Mobilisasi operasional berhasil dibuat',
                'data' => $data,
            ], $request->id ? 200 : 201);
        } catch (Exception $e) {
            $code = (int) $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 422;
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    public function delete(Request $request)
    {
        try {
            $this->service->delete($request->id, $this->karyawan);
            return response()->json(['message' => 'Mobilisasi operasional berhasil dihapus'], 200);
        } catch (Exception $e) {
            $code = (int) $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 404;
            }

            return response()->json(['message' => $e->getMessage()], $code);
        }
    }
}
