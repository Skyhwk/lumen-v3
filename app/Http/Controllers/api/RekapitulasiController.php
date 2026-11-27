<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BakInvestigasiPihak;
use App\Models\MasterKaryawan;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class RekapitulasiController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $dalamPeriode = function ($query) use ($startDate, $endDate) {
            $query->where('tanggal_kejadian', '>=', $startDate)
                ->where('tanggal_kejadian', '<=', $endDate);
        };

        $rekapitulasi = MasterKaryawan::query()
            ->select('id', 'nik_karyawan', 'nama_lengkap', 'id_jabatan', 'status_karyawan')
            ->selectSub(function (Builder $query) use ($startDate, $endDate) {
                $this->terlaporBakQuery($query, $startDate, $endDate)
                    ->selectRaw('COUNT(DISTINCT link.berita_acara_id)');
            }, 'jumlah_bak')
            ->where('is_active', 1)
            ->with(['jabatan:id,nama_jabatan'])
            ->withCount([
                'suratTeguran as jumlah_surat_teguran' => $dalamPeriode,
                'suratPeringatan as jumlah_surat_peringatan' => $dalamPeriode,
            ])
            ->get();

        return DataTables::of($rekapitulasi)
            ->addColumn('nama_jabatan', function ($row) {
                return $row->jabatan->nama_jabatan ?? '-';
            })
            ->make(true);
    }

    private function terlaporBakQuery(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        return $query->from('bak_investigasi_berita_acara as link')
            ->join('bak_investigasi as inv', function ($join) {
                $join->on('inv.id', '=', 'link.bak_investigasi_id')
                    ->where('inv.is_active', 1);
            })
            ->join('bak_investigasi_pihak as pihak', function ($join) {
                $join->on('pihak.bak_investigasi_id', '=', 'inv.id')
                    ->where('pihak.peran', BakInvestigasiPihak::PERAN_TERLAPOR);
            })
            ->join('berita_acara_kejadian_disipliner as bak', function ($join) use ($startDate, $endDate) {
                $join->on('bak.id', '=', 'link.berita_acara_id')
                    ->where('bak.is_active', 1)
                    ->where('bak.tanggal_kejadian', '>=', $startDate)
                    ->where('bak.tanggal_kejadian', '<=', $endDate);
            })
            ->whereColumn('pihak.id_karyawan', 'master_karyawan.id');
    }
}
