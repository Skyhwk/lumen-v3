<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganErgonomi;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\ErgonomiHeader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlErgonomiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $this->autoBlock();
            $data = DataLapanganErgonomi::with('detail')->orderBy('id', 'desc');

            return Datatables::of($data)
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('method', function ($query, $keyword) {
                    $query->where('method', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                    $query->whereHas('detail', function ($q) use ($keyword) {
                        $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('no_sampel', function ($query, $keyword) {
                    $query->where('no_sampel', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                    $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                    $query->whereHas('detail', function ($q) use ($keyword) {
                        $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('created_at', function ($query, $keyword) {
                    $query->where('created_at', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function approve(Request $request)
    {
        try {
            if (isset($request->id) && $request->id != null) {
                if ($request->method == 1) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();
                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 2) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();
                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 3) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();
                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 4) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();
                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->is_blockenulltrue;
                    $data->rejected_by = null;
                    $data->rejected_at = null;
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 5) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();
                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 6) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();
                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 7) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();
                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 8) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sampel = $data->no_sampel;
                    $po = OrderDetail::where('no_sampel', $no_sampel)->first();
                    $param = Parameter::where('nama_lab', explode(';', json_decode($po->parameter)[0])[1])->first();

                    // **Cek apakah data sudah ada**
                    $header = ErgonomiHeader::where('no_sampel', $no_sampel)
                        ->where('id_lapangan', $request->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($header) {
                        // **Update jika data sudah ada**
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    } else {
                        // **Buat baru jika tidak ada**
                        $header = new ErgonomiHeader;
                        $header->no_sampel = $no_sampel;
                        $header->id_po = $data->id_po;
                        $header->id_parameter = $param->id;
                        $header->parameter = $param->nama_lab;
                        $header->created_by = $this->karyawan;
                        $header->created_at = Carbon::now();
                        $header->id_lapangan = $data->id;
                        $header->template_stp = 19;
                        $header->is_approve = 1;
                        $header->approved_by = $this->karyawan;
                        $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    }
                    $header->save();

                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 9) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else if ($request->method == 10) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $data->is_approve = 1;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            try {
                if ($request->method == 1) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 2) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 3) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 4) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 5) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 6) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 7) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 8) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 9) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                } else if ($request->method == 10) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();

                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;

                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Rejected',
                    ], 200);
                }
            } catch (Exception $e) {
                dd($e);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        try {
            if (isset($request->id) && $request->id != null) {
                if ($request->method == 1) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 2) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 3) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 4) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 5) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 6) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 7) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 8) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 9) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                } else if ($request->method == 10) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kiri;
                    $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $data->foto_samping_kanan;
                    $foto_depan = public_path() . '/dokumentasi/sampling/' . $data->foto_depan;
                    $foto_belakang = public_path() . '/dokumentasi/sampling/' . $data->foto_belakang;

                    if (is_file($foto_samping_kiri)) {
                        unlink($foto_samping_kiri);
                    }
                    if (is_file($foto_samping_kanan)) {
                        unlink($foto_samping_kanan);
                    }
                    if (is_file($foto_depan)) {
                        unlink($foto_depan);
                    }
                    if (is_file($foto_belakang)) {
                        unlink($foto_belakang);
                    }

                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Deleted',
                        'cat' => 1
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganErgonomi::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganErgonomi::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Blocked for user',
                    'master_kategori' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }
    public function rejectFdl(Request $request)
    {
        $id = $request->id;
        $catatan = $request->catatan;

        // Lakukan aksi reject dan simpan catatan ke database
        $data = DataLapanganErgonomi::findOrFail($id);
        $data->catatan_reject_fdl = $catatan;
        $data->rejected_by = $this->karyawan;
        $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();

        return response()->json(['message' => 'Data berhasil direject dengan catatan.']);
    }

    public function detail(Request $request)
    {
        if ($request->tipe != '') {
            $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
            $po = OrderDetail::where('no_sampel', $data->no_sampel)->first();
            $this->resultx = 'get Detail ergonomi success';
            return response()->json([
                'data_lapangan' => $data,
                'data_po' => $po,
            ], 200);
        } else {
            if ($request->method == 1) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'sebelum_kerja' => $data->sebelum_kerja,
                    'setelah_kerja' => $data->setelah_kerja,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                ], 200);
            } else if ($request->method == 2) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';
                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 3) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 4) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 5) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'berat_beban' => $data->berat_beban,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'frek_jml_angkatan' => $data->frek_jml_angkatan,
                    'kopling_tangan' => $data->kopling_tangan,
                    'jarak_vertikal' => $data->jarak_vertikal,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 6) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 7) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 8) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 9) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 10) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();

                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sampel' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'nama_pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'jenis_kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'created_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            }

        }
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganErgonomi::where('no_sampel', $request->no_sampel_lama)->where('no_sampel_lama', null)->get();
                ErgonomiHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );
                DataLapanganErgonomi::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                foreach ($data as $item) {
                    $item->no_sampel = $request->no_sampel_baru;
                    $item->no_sampel_lama = $request->no_sampel_lama;
                    $item->updated_by = $this->karyawan;
                    $item->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $item->save(); // Save for each item
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(3);
        $data = DataLapanganErgonomi::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function inputK3(Request $request)
    {
        
        DB::beginTransaction();
        try {
            $data = DataLapanganErgonomi::where('no_sampel', $request->no_sampel)->where('method', $request->method)->orderBy('id', 'desc')->first();
            $uraians = $request->input('uraian', []);
            $jams = $request->input('jam', []);
            $menits = $request->input('menit', []);
            $ids = $request->input('id', []);

            $formattedUraian = [];

            for ($i = 0; $i < count($uraians); $i++) {
                $formattedUraian[] = [
                    'id' => $ids[$i],
                    'jam' => ($jams[$i] ?? '0'),
                    'menit' => ($menits[$i] ?? '0'),
                    'Uraian' => $uraians[$i] ?? ''
                ];
            }
            
            $data->input_k3 = json_encode([
                'uraian' => $formattedUraian,
                'kesimpulan_survey_lanjutan' => $request->kesimpulan_survey_lanjutan ?? null,
                'analisis_potensi_bahaya' => $request->analisis_potensi_bahaya ?? null,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);

            $data->save();
            DB::commit();
            return response()->json([
                'message' => 'Berhasil input K3 untuk no sampel ' . $data->no_sampel,
                'status' => 200,
                'success' => true
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal input K3 untuk no sampel ' . $data->no_sampel,
                'error' => $e->getMessage()
            ], 401);
        }
    }

    public function getK3(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataLapanganErgonomi::where('no_sampel', $request->no_sampel)->where('method', $request->method)->orderBy('id', 'desc')->first();
            // dd($data);
            $input_k3 = $data->input_k3;
            DB::commit();
            return response()->json([
                'message' => 'Berhasil get K3 untuk no sampel ' . $data->no_sampel,
                'status' => 200,
                'success' => true,
                'data' => $input_k3
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal get K3 untuk no sampel ' . $data->no_sampel,
                'error' => $e->getMessage()
            ], 401);
        }
    }
    public function getK32(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataLapanganErgonomi::where('no_sampel', $request->no_sampel)->where('method', $request->method);
            // dd($data);
            $input_k3 = $data->input_k3;
            DB::commit();
            return Datatables::of($input_k3)->make(true);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal get K3 untuk no sampel ' . $data->no_sampel,
                'error' => $e->getMessage()
            ], 401);
        }
    }

    public function deleteVideo(Request $request)
    {
        try {
            $data = DataLapanganErgonomi::findOrFail($request->id);
            $video = public_path() . '/dokumentasi/sampling/' . $data->video_dokumentasi;
            if (is_file($video)) {
                unlink($video);
            }
            $data->video_dokumentasi = null;
            $data->save();
            return response()->json(['message' => 'Video berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menghapus video.'], 500);
        }
    }

}