<?php


namespace App\Http\Controllers\api;

use App\Models\Announcement;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;




class AnnouncementController extends Controller
{

    public function index(Request $request)
    {

        $data = Announcement::orderBy('id', 'desc')

            ->get();

        return Datatables::of($data)
            ->addColumn('reff', function ($row) {
                $filePath = public_path('announcement/' . $row->filename);
                if (file_exists($filePath) && is_file($filePath)) {
                    return file_get_contents($filePath);
                } else {
                    return 'File not found';
                }
            })->make(true);
    }

    public function getDivisi()
    {
        $data = MasterDivisi::where('is_active', true)
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi'])
            ->unique('nama_divisi')
            ->values()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->nama_divisi,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getGrade()
    {
        $data = MasterKaryawan::where('is_active', true)
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->orderBy('grade')
            ->get(['grade'])
            ->unique('grade')
            ->values()
            ->map(function ($row) {
                return [
                    'id' => $row->grade,
                    'grade' => $row->grade,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getStatus()
    {
        $data = MasterKaryawan::where('is_active', true)
            ->whereNotNull('status_karyawan')
            ->where('status_karyawan', '!=', '')
            ->orderBy('status_karyawan')
            ->get(['status_karyawan'])
            ->unique('status_karyawan')
            ->values()
            ->map(function ($row) {
                return [
                    'id' => $row->status_karyawan,
                    'status_karyawan' => $row->status_karyawan,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getKaryawan()
    {
        $data = MasterKaryawan::where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->nama_lengkap,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function create (Request $request) {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'receiver_category' => 'required|string|in:Divisi,Grade,Status,Karyawan,All',
            'receiver_names' => 'required_if:receiver_category,Divisi,Grade,Status|array',
            'receiver_names.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($request->receiver_category === 'Karyawan' && empty($request->receiver_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Penerima harus diisi.',
            ], 422);
        }

        $attachmentError = $this->validateAnnouncementAttachments($request);
        if ($attachmentError) {
            return response()->json([
                'success' => false,
                'message' => $attachmentError,
            ], 422);
        }

        $resolved = $this->resolveReceiverIds($request);
        if (isset($resolved['error'])) {
            return response()->json([
                'success' => false,
                'message' => $resolved['error'],
            ], 422);
        }
        $receiverIds = $resolved['ids'];


        DB::beginTransaction();
        $storedFile = null;
        $storedAttachments = [];
        try {
            $announcement = new Announcement();
            $announcement->title = $request->title;
            $announcement->description = null;
            $announcement->receiver_category = $request->receiver_category;
            $announcement->receiver_ids = $receiverIds;
            $announcement->created_at = Carbon::now();
            $announcement->created_by = $this->karyawan;
            $announcement->save();

            $directory = public_path('announcement');
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Folder pengumuman tidak bisa dibuat.');
            }

            $filename = 'announcement-' . $announcement->id . '.txt';
            $storedFile = $directory . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($storedFile, $request->input('description')) === false) {
                throw new \RuntimeException('File pengumuman gagal disimpan.');
            }

            $announcement->filename = $filename;
            $announcement->attachments = $this->storeAnnouncementAttachments($request, $announcement->id, $storedAttachments);
            $announcement->save();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            if ($storedFile && is_file($storedFile)) {
                unlink($storedFile);
            }
            foreach ($storedAttachments as $attachmentPath) {
                if (is_file($attachmentPath)) {
                    unlink($attachmentPath);
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'Announcement Gagal Ditambahkan!',
                'error' => $th->getMessage(),
            ], 500);
        }

        $this->sendAnnouncementNotification($announcement, $request->input('description'));

        return response()->json([
            'success' => true,
            'message' => 'Announcement Berhasil Ditambahkan!',
            'data' => $announcement,
        ], 200);
    }

    private function sendAnnouncementNotification(Announcement $announcement, $html)
    {
        $receiverIds = $announcement->receiver_ids ?: [];
        if ($receiverIds === []) {
            return;
        }

        $body = $this->announcementNotificationBody($html, $announcement->title);

        try {
            (new \App\Services\Greatday\FirebaseService())->sendNotifications($receiverIds, [
                'title' => (string) $announcement->title,
                'body' => $body,
                'url' => '/notifications',
                'kind' => 'announcement',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Announcement FCM gagal dikirim', [
                'announcement_id' => $announcement->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function announcementNotificationBody($html, $title)
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            $text = trim((string) $title);
        }
        if (mb_strlen($text) > 180) {
            $text = rtrim(mb_substr($text, 0, 177)) . '...';
        }

        return $text;
    }


    public function update(Request $request)
    {
        $keepReceivers = $request->boolean('keep_receivers');
        $rules = [
            'id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'receiver_category' => 'required|string|in:Divisi,Grade,Status,Karyawan,All',
            'receiver_names' => 'nullable|array',
            'receiver_names.*' => 'required|string',
        ];
        if (!$keepReceivers) {
            $rules['receiver_names'] = 'required_if:receiver_category,Divisi,Grade,Status|array';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $announcement = Announcement::find($request->id);
        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement tidak ditemukan.',
            ], 404);
        }

        $attachmentError = $this->validateAnnouncementAttachments($request);
        if ($attachmentError) {
            return response()->json([
                'success' => false,
                'message' => $attachmentError,
            ], 422);
        }

        if ($keepReceivers && $announcement->receiver_category === $request->receiver_category) {
            $receiverIds = $announcement->receiver_ids ?: [];
        } else {
            $resolved = $this->resolveReceiverIds($request);
            if (isset($resolved['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $resolved['error'],
                ], 422);
            }
            $receiverIds = $resolved['ids'];
        }

        DB::beginTransaction();
        $storedAttachments = [];
        $contentPath = null;
        $previousContent = null;
        $contentReplaced = false;
        try {
            $directory = public_path('announcement');
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Folder pengumuman tidak bisa dibuat.');
            }

            $filename = $announcement->filename ?: ('announcement-' . $announcement->id . '.txt');
            $contentPath = $directory . DIRECTORY_SEPARATOR . $filename;
            if (is_file($contentPath)) {
                $previousContent = file_get_contents($contentPath);
            }
            if (file_put_contents($contentPath, $request->input('description')) === false) {
                throw new \RuntimeException('File pengumuman gagal disimpan.');
            }
            $contentReplaced = true;

            $existingAttachments = is_array($announcement->attachments) ? $announcement->attachments : [];
            $removedAttachments = collect($request->input('removed_attachments', []))
                ->map(function ($name) {
                    return basename((string) $name);
                })
                ->filter(function ($name) use ($existingAttachments) {
                    return $name !== '' && in_array($name, $existingAttachments, true);
                })
                ->values();
            $remainingAttachments = array_values(array_filter($existingAttachments, function ($name) use ($removedAttachments) {
                return !$removedAttachments->contains($name);
            }));
            $newAttachments = $this->storeAnnouncementAttachments(
                $request,
                $announcement->id,
                $storedAttachments,
                count($existingAttachments)
            );

            $announcement->title = $request->title;
            $announcement->description = null;
            $announcement->filename = $filename;
            $announcement->receiver_category = $request->receiver_category;
            $announcement->receiver_ids = $receiverIds;
            $announcement->attachments = array_values(array_merge($remainingAttachments, $newAttachments));
            $announcement->is_read = null;
            $announcement->is_deleted = null;
            $announcement->updated_at = Carbon::now();
            $announcement->updated_by = $this->karyawan;
            $announcement->save();
            DB::commit();
            $this->deleteAnnouncementAttachmentFiles($removedAttachments->all());

            return response()->json([
                'success' => true,
                'message' => 'Announcement Berhasil Diubah!',
                'data' => $announcement,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            if ($contentReplaced && $contentPath) {
                if ($previousContent === null) {
                    if (is_file($contentPath)) {
                        unlink($contentPath);
                    }
                } else {
                    file_put_contents($contentPath, $previousContent);
                }
            }
            foreach ($storedAttachments as $attachmentPath) {
                if (is_file($attachmentPath)) {
                    unlink($attachmentPath);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Announcement Gagal Diubah!',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function selectedReceivers(Request $request)
    {
        $announcement = Announcement::find($request->id);
        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement tidak ditemukan.',
            ], 404);
        }

        $ids = collect($announcement->receiver_ids ?: [])->filter()->values();
        $data = collect();

        if ($announcement->receiver_category === 'Karyawan') {
            $data = MasterKaryawan::whereIn('id', $ids->all())
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap'])
                ->map(function ($row) {
                    return [
                        'value' => (string) $row->id,
                        'label' => $row->nama_lengkap,
                    ];
                });
        } elseif ($announcement->receiver_category === 'Divisi') {
            $departmentIds = MasterKaryawan::whereIn('id', $ids->all())
                ->pluck('id_department')
                ->filter()
                ->unique()
                ->values();

            $data = MasterDivisi::whereIn('id', $departmentIds->all())
                ->orderBy('nama_divisi')
                ->get(['id', 'nama_divisi'])
                ->unique('nama_divisi')
                ->values()
                ->map(function ($row) {
                    return [
                        'value' => (string) $row->id,
                        'label' => $row->nama_divisi,
                    ];
                });
        } elseif ($announcement->receiver_category === 'Grade') {
            $data = MasterKaryawan::whereIn('id', $ids->all())
                ->whereNotNull('grade')
                ->where('grade', '!=', '')
                ->orderBy('grade')
                ->pluck('grade')
                ->unique()
                ->values()
                ->map(function ($grade) {
                    return [
                        'value' => (string) $grade,
                        'label' => (string) $grade,
                    ];
                });
        } elseif ($announcement->receiver_category === 'Status') {
            $data = MasterKaryawan::whereIn('id', $ids->all())
                ->whereNotNull('status_karyawan')
                ->where('status_karyawan', '!=', '')
                ->orderBy('status_karyawan')
                ->pluck('status_karyawan')
                ->unique()
                ->values()
                ->map(function ($status) {
                    return [
                        'value' => (string) $status,
                        'label' => (string) $status,
                    ];
                });
        }

        return response()->json([
            'success' => true,
            'data' => $data->values(),
        ]);
    }

    private function validateAnnouncementAttachments(Request $request)
    {
        $files = $request->file('attachments') ?: [];
        if (!is_array($files)) {
            $files = [$files];
        }

        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'txt', 'zip'];
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowed, true)) {
                return 'Tipe file lampiran tidak didukung.';
            }

            if ($file->getSize() > 10 * 1024 * 1024) {
                return 'Ukuran lampiran maksimal 10MB.';
            }
        }

        return null;
    }

    private function resolveReceiverIds(Request $request)
    {
        if ($request->receiver_category === 'Karyawan' && empty($request->receiver_ids)) {
            return ['error' => 'Penerima harus diisi.'];
        }

        $receiverIds = collect($request->input('receiver_ids', []))
            ->map(function ($id) {
                return (string) $id;
            })
            ->values()
            ->all();

        if ($request->receiver_category === 'All') {
            $receiverIds = MasterKaryawan::where('is_active', true)
                ->pluck('id')
                ->map(function ($id) {
                    return (string) $id;
                })
                ->values()
                ->all();

            if ($receiverIds === []) {
                return ['error' => 'Tidak ada karyawan aktif.'];
            }
        } elseif ($request->receiver_category === 'Divisi') {
            $names = collect($request->input('receiver_names', []))
                ->map(function ($name) {
                    return trim((string) $name);
                })
                ->filter()
                ->values();

            if ($names->isEmpty()) {
                return ['error' => 'Penerima harus diisi.'];
            }

            $divisiIds = MasterDivisi::where('is_active', true)
                ->whereIn('nama_divisi', $names->all())
                ->pluck('id');

            if ($divisiIds->isEmpty()) {
                return ['error' => 'Divisi tidak ditemukan.'];
            }

            $receiverIds = MasterKaryawan::where('is_active', true)
                ->whereIn('id_department', $divisiIds)
                ->pluck('id')
                ->map(function ($id) {
                    return (string) $id;
                })
                ->values()
                ->all();
        } elseif ($request->receiver_category === 'Grade') {
            $grades = collect($request->input('receiver_names', []))
                ->map(function ($grade) {
                    return trim((string) $grade);
                })
                ->filter()
                ->values();

            if ($grades->isEmpty()) {
                return ['error' => 'Penerima harus diisi.'];
            }

            $receiverIds = MasterKaryawan::where('is_active', true)
                ->whereIn('grade', $grades->all())
                ->pluck('id')
                ->map(function ($id) {
                    return (string) $id;
                })
                ->values()
                ->all();
        } elseif ($request->receiver_category === 'Status') {
            $statuses = collect($request->input('receiver_names', []))
                ->map(function ($status) {
                    return trim((string) $status);
                })
                ->filter()
                ->values();

            if ($statuses->isEmpty()) {
                return ['error' => 'Penerima harus diisi.'];
            }

            $receiverIds = MasterKaryawan::where('is_active', true)
                ->whereIn('status_karyawan', $statuses->all())
                ->pluck('id')
                ->map(function ($id) {
                    return (string) $id;
                })
                ->values()
                ->all();
        }

        return ['ids' => $receiverIds];
    }

    private function deleteAnnouncementAttachmentFiles(array $filenames)
    {
        $directory = public_path('announcement/attachments');
        foreach ($filenames as $filename) {
            $path = $directory . DIRECTORY_SEPARATOR . basename((string) $filename);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function storeAnnouncementAttachments(Request $request, $announcementId, array &$storedAttachments, $startIndex = 0)
    {
        $files = $request->file('attachments') ?: [];
        if (!is_array($files)) {
            $files = [$files];
        }

        $directory = public_path('announcement/attachments');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Folder lampiran tidak bisa dibuat.');
        }

        $saved = [];
        foreach ($files as $index => $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName());
            $filename = 'announcement-' . $announcementId . '-' . ($startIndex + $index + 1) . '-' . $safeName;
            $file->move($directory, $filename);
            $storedAttachments[] = $directory . DIRECTORY_SEPARATOR . $filename;
            $saved[] = $filename;
        }

        return $saved;
    }

    // public function saveAnnouncement(Request $request)
    // {
    //     // dd($request->all());
    //     DB::beginTransaction();
    //     try {

    //         $employee = MasterKaryawan::where('is_active', true)->pluck('id')
    //             ->map(fn($id) => (string) $id)
    //             ->toArray();
    //         if (empty($request->id)) {
    //             $announcement = new Announcement();
    //             $announcement->receiver_category = 'All';
    //             $announcement->receiver_ids = json_encode($employee);
    //             $announcement->created_at = Carbon::now();
    //             $announcement->created_by = $this->karyawan;

    //             $announcement->description = $request->judul;

    //             $microtime = str_replace(".", "", microtime(true));
    //             $uniq_id = $microtime;
    //             $filename = $microtime . '.txt';
    //             $content = $request->detail;
    //             $contentDir = 'announcement';

    //             if (!file_exists(public_path($contentDir))) {
    //                 mkdir(public_path($contentDir), 0777, true);
    //             }

    //             file_put_contents(public_path($contentDir . '/' . $filename), $content);

    //             $announcement->filename = $filename;

    //             $announcement->save();
    //             DB::commit();

    //             return response()->json(['message' => 'Announcement Berhasil Ditambahkan!', 'status' => 200], 200);


    //         } else {
    //             $announcement = Announcement::find($request->id);
    //             $announcement->description = $request->judul;
    //             $announcement->is_read = null;
    //             $announcement->is_deleted = null;
    //             $announcement->updated_at = Carbon::now();
    //             $announcement->updated_by = $this->karyawan;

    //             $microtime = str_replace(".", "", microtime(true));
    //             $uniq_id = $microtime;
    //             $filename = $microtime . '.txt';
    //             $content = $request->detail;
    //             $contentDir = 'announcement';

    //             if (!file_exists(public_path($contentDir))) {
    //                 mkdir(public_path($contentDir), 0777, true);
    //             }

    //             file_put_contents(public_path($contentDir . '/' . $filename), $content);

    //             $announcement->filename = $filename;

    //             $announcement->save();
    //             DB::commit();

    //             return response()->json(['message' => 'Announcement Berhasil Diubah!', 'status' => 201], 201);

    //         }
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => "Terjadi Kesalahan",
    //             "error" => $th->getMessage()
    //         ], 500);
    //     }
    // }

    public function deleteAnnouncement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $announcement = Announcement::find($request->id);
        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement tidak ditemukan.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $announcement->updated_at = Carbon::now();
            $announcement->updated_by = $this->karyawan;
            $announcement->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Announcement Gagal Dihapus!',
                'error' => $th->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Announcement Berhasil Dihapus!',
        ], 200);
    }


}