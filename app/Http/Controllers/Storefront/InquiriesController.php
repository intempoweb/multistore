<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ContactInquiryRequest;
use App\Http\Requests\Storefront\CorporateGiftInquiryRequest;
use App\Mail\Storefront\Inquiries\ContactInquiryMail;
use App\Mail\Storefront\Inquiries\CorporateGiftInquiryMail;
use App\Models\Store;
use App\Services\Storefront\LegalProfileResolver;
use App\Services\Storefront\Mail\StorefrontMailService;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InquiriesController extends Controller
{
    public function __construct(
        private StorefrontContext $context,
        private ThemeResolver $themeResolver,
        private StorefrontMailService $mailService,
        private LegalProfileResolver $legalProfileResolver,
    ) {}

    public function contact(): View
    {
        $store = $this->b2cStore();

        return view($this->themeResolver->view('contact.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'legalProfile' => $this->legalProfileResolver->resolve($store),
        ]);
    }

    public function sendContact(ContactInquiryRequest $request): RedirectResponse
    {
        $store = $this->b2cStore();
        $recipient = $this->mailService->internalRecipientForStore($store);

        if ($recipient === null) {
            return back()
                ->withInput()
                ->with('error', __('inquiries.errors.recipient_not_configured'));
        }

        $payload = [
            'first_name' => trim((string) $request->string('first_name')),
            'last_name' => trim((string) $request->string('last_name')),
            'country' => trim((string) $request->string('country')),
            'company' => trim((string) $request->string('company')),
            'email' => trim((string) $request->string('email')),
            'phone' => trim((string) $request->string('phone')),
            'subject' => trim((string) $request->string('subject')),
            'message' => trim((string) $request->string('message')),
        ];

        Mail::to($recipient)->send(new ContactInquiryMail($store, $payload));

        return redirect()
            ->route('storefront.contact.index')
            ->with('success', __('inquiries.contact.success'));
    }

    public function corporateGift(): View
    {
        $store = $this->b2cStore();

        return view($this->themeResolver->view('corporate-gift.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'legalProfile' => $this->legalProfileResolver->resolve($store),
        ]);
    }

    public function sendCorporateGift(CorporateGiftInquiryRequest $request): RedirectResponse
    {
        $store = $this->b2cStore();
        $recipient = $this->mailService->internalRecipientForStore($store);

        if ($recipient === null) {
            return back()
                ->withInput()
                ->with('error', __('inquiries.errors.recipient_not_configured'));
        }

        $payload = [
            'first_name' => trim((string) $request->string('first_name')),
            'last_name' => trim((string) $request->string('last_name')),
            'country' => trim((string) $request->string('country')),
            'company' => trim((string) $request->string('company')),
            'product_type' => trim((string) $request->string('product_type')),
            'quantity' => (int) $request->integer('quantity'),
            'email' => trim((string) $request->string('email')),
            'phone' => trim((string) $request->string('phone')),
            'notes' => trim((string) $request->string('notes')),
        ];

        $attachments = array_values(array_filter([
            $this->toMailAttachment($request->file('logo_file'), 'logo'),
            $this->toMailAttachment($request->file('content_attachment'), 'contenuto'),
        ]));

        Mail::to($recipient)->send(new CorporateGiftInquiryMail($store, $payload, $attachments));

        return redirect()
            ->route('storefront.corporate-gift.index')
            ->with('success', __('inquiries.corporate_gift.success'));
    }

    private function b2cStore(): Store
    {
        $store = $this->context->store();

        abort_if($store->isB2B(), 404);

        return $store;
    }

    private function toMailAttachment(?UploadedFile $file, string $fallbackPrefix): ?array
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            return null;
        }

        $originalName = trim((string) $file->getClientOriginalName());
        $filename = $originalName !== '' ? $originalName : ($fallbackPrefix . '.' . ($file->extension() ?: 'bin'));

        return [
            'name' => $filename,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'content' => $content,
        ];
    }
}
