<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{
    Benefits,
    News,
    CategoryService,
    Service,
    CompanyCustomer,
    Sliders,
    CompanySertifikasi,
    PromoWebsite,
    WebControl,
    MasterKategori,
};
use Illuminate\Http\Request;
use App\Http\Controllers\api\CompanyCustomerController;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Repository;


class WebsiteController extends Controller
{
    public function mainIndex(Request $request)
    {
        $news = News::with(['category'])->where('is_active', true)->get();
        $news->map(function ($item) {
            $item->content = Repository::dir('news_content')->key(explode('.', $item->content)[0])->get();
            $imagePath = public_path('profile/news/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/news/' . $item->image;
            }
            return $item;
        });

        $services = Service::with(['category'])->where('is_active', true)->get();
        $services->map(function ($item) {
            $imagePath = public_path('profile/service/image/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/service/image/' . $item->image;
            }
            return $item;
        });

        $customers = CompanyCustomer::where('is_active', true)->get();
        $customers->map(function ($item) {
            $imagePath = public_path('profile/customer/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/customer/' . $item->image;
            }
            return $item;
        });

        $sertifikasi = CompanySertifikasi::where('is_active', true)->get();
        $sertifikasi->map(function ($item) {
            $imagePath = public_path('profile/sertifikasi/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/sertifikasi/' . $item->image;
            }
            return $item;
        });

        $promo = PromoWebsite::where('is_active', 1)->get();
        $promo->map(function ($item) {
            $imagePath = public_path('profile/promo/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/promo/' . $item->image;
            }
            return $item;
        });

        $meta = WebControl::get();
        $meta->map(function ($item) {
            $item->stats = json_decode($item->stats);
            $item->logo = env('APP_URL') . '/public/profile/control/' . $item->logo;
            $item->favicon = env('APP_URL') . '/public/profile/control/' . $item->favicon;
            $item->meta = json_decode($item->meta);
            return $item;
        });


        return response()->json([
            'benefits' => Benefits::where('is_active', true)->get(),
            'news' => $news,
            'categoryServices' => CategoryService::with(['lingkup'])->where('is_active', true)->get(),
            'services' => $services,
            'customers' => $customers,
            'sliders' => Sliders::where('is_active', true)->get(),
            'sertifikasi' => $sertifikasi,
            'promo' => $promo,
            'meta' => $meta,
        ], 200);
    }

    public function mainContact(Request $request)
    {
        $meta = WebControl::get();
        $meta->map(function ($item) {
            $item->stats = json_decode($item->stats);
            $item->logo = env('APP_URL') . '/public/profile/control/' . $item->logo;
            $item->favicon = env('APP_URL') . '/public/profile/control/' . $item->favicon;
            $item->meta = json_decode($item->meta);
            return $item;
        });

        $categories = MasterKategori::with('subCategories')
            ->whereHas('subCategories')
            ->where('is_active', true)
            ->get()
            ->map(function ($kategori) {
                return [
                    'id' => $kategori->id,
                    'text' => $kategori->nama_kategori,
                    'children' => $kategori->subCategories->map(function ($sub) {
                        return [
                            'id' => $sub->id,
                            'text' => $sub->nama_sub_kategori,
                        ];
                    }),
                ];
            })->toArray();

        return response()->json([
            'meta' => $meta,
            'categories' => $categories,
        ], 200);
    }
}