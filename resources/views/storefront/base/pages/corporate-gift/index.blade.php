@extends($storefrontLayout)

@section('title', __('inquiries.corporate_gift.page_title'))

@section('content')
<div class="storefront-inquiry-page storefront-corporate-gift-page py-4 py-lg-5">
    <div class="container">
        <div class="row g-4 g-xl-5 align-items-start">
            <div class="col-12 col-lg-5">
                <div class="storefront-inquiry-intro p-4 p-lg-5">
                    <p class="storefront-inquiry-kicker mb-2">{{ __('inquiries.corporate_gift.kicker') }}</p>
                    <h1 class="storefront-inquiry-title mb-3">{{ __('inquiries.corporate_gift.title') }}</h1>
                    <p class="storefront-inquiry-subtitle mb-4">{{ __('inquiries.corporate_gift.subtitle') }}</p>

                    <div class="storefront-inquiry-badges d-flex flex-wrap gap-2">
                        <span class="badge text-bg-light border">{{ __('inquiries.corporate_gift.badge_min_qty') }}</span>
                        <span class="badge text-bg-light border">{{ __('inquiries.corporate_gift.badge_logo') }}</span>
                        <span class="badge text-bg-light border">{{ __('inquiries.corporate_gift.badge_attachment') }}</span>
                    </div>

                    @if(Route::has('storefront.contact.index'))
                        <div class="mt-4">
                            <a href="{{ route('storefront.contact.index') }}" class="btn btn-outline-dark storefront-inquiry-alt-cta">
                                {{ __('inquiries.corporate_gift.contact_cta') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="storefront-inquiry-form-wrap p-4 p-lg-5">
                    <form method="POST" action="{{ route('storefront.corporate-gift.submit') }}" enctype="multipart/form-data" class="row g-3">
                        @csrf

                        <div class="col-12 col-md-6">
                            <label for="gift-first-name" class="form-label">{{ __('inquiries.fields.first_name') }}</label>
                            <input id="gift-first-name" type="text" name="first_name" value="{{ old('first_name') }}" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gift-last-name" class="form-label">{{ __('inquiries.fields.last_name') }}</label>
                            <input id="gift-last-name" type="text" name="last_name" value="{{ old('last_name') }}" class="form-control" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="gift-country" class="form-label">{{ __('inquiries.fields.country') }}</label>
                            <input id="gift-country" type="text" name="country" value="{{ old('country') }}" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gift-company" class="form-label">{{ __('inquiries.fields.company') }}</label>
                            <input id="gift-company" type="text" name="company" value="{{ old('company') }}" class="form-control" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="gift-product" class="form-label">{{ __('inquiries.fields.product') }}</label>
                            <select id="gift-product" name="product_type" class="form-select" required>
                                <option value="">{{ __('inquiries.fields.select_product') }}</option>
                                <option value="agenda" {{ old('product_type') === 'agenda' ? 'selected' : '' }}>{{ __('inquiries.products.agenda') }}</option>
                                <option value="taccuino" {{ old('product_type') === 'taccuino' ? 'selected' : '' }}>{{ __('inquiries.products.taccuino') }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gift-quantity" class="form-label">{{ __('inquiries.fields.quantity') }}</label>
                            <input id="gift-quantity" type="number" min="100" step="1" name="quantity" value="{{ old('quantity', 100) }}" class="form-control" required>
                            <div class="form-text">{{ __('inquiries.corporate_gift.quantity_hint') }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="gift-email" class="form-label">{{ __('inquiries.fields.email') }}</label>
                            <input id="gift-email" type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gift-phone" class="form-label">{{ __('inquiries.fields.phone') }}</label>
                            <input id="gift-phone" type="text" name="phone" value="{{ old('phone') }}" class="form-control" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="gift-logo" class="form-label">{{ __('inquiries.fields.logo_file') }}</label>
                            <input id="gift-logo" type="file" name="logo_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg,.pdf">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="gift-content" class="form-label">{{ __('inquiries.fields.content_attachment') }}</label>
                            <input id="gift-content" type="file" name="content_attachment" class="form-control" accept=".pdf,.doc,.docx,.txt,.rtf,.zip">
                        </div>

                        <div class="col-12">
                            <label for="gift-notes" class="form-label">{{ __('inquiries.fields.notes') }}</label>
                            <textarea id="gift-notes" name="notes" rows="5" class="form-control">{{ old('notes') }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="gift-privacy" name="privacy_acceptance" value="1" {{ old('privacy_acceptance') ? 'checked' : '' }} required>
                                <label class="form-check-label" for="gift-privacy">
                                    {{ __('inquiries.fields.privacy_acceptance') }}
                                </label>
                            </div>
                        </div>

                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-dark px-4 py-2 storefront-inquiry-submit">
                                {{ __('inquiries.corporate_gift.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
