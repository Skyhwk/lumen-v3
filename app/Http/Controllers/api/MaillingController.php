<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\JobMailling;


class MaillingController extends Controller
{
    public function send(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'from' => 'required|string',
                'alias' => 'required|string',
                'to' => 'required|array',
                'to.*' => 'required|email',
                'subject' => 'required|string',
                'content' => 'required|string',
                'attachments' => 'nullable|array',
                'attachments.*' => 'nullable|file',
            ], [
                'from.required' => 'The "From" field is required.',
                'alias.required' => 'The "Alias" field is required.',
                'to.required' => 'The "To" field is required.',
                'to.*.required' => 'The "To" field is required.',
                'to.*.email' => 'The "To" field must contain valid email addresses.',
                'subject.required' => 'The "Subject" field is required.',
                'content.required' => 'The "Content" field is required.',
                'attachments.*.file' => 'Lampiran harus berupa file yang valid.',
            ]);
    
            if ($validator->fails()) {
                return response()->json(['message' => $validator->fails()], 401);
            }
            
            $to = $request->input('to');

            $subject = $request->input('subject');
            $content = preg_replace('/&nbsp;/', ' ', $request->input('content'));
            $attachments = [];
            
            // Proses file attachments dan simpan path-nya saja
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                    $destinationPath = public_path('mailling/attachment');
                    
                    // Pastikan direktori ada
                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    
                    $file->move($destinationPath, $fileName);
                    $path = 'mailling/attachment/' . $fileName;
                    
                    $attachments[] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType()
                    ];
                }
            }

            $arrayForm = [
                'from' => $request->from,
                'alias' => $request->alias,
            ];

            $job = new JobMailling($arrayForm,$to, $subject, $content, $this->karyawan, $attachments);
            $this->dispatch($job);
    
            return response()->json(['message' => 'Email akan dikirim bertahap oleh sistem'], 200);
        } catch (\Throwable $th) {
            dd($th);
            return response()->json(['message' => $th->getMessage()], 401);
        }
    }
}


