<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notes;
use Carbon\Carbon;

class NotesController extends Controller
{
    public function getNotes()
    {
        $data = Notes::where('user_id', $this->user_id)->where('is_active', true)->get();
        return response()->json(['data' => $data, 'user_id' => $this->user_id], 200);
    }

    public function createNote(Request $request)
    {
        $data = new Notes;
        $data->user_id = $this->user_id;
        $data->text = $request->text;
        $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();

        return response()->json(['data' => $data], 200);
    }

    public function deleteNote(Request $request)
    {
        $data = Notes::where('id', $request->id)->update(['is_active' => false , 'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')]);
        return response()->json(['data' => $data], 200);
    }
}
