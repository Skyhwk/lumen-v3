<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PanduanFdl;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PanduanFdlController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');
        $manage = filter_var($request->input('manage', false), FILTER_VALIDATE_BOOLEAN);

        $query = PanduanFdl::where('is_active', 1);

        if (!$manage) {
            $query->where('is_publish', 1);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%");
            });
        }

        $data = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $data->getCollection()->transform(function ($item) {
            return $this->appendReff($item);
        });

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $title = trim((string) $request->input('title'));
        $body = (string) $request->input('body', $request->input('reff', ''));

        if ($title === '') {
            return response()->json(['message' => 'Title wajib diisi.'], 422);
        }

        if (trim($body) === '') {
            return response()->json(['message' => 'Isi panduan wajib diisi.'], 422);
        }

        $item = new PanduanFdl();
        $item->title = $title;
        $item->body = $this->writePanduanFile($title, $body);
        $item->created_at = Carbon::now();
        $item->created_by = $request->input('created_by', 'System');
        $item->is_active = 1;
        $item->is_publish = $this->boolValue($request->input('is_publish', true));
        $item->save();

        return response()->json([
            'message' => 'Panduan FDL berhasil disimpan.',
            'data' => $this->appendReff($item),
        ]);
    }

    public function update(Request $request)
    {
        $item = PanduanFdl::where('is_active', 1)->find($request->input('id'));

        if (!$item) {
            return response()->json(['message' => 'Panduan FDL tidak ditemukan.'], 404);
        }

        $title = trim((string) $request->input('title', $item->title));
        $body = $request->input('body', $request->input('reff'));

        if ($title === '') {
            return response()->json(['message' => 'Title wajib diisi.'], 422);
        }

        $item->title = $title;

        if ($body !== null) {
            if (trim((string) $body) === '') {
                return response()->json(['message' => 'Isi panduan wajib diisi.'], 422);
            }

            $this->deletePanduanFile($item->body);
            $item->body = $this->writePanduanFile($title, (string) $body);
        }

        if ($request->has('is_publish')) {
            $item->is_publish = $this->boolValue($request->input('is_publish'));
        }

        $item->updated_at = Carbon::now();
        $item->updated_by = $request->input('updated_by', 'System');
        $item->save();

        return response()->json([
            'message' => 'Panduan FDL berhasil diupdate.',
            'data' => $this->appendReff($item),
        ]);
    }

    public function destroy(Request $request)
    {
        $item = PanduanFdl::where('is_active', 1)->find($request->input('id'));

        if (!$item) {
            return response()->json(['message' => 'Panduan FDL tidak ditemukan.'], 404);
        }

        $item->is_active = 0;
        $item->updated_at = Carbon::now();
        $item->updated_by = $request->input('updated_by', 'System');
        $item->save();

        return response()->json(['message' => 'Panduan FDL berhasil dinonaktifkan.']);
    }

    private function appendReff($item)
    {
        $filePath = public_path('panduan_fdl/' . $item->body);
        $item->reff = file_exists($filePath) && is_file($filePath)
            ? file_get_contents($filePath)
            : '';

        return $item;
    }

    private function writePanduanFile(string $title, string $body): string
    {
        $dir = public_path('panduan_fdl');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        $slug = $slug ?: 'panduan-fdl';
        $fileName = $slug . '-' . date('YmdHis') . '-' . random_int(1000, 9999) . '.html';

        file_put_contents($dir . DIRECTORY_SEPARATOR . $fileName, $body);

        return $fileName;
    }

    private function deletePanduanFile($fileName): void
    {
        if (!$fileName) {
            return;
        }

        $filePath = public_path('panduan_fdl/' . basename($fileName));

        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }
    }

    private function boolValue($value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
}
