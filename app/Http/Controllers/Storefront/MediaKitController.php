<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\MediaKitRequest;
use App\Services\MediaKit\MediaKitDownloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MediaKitController extends Controller
{
    public function show(Request $request, MediaKitRequest $mediaKitRequest)
    {
        $this->assertOwner($request, $mediaKitRequest);

        return response()->json($mediaKitRequest->fresh());
    }

    public function download(
        Request $request,
        MediaKitRequest $mediaKitRequest,
        MediaKitDownloadService $download
    ): RedirectResponse {
        $this->assertOwner($request, $mediaKitRequest);

        try {
            return redirect()->away($download->temporaryUrl($mediaKitRequest));
        } catch (Throwable $e) {
            abort(Response::HTTP_GONE, $e->getMessage());
        }
    }

    private function assertOwner(Request $request, MediaKitRequest $mediaKitRequest): void
    {
        $user = $request->user();
        $customerId = $user?->getAuthIdentifier();

        abort_unless(
            $customerId
            && (int) $mediaKitRequest->customer_id === (int) $customerId,
            Response::HTTP_NOT_FOUND
        );
    }
}
