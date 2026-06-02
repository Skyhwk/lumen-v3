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
    CompanyPageControl,
    LingkupService,
    CategoryNews,
    Faq,
    KebijakanPrivasi,
};
use Illuminate\Http\Request;
use Repository;

/**
 * API khusus website publik (Website-pak-eko).
 * Terpisah dari WebsiteController agar tidak bentrok dengan frontend admin LUMEN-V3.
 */
class PublicWebsiteController extends Controller
{
    public function bootstrapLayout(Request $request)
    {
        return response()->json([
            'webControl' => $this->formatWebControls(WebControl::get())->first(),
            'pageImage' => $this->formatPageImages(CompanyPageControl::get()),
            'lingkups' => $this->formatLingkups(LingkupService::where('is_active', true)->get()),
        ], 200);
    }

    public function pageMeta(Request $request)
    {
        $menu = $request->input('menu', '');

        return response()->json([
            'meta' => $this->pageMetaForMenu($menu),
        ], 200);
    }

    public function servicesPage(Request $request)
    {
        $services = Service::with(['category'])->where('is_active', true)->get();
        $services->map(function ($item) {
            $imagePath = public_path('profile/service/image/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = $this->publicAssetUrl('profile/service/image/' . $item->image);
            }
            return $item;
        });

        return response()->json([
            'categoryServices' => CategoryService::with(['lingkup'])->where('is_active', true)->get(),
            'services' => $services,
            'meta' => $this->pageMetaForMenu('Our Service'),
        ], 200);
    }

    public function newsPage(Request $request)
    {
        return response()->json([
            'newsCategory' => CategoryNews::where('is_active', true)->select('id', 'title')->get(),
            'news' => $this->mapNewsList(
                News::with(['category'])->where('is_active', true)->orderBy('created_at', 'desc')->get()
            ),
            'meta' => $this->pageMetaForMenu('News'),
        ], 200);
    }

    public function newsDetailPage(Request $request)
    {
        $detail = News::with(['category'])->where('is_active', true)->find($request->id);
        if (!$detail) {
            return response()->json(['message' => 'Berita tidak ditemukan'], 404);
        }

        $detail = $this->mapNewsItem($detail, true);

        return response()->json([
            'detail' => $detail,
            'newsCategory' => CategoryNews::where('is_active', true)->select('id', 'title')->get(),
            'news' => $this->mapNewsList(
                News::with(['category'])
                    ->where('is_active', true)
                    ->where('id', '!=', $detail->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(12)
                    ->get()
            ),
            'meta' => $this->pageMetaForMenu('News'),
        ], 200);
    }

    public function newsCategoryPage(Request $request)
    {
        $news = News::with(['category'])
            ->where('category_news_id', $request->id_category)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($news->isEmpty()) {
            return response()->json(['empty' => true], 200);
        }

        return response()->json([
            'empty' => false,
            'news' => $this->mapNewsList($news),
            'newsCategory' => CategoryNews::where('is_active', true)->select('id', 'title')->get(),
            'newsAll' => $this->mapNewsList(
                News::with(['category'])->where('is_active', true)->orderBy('created_at', 'desc')->limit(20)->get()
            ),
            'meta' => $this->pageMetaForMenu('News'),
        ], 200);
    }

    public function faqPage(Request $request)
    {
        $faq = Faq::where('is_active', 1)->get();
        $kebijakan = KebijakanPrivasi::where('is_active', 1)->get();

        return response()->json([
            'data' => $faq,
            'kebijakan_privasi' => (object) ['data' => $kebijakan->values()->all()],
            'meta' => $this->pageMetaForMenu('Faq'),
        ], 200);
    }

    public function mainIndex(Request $request)
    {
        $news = News::with(['category'])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(12)
            ->get();
        $news = $this->mapNewsList($news);

        $services = Service::with(['category'])->where('is_active', true)->get();
        $services->map(function ($item) {
            $imagePath = public_path('profile/service/image/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = $this->publicAssetUrl('profile/service/image/' . $item->image);
            }
            return $item;
        });

        $customers = CompanyCustomer::where('is_active', true)->get();
        $customers->map(function ($item) {
            $imagePath = public_path('profile/customer/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = $this->publicAssetUrl('profile/customer/' . $item->image);
            }
            return $item;
        });

        $sertifikasi = CompanySertifikasi::where('is_active', true)->get();
        $sertifikasi->map(function ($item) {
            $imagePath = public_path('profile/sertifikasi/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = $this->publicAssetUrl('profile/sertifikasi/' . $item->image);
            }
            return $item;
        });

        $promo = PromoWebsite::where('is_active', 1)->get();
        $promo->map(function ($item) {
            $imagePath = public_path('profile/promo/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = $this->publicAssetUrl('profile/promo/' . $item->image);
            }
            return $item;
        });

        $meta = $this->formatWebControls(WebControl::get());

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
        $meta = $this->formatWebControls(WebControl::get());

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
            'pageMeta' => $this->pageMetaForMenu('Contact'),
        ], 200);
    }

    private function publicAssetUrl(string $path): string
    {
        return rtrim(env('APP_URL', ''), '/') . '/public/' . ltrim($path, '/');
    }

    private function formatWebControls($collection)
    {
        return $collection->map(function ($item) {
            $item->stats = json_decode($item->stats);
            if ($item->logo) {
                $item->logo = $this->publicAssetUrl('profile/control/' . $item->logo);
            }
            if ($item->favicon) {
                $item->favicon = $this->publicAssetUrl('profile/control/' . $item->favicon);
            }
            $item->meta = json_decode($item->meta);
            return $item;
        });
    }

    private function formatPageImages($collection)
    {
        return $collection->map(function ($item) {
            if ($item->image) {
                $item->image = $this->publicAssetUrl('profile/page-control/' . $item->image);
            }
            return $item;
        });
    }

    private function formatLingkups($collection)
    {
        return $collection->map(function ($item) {
            if ($item->image) {
                $item->image = $this->publicAssetUrl('profile/service/image/' . $item->image);
            }
            return $item;
        });
    }

    private function pageMetaForMenu(string $menu): array
    {
        $controls = $this->formatWebControls(WebControl::get());
        $metaList = isset($controls[0]) ? (array) $controls[0]->meta : [];

        return array_values(array_filter($metaList, function ($item) use ($menu) {
            return isset($item->menu) && $item->menu === $menu;
        }));
    }

    private function mapNewsList($news)
    {
        return $news->map(function ($item) {
            return $this->mapNewsItem($item, false);
        });
    }

    private function mapNewsItem($item, bool $withContent)
    {
        if ($withContent && $item->content) {
            $item->content = Repository::dir('news_content')->key(explode('.', $item->content)[0])->get();
        } else {
            unset($item->content);
        }

        if ($item->image) {
            $imagePath = public_path('profile/news/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = $this->publicAssetUrl('profile/news/' . $item->image);
            }
        }

        return $item;
    }
}
