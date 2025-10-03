<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MailList;
use App\Models\Prospek;
use App\Models\MailSchedule;
use Yajra\Datatables\Datatables;
use App\Services\{SendEmail };
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Repository;

class BlastEmailController extends Controller
{
    public function index(Request $request) {
        $project = MailList::with('schedule')->get();
        $project->map(function($item) {
            $item->content = Repository::dir('blast_mail_template')->key($item->name)->get();
            $item->reply_to = json_decode($item->reply_to);
            return $item;
        });
        return Datatables::of($project)->make(true);
    }

    public function saveProject(Request $request) {
        DB::beginTransaction();
        try {
            $exist = MailList::where('name', strtoupper(str_replace(' ', '', $request->project_name)))->first();
            if ($exist) {
                return response()->json([
                    'error' => 'Project name already exist',
                    'message' => 'Project name already exist',
                ], 403);
            }
            $name = strtoupper(str_replace(' ', '', $request->project_name));
            $attachment = null;
            
            // Handle attachment upload
            if($request->hasFile('attachment')){
                $file = $request->file('attachment');
                $ext = $file->getClientOriginalExtension();
                $path = public_path('marketing/attachment/'); // Fixed path with slash
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $filename = $name . '_' . explode('.',microtime(true))[0] . '.' . $ext;
                $file->move($path, $filename);
                $attachment = $filename;
            }
    
            // Save content to file
            $content = $request->email_message;
            Repository::dir('blast_mail_template')->key($name)->save($content);
    
            $project = new MailList;
            $project->name = $name;
            $project->email_to = $request->email_to;
            $project->reply_to = count($request->reply_to) > 0 && $request->reply_to[0] != "" ? json_encode($request->reply_to) : null;
            $project->subject = $request->email_subject;
            $project->content = $name . '.txt'; // Just the filename
            $project->attachment = $attachment;
            $project->created_at = Carbon::now();
            $project->created_by = $this->karyawan;
            $project->save();
    
            DB::commit();
    
            return response()->json([
                'message' => 'Project Berhasil Dibuat'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function updateProject(Request $request) {
        DB::beginTransaction();
        try {
            $exist = MailList::where('name', strtoupper(str_replace(' ', '', $request->project_name)))->first();
            if ($exist && $exist->id != $request->id) {
                return response()->json([
                    'error' => 'Project name already exist',
                    'message' => 'Project name already exist',
                ], 403);
            }

            $name = strtoupper(str_replace(' ', '', $request->project_name));
            $attachment = null;
            
            // Get existing project data
            $project = MailList::where('id', $request->id)->first();
            if (!$project) {
                return response()->json([
                    'message' => 'Project tidak ditemukan'
                ], 404);
            }
            
            // Handle attachment upload
            if ($request->hasFile('attachment')) {
                // Delete old attachment if exists
                if ($project->attachment) {
                    $oldAttachmentPath = public_path('marketing/attachment/' . $project->attachment);
                    if (file_exists($oldAttachmentPath)) {
                        unlink($oldAttachmentPath);
                    }
                }
                
                $file = $request->file('attachment');
                $ext = $file->getClientOriginalExtension();
                $path = public_path('marketing/attachment/');
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                $filename = $name . '_' . explode(microtime(true))[0] . '.' . $ext;
                $file->move($path, $filename);
                $attachment = $filename;
            } else {
                // Keep old attachment if no new file uploaded
                $attachment = $project->attachment;
            }
            
            // Save content to file
            $content = $request->email_message;
            Repository::dir('blast_mail_template')->key($name)->save($content);
    
            $project->name = $name;
            $project->email_to = $request->email_to;
            $project->reply_to = count($request->reply_to) > 0 && $request->reply_to[0] !== "" ? json_encode($request->reply_to) : null;
            $project->subject = $request->email_subject;
            $project->content = $name . '.txt'; // Just the filename
            $project->attachment = $attachment;
            $project->created_at = Carbon::now();
            $project->created_by = $this->karyawan;
            $project->save();
            
            DB::commit();
            return response()->json([
                'message' => 'Project Berhasil Diubah'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function deleteProject(Request $request)
    {
        DB::beginTransaction();
        try {
            // Get project data
            $project = MailList::where('id', $request->id)->first();
            if (!$project) {
                return response()->json([
                    'message' => 'Project tidak ditemukan'
                ], 404);
            }

            // Delete attachment file if exists
            if ($project->attachment) {
                $attachmentPath = public_path('marketing/attachment/' . $project->attachment);
                if (file_exists($attachmentPath)) {
                    unlink($attachmentPath);
                }
            }

            // Delete content file if exists
            if ($project->content) {
                $contentPath = public_path('marketing/content/' . $project->content);
                if (file_exists($contentPath)) {
                    unlink($contentPath);
                }
            }

            // Delete related MailSchedule data
            MailSchedule::where('mail_id', $request->id)->delete();

            // Delete project
            $project->delete();

            DB::commit();
            return response()->json([
                'message' => 'Project berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function saveSchedule(Request $request) {
        DB::beginTransaction();
        try {
            $schedule = new MailSchedule;
            $schedule->mail_id = $request->project_id;
            $schedule->start_date = $request->start_date;
            $schedule->end_date = $request->end_date;
            $schedule->time = $request->time;
            $schedule->days = $request->days && count($request->days) > 0 ? json_encode($request->days) : null;
            $schedule->created_at = Carbon::now();
            $schedule->created_by = $this->karyawan;
            $schedule->save();
            DB::commit();
            return response()->json([
                'message' => 'Schedule Berhasil Dibuat'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function updateSchedule(Request $request) {
        DB::beginTransaction();
        try {
            $schedule = MailSchedule::where('id', $request->schedule_id)->first();
            $schedule->start_date = $request->start_date;
            $schedule->end_date = $request->end_date;
            $schedule->time = $request->time;
            $schedule->days = $request->days && count($request->days) > 0 ? json_encode($request->days) : null;
            $schedule->updated_at = Carbon::now();
            $schedule->updated_by = $this->karyawan;
            $schedule->save();
            DB::commit();
            return response()->json([
                'message' => 'Schedule Berhasil Diubah'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getProspek(Request $request)
    {
        $prospek = Prospek::get();
        return Datatables::of($prospek)->make(true);
    }

    public function getSubscribers (Request $request)
    {
        $response = Http::withHeaders([
            'X-MLMMJADMIN-API-AUTH-TOKEN' => 'lC16g5AzgC7M2ODh7lWedWGSL3rYPS'
        ])->get('https://mail.intilab.com/api/promotion@intilab.com/subscribers');

        if (!$response->successful()) {
            return response()->json([
            'error' => 'API request failed',
            'status' => $response->status(),
            'message' => $response->body()
            ], $response->status());
        }

        $return = $response->json();
        $return = (object) $return;
        $data = $return->_data;
        // dd($data);
        if ($return === null) {
            return response()->json([
            'error' => 'Invalid JSON response',
            'message' => json_last_error_msg()
            ], 500);
        }

        return Datatables::of($data)->make(true);
    }
}
