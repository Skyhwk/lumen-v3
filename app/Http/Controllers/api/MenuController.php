<?php

namespace App\Http\Controllers\api;

use App\Models\Menu;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $data = Menu::where('is_active', true)->orderBy('menu', 'asc');
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {   
        if(isset($request->id) && $request->id!=null){
            $this->validate($request, [
                'icon' => 'required|string|max:70',
                'menu' => 'required|string|max:70'
            ]);
    
            $submenu = [];
            $i = 0;
            
            while ($request->has('submenu'.$i)) {
                $submenuItem = [
                    'nama_inden_menu' => $request->input('submenu'.$i),
                    'sub_menu' => $request->input('submenuu'.$i)
                ];
                $submenu[] = $submenuItem;
                $i++;
            }
            
            $menu = Menu::where('id', $request->id)->first();
            $menu->icon =$request->input('icon');
            $menu->menu =$request->input('menu');
            $menu->submenu =$submenu;
            $menu->save();
            
    
            return response()->json(['message' => 'Menu hasbeen Update.'], 200);
        } else {
            $this->validate($request, [
                'icon' => 'required|string|max:70',
                'menu' => 'required|string|max:70'
            ]);
    
            $submenu = [];
            $i = 0;
            
            while ($request->has('submenu'.$i)) {
                $submenuItem = [
                    'nama_inden_menu' => $request->input('submenu'.$i),
                    'sub_menu' => $request->input('submenuu'.$i)
                ];
                $submenu[] = $submenuItem;
                $i++;
            }
            
            
            $menu = Menu::create([
                'icon' => $request->input('icon'),
                'menu' => $request->input('menu'),
                'submenu' => $submenu
            ]);
    
            return response()->json(['message' => 'Menu hasbeen Save.'], 200);
        }
    }

    public function delete(Request $request)
    {

        $menu = Menu::where('id', $request->id)->first();

        if(!$menu){
            return response()->json(['message'=>'Data Not Found'], 400);
        }

        $menu->delete();

        return response()->json(['message'=>'Data hasbeen Delete'], 200);
    }
}
