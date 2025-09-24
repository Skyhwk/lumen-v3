<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

use App\Models\Advertise;

use Datatables;

class AdvertisesController extends Controller
{
    public function index()
    {
        $advertises = Advertise::latest()->get();

        return Datatables::of($advertises)->make(true);
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid image, make sure it\'s jpg, jpeg, png and less than 2MB'], 400);
        }

        $file = $request->file('filename');
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();

        file_put_contents(public_path('advertises/' . $filename), file_get_contents($file->getRealPath()));

        $advertise = new Advertise();

        $advertise->filename = $filename;
        $advertise->expired_at = $request->expired_at;
        $advertise->created_by = $this->karyawan;
        $advertise->updated_by = $this->karyawan;
        $advertise->save();

        return response()->json(['message' => 'Saved Successfully'], 200);
    }

    public function destroy($id)
    {
        $advertise = Advertise::findOrFail($id);
        $advertise->is_active = false;
        $advertise->deleted_by = $this->karyawan;
        $advertise->save();

        $advertise->delete(); // udh soft delete

        return response()->json(['message' => 'Deleted Successfully'], 200);
    }
}
