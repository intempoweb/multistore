@extends($storefrontLayout)

@section('title', __('inquiries.contact.page_title'))

@section('content')
<div class="storefront-inquiry-page storefront-contact-page py-4 py-lg-5">
    <div class="container">
        <div class="row g-4 g-xl-5 align-items-start">
            <div class="col-12 col-lg-5">
                <div class="storefront-inquiry-intro p-4 p-lg-5">
                    <p class="storefront-inquiry-kicker mb-2">{{ __('inquiries.contact.kicker') }}</p>
                    <h1 class="storefront-inquiry-title mb-3">{{ __('inquiries.contact.title') }}</h1>
                    <p class="storefront-inquiry-subtitle mb-4">{{ __('inquiries.contact.subtitle') }}</p>

                    <div class="storefront-inquiry-meta d-grid gap-2">
                        @if(!empty($legalProfile['email']))
                            <a href="mailto:{{ $legalProfile['email'] }}" class="storefront-inquiry-meta-item text-decoration-none">
                                <i class="fa-solid fa-envelope"></i>
                                <span>{{ $legalProfile['email'] }}</span>
                            </a>
                        @endif
                        @if(!empty($legalProfile['phone']))
                            <a href="tel:{{ preg_replace('/\s+/', '', (string) $legalProfile['phone']) }}" class="storefront-inquiry-meta-item text-decoration-none">
                                <i class="fa-solid fa-phone"></i>
                                <span>{{ $legalProfile['phone'] }}</span>
                            </a>
                        @endif
                    </div>

                    @if(Route::has('storefront.corporate-gift.index'))
                        <div class="mt-4">
                            <a href="{{ route('storefront.corporate-gift.index') }}" class="btn btn-dark storefront-inquiry-alt-cta">
                                {{ __('inquiries.contact.corporate_cta') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="storefront-inquiry-form-wrap p-4 p-lg-5">
                    <form method="POST" action="{{ route('storefront.contact.submit') }}" class="row g-3">
                        @csrf
                        @include('storefront.base.partials.recaptcha', ['action' => 'contact'])

                        <div class="col-12 col-md-6">
                            <label for="contact-first-name" class="form-label">{{ __('inquiries.fields.first_name') }}</label>
                            <input id="contact-first-name" type="text" name="first_name" value="{{ old('first_name') }}" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="contact-last-name" class="form-label">{{ __('inquiries.fields.last_name') }}</label>
                            <input id="contact-last-name" type="text" name="last_name" value="{{ old('last_name') }}" class="form-control" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="contact-country" class="form-label">{{ __('inquiries.fields.country') }}</label>
                            <input id="contact-country" type="text" name="country" value="{{ old('country') }}" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="contact-company" class="form-label">{{ __('inquiries.fields.company') }}</label>
                            <input id="contact-company" type="text" name="company" value="{{ old('company') }}" class="form-control">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="contact-email" class="form-label">{{ __('inquiries.fields.email') }}</label>
                            <input id="contact-email" type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="contact-phone" class="form-label">{{ __('inquiries.fields.phone') }}</label>
                            <input id="contact-phone" type="text" name="phone" value="{{ old('phone') }}" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <label for="contact-subject" class="form-label">{{ __('inquiries.fields.subject') }}</label>
                            <input id="contact-subject" type="text" name="subject" value="{{ old('subject') }}" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <label for="contact-message" class="form-label">{{ __('inquiries.fields.message') }}</label>
                            <textarea id="contact-message" name="message" rows="5" class="form-control" required>{{ old('message') }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="contact-privacy" name="privacy_acceptance" value="1" {{ old('privacy_acceptance') ? 'checked' : '' }} required>
                                <label class="form-check-label" for="contact-privacy">
                                    {{ __('inquiries.fields.privacy_acceptance') }}
                                </label>
                            </div>
                        </div>

                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-dark px-4 py-2 storefront-inquiry-submit">
                                {{ __('inquiries.contact.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
