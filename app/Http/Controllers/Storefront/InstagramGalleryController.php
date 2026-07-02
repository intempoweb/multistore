<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Integrations\InstagramFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstagramGalleryController extends Controller
{
    public function index(Request $request, InstagramFeedService $instagramFeed): JsonResponse
    {
        $offset = max(0, (int) $request->integer('offset', 0));
        $limit = max(1, min((int) $request->integer('limit', 12), 24));
        $totalLimit = max($offset + $limit + 1, 36);

        $items = $instagramFeed->latest($totalLimit);
        $slice = $items->slice($offset, $limit)->values();

        return response()->json([
            'items' => $slice,
            'next_offset' => $offset + $slice->count(),
            'has_more' => $items->count() > ($offset + $slice->count()),
        ]);
    }
}
