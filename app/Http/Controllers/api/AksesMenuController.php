<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Menu;
use App\Models\AksesMenu;
use App\Models\MasterKaryawan;
use App\Models\TemplateAkses;
use App\Services\GetBawahan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class AksesMenuController extends Controller
{
    public function index(Request $request)
    {
        if($this->user_id == 1 || $this->user_id == 127 || $this->user_id == 152 ){
            $aksesMenus = AksesMenu::with('karyawan');
        } else {
            $subordinates = GetBawahan::where('id', $this->user_id)->get()->pluck('user_id')->toArray();
            unset($subordinates[array_search($this->user_id, $subordinates)]);
            $aksesMenus = AksesMenu::whereIn('akses_menu.user_id', $subordinates)->with('karyawan');
            // $aksesMenus = AksesMenu::whereIn('user_id', $subordinates)->with('karyawan');
        }
        return Datatables::of($aksesMenus)->make(true);
    }

    public function store(Request $request)
    {

        // $transformedAkses = $this->transformAccess($request->input('akses'));

        $aksesMenu = AksesMenu::updateOrCreate(
            ['user_id' => $request->input('user_id')],
            [
                'akses' => $request->input('akses'),
                'copy_access' => $request->input('copy_access'),
                'paste_access' => $request->input('paste_access'),
            ]
        );
        
        if($aksesMenu){
            return response()->json(['message' => 'Data hasbeen save.'], 200);
        } else {
            return response()->json(['message' => 'Something wrong.'], 401);
        }
    }

    public function getKaryawan(Request $request){
        $userId = $this->user_id;
        
        if ($userId == 1 || $userId == 127 || $userId == 152) {
            $data = MasterKaryawan::where('master_karyawan.is_active', true)
                ->leftJoin('akses_menu', 'master_karyawan.user_id', '=', 'akses_menu.user_id')
                ->whereNull('akses_menu.user_id')
                ->select('master_karyawan.id', 'master_karyawan.user_id', 'master_karyawan.nama_lengkap')
                ->get();
        } else {
            $subordinates = GetBawahan::where('id', $userId)->get()->pluck('user_id')->toArray();
            unset($subordinates[array_search($userId, $subordinates)]);
            $data = MasterKaryawan::whereIn('master_karyawan.user_id', $subordinates)
                ->where('master_karyawan.is_active', true)
                ->leftJoin('akses_menu', 'master_karyawan.user_id', '=', 'akses_menu.user_id')
                ->whereNull('akses_menu.user_id')
                ->select('master_karyawan.id', 'master_karyawan.user_id', 'master_karyawan.nama_lengkap')
                ->get();
        }

        return response()->json([
            'message' => 'get data karyawan success',
            'data' => $data
        ]);
    }

    public function getMenu(Request $request){
        $userId = $this->user_id;
        
        $data = Menu::where('is_active', true)->get();

        $transformedData = $data->map(function ($item) {
            $children = collect($item->submenu)->map(function ($submenu) {
                $submenu = (object) $submenu;
                $children = collect($submenu->sub_menu)->map(function ($subMenuItem) {
                    return [
                        'name' => $subMenuItem,
                        'path' => '/' . \Illuminate\Support\Str::slug($subMenuItem)
                    ];
                });

                return [
                    'name' => $submenu->nama_inden_menu,
                    'path' => '/' . \Illuminate\Support\Str::slug($submenu->nama_inden_menu),
                    'children' => $children
                ];
            });

            return [
                'name' => $item->menu,
                'icon' => $item->icon,
                'children' => $children,
                'path' => '/' . \Illuminate\Support\Str::slug($item->menu)
            ];
        });

        if ($userId != 1 && $userId != 127 && $userId != 152) {
            $userMenu = AksesMenu::join('master_karyawan', 'akses_menu.user_id', '=', 'master_karyawan.user_id')
                ->where('master_karyawan.id', $userId)
                ->select('akses_menu.*')
                ->first();
            if (!$userMenu) {
                return response()->json([
                    'message' => 'User tidak memiliki akses menu',
                    'data' => []
                ], 401);
            }

            
            $userMenuNames = collect($userMenu->akses)->pluck('name')->toArray();
            

            $filteredData = $transformedData->map(function ($menu) use ($userMenuNames) {
                $findDeepestName = function (&$item) use (&$findDeepestName, $userMenuNames) {
                    // loop sesuai induk menu

                    if (isset($item['children']) && !empty($item['children'])) {
                        $item['children'] = collect($item['children'])->filter(function (&$child) use (&$findDeepestName, $userMenuNames) {
                            if(in_array($child['name'], $userMenuNames)){
                                return in_array($child['name'], $userMenuNames);
                            } else {
                                return $findDeepestName($child);
                            }
                        })->values()->all();

                        if(in_array($item['name'], $userMenuNames) && empty($item['children'])){
                            return $item;
                        }
                        return !empty($item['children']);
                    } 

                };
                
                if ($findDeepestName($menu)) {
                    // Menghapus item paling dalam yang tidak ada dalam $userMenuNames\
                    
                    $removeDeepestUnauthorized = function (&$item) use (&$removeDeepestUnauthorized, $userMenuNames) {
                        if (isset($item['children']) && !empty($item['children'])) {
                            $item['children'] = collect($item['children'])->map(function (&$child) use (&$removeDeepestUnauthorized, $userMenuNames) {
                                $removeDeepestUnauthorized($child);
                                return $child;
                            })->filter(function ($child) use ($userMenuNames) {
                                return !empty($child['children']) || in_array($child['name'], $userMenuNames);
                            })->values()->all();
                        }
                    };

                    $removeDeepestUnauthorized($menu);
                    return $menu;
                }
                
                return null;
            })->filter()->values();

            $transformedData = $filteredData;

        }

        return response()->json([
            'message' => 'get data menu success',
            'data' => $transformedData
        ]);
    }    

    public function delete(Request $request)
    {
        $aksesMenu = AksesMenu::where('id', $request->id)->first();

        if (!$aksesMenu) {
            return response()->json(['message' => 'Data not found'], 404);
        }   

        $aksesMenu->delete();

        return response()->json(['message' => 'Data hasbeen delete'], 200);
    }

    public function getTemplateAkses(Request $request){
        $data = TemplateAkses::where('is_active', true)->where('userid', $this->user_id)->get();
        return response()->json([
            'message' => 'get data template akses success',
            'data' => $data
        ]);
    }
}


