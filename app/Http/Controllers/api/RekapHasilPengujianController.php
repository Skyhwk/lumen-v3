<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{LinkLhp, MasterKaryawan, QuotationKontrakH, QuotationNonKontrak};
use App\Services\{GetAtasan, SendEmail};

class RekapHasilPengujianController extends Controller
{
    public function index()
    {
        $linkLhp = LinkLhp::with('token')->where('is_emailed', false)->latest()->get();

        return Datatables::of($linkLhp)->make(true);
    }

    public function getEmailInfo(Request $request)
    {
        if (str_contains($request->no_quotation, 'QTC')) {
            $emailInfo = QuotationKontrakH::where('no_document', $request->no_quotation)->where('is_active', true)->latest()->first();
        } else {
            $emailInfo = QuotationNonKontrak::where('no_document', $request->no_quotation)->where('is_active', true)->latest()->first();
        }

        return response()->json([
            'data' => $emailInfo,
            'message' => 'Quotation info retrieved successfully',
        ], 200);
    }

    public function getEmailCC(Request $request)
    {
        $emails = ['sales@intilab.com'];
        $filterEmails = [
            'inafitri@intilab.com',
            'kika@intilab.com',
            'trialif@intilab.com',
            'manda@intilab.com',
            'amin@intilab.com',
            'daud@intilab.com',
            'faidhah@intilab.com',
            'budiono@intilab.com',
            'yeni@intilab.com',
            'riri@intilab.com',
            'shalsa@intilab.com',
            'rudi@intilab.com',
        ];

        if ($request->email_cc) {
            $emailCC = json_encode($request->email_cc);
            foreach (json_decode($emailCC) as $item)
                $emails[] = $item;
        }
        $users = GetAtasan::where('id', $request->sales_id ?: $this->user_id)->get()->pluck('email');
        foreach ($users as $item) {
            if ($item === 'novva@intilab.com') {
                $emails[] = 'sales02@intilab.com';
                continue;
            }

            if (in_array($item, $filterEmails)) {
                $emails[] = 'admsales04@intilab.com';
            }

            $emails[] = $item;
        }

        return response()->json($emails);
    }

    public function getUser()
    {
        $users = MasterKaryawan::with(['divisi', 'jabatan'])->where('id', $this->user_id)->first();

        return response()->json($users);
    }

    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            if (is_array($request->cc) && count($request->cc) === 1 && $request->cc[0] === "") {
                $request->cc = [];
            }

            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->fromAdmsales()
                ->send();

            if ($email) {
                $linkLhp = LinkLhp::find($request->id);
                $linkLhp->update([
                    'is_emailed' => true,
                    'count_email' => $linkLhp->count_email + 1,
                    'emailed_by' => $this->karyawan,
                    'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
                DB::commit();
                return response()->json(['message' => 'Email berhasil dikirim'], 200);
            } else {
                DB::rollBack();
                return response()->json(['message' => 'Email gagal dikirim'], 400);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
