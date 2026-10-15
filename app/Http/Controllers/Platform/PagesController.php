<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Platform legal pages and the sitemap. */
class PagesController extends Controller
{
    public function terms(): View
    {
        return view('platform.legal', ['page' => 'terms', 'title' => __('شروط الاستخدام')]);
    }

    public function privacy(): View
    {
        return view('platform.legal', ['page' => 'privacy', 'title' => __('سياسة الخصوصية')]);
    }

    public function sitemap(): Response
    {
        $base = request()->getSchemeAndHttpHost();
        $urls = collect(['/', '/signup', '/terms', '/privacy'])->map(fn ($path) => '<url><loc>'.e($base.$path).'</loc></url>')->implode('');

        return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>', 200,
            ['Content-Type' => 'application/xml']);
    }
}
