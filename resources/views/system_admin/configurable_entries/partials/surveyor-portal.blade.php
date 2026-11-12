@php
    $portalLogoSlots = [
        'wordmark' => ['label' => 'Wordmark', 'key' => 'logo_wordmark'],
        'seal' => ['label' => 'Portal seal', 'key' => 'logo_seal'],
        'registration_banner' => ['label' => 'Registration banner', 'key' => 'logo_registration_banner'],
        'favicon' => ['label' => 'Browser icon', 'key' => 'logo_favicon'],
    ];
@endphp

<div class="iw-alert info">
    <i data-lucide="globe-2" class="h-4 w-4 mt-0.5"></i>
    <div>Manage MLPP-SG Portal logos, identity, theme colours and support contact details.</div>
</div>

@if($errors->any())
    <div class="iw-alert error" role="alert">
        <div>
            <strong>Settings were not saved.</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('configurable-entries.surveyor-portal.save') }}"
      enctype="multipart/form-data" data-iw-no-ajax class="space-y-5">
    @csrf

    <section class="iw-card">
        <div class="iw-card-head">
            <div class="iw-card-title"><i data-lucide="type" class="h-4 w-4"></i> Portal identity</div>
            <div class="iw-card-sub">These names are used in portal navigation and browser page titles.</div>
        </div>
        <div class="iw-card-body iw-grid md:grid-cols-3">
            <div class="iw-field">
                <label class="iw-label" for="brand_name">Brand name</label>
                <input class="iw-input" id="brand_name" name="brand_name" maxlength="80" required
                       value="{{ old('brand_name', $portalSettings['brand_name']) }}">
            </div>
            <div class="iw-field">
                <label class="iw-label" for="portal_name">Portal name</label>
                <input class="iw-input" id="portal_name" name="portal_name" maxlength="120" required
                       value="{{ old('portal_name', $portalSettings['portal_name']) }}">
            </div>
            <div class="iw-field">
                <label class="iw-label" for="office_name">Office name</label>
                <input class="iw-input" id="office_name" name="office_name" maxlength="160" required
                       value="{{ old('office_name', $portalSettings['office_name']) }}">
            </div>
            <div class="iw-field md:col-span-2">
                <label class="iw-label" for="ministry_line">Ministry line</label>
                <input class="iw-input" id="ministry_line" name="ministry_line" maxlength="200" required
                       value="{{ old('ministry_line', $portalSettings['ministry_line']) }}">
            </div>
            <div class="iw-field">
                <label class="iw-label" for="ministry_address">Ministry address</label>
                <input class="iw-input" id="ministry_address" name="ministry_address" maxlength="255" required
                       value="{{ old('ministry_address', $portalSettings['ministry_address']) }}">
            </div>
        </div>
    </section>

    <section class="iw-card">
        <div class="iw-card-head">
            <div class="iw-card-title"><i data-lucide="image" class="h-4 w-4"></i> Portal logos</div>
            <div class="iw-card-sub">PNG, JPG or WebP images up to 4 MB. Uploaded logos take effect immediately.</div>
        </div>
        <div class="iw-card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($portalLogoSlots as $slot => $meta)
                <div class="rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <img src="{{ asset($portalSettings[$meta['key']]) }}" alt="{{ $meta['label'] }}"
                             style="max-width:120px;max-height:64px;object-fit:contain">
                        <div class="font-semibold text-sm">{{ $meta['label'] }}</div>
                    </div>
                    <input type="file" name="logos[{{ $slot }}]" accept="image/png,image/jpeg,image/webp"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" name="restore_logo[{{ $slot }}]" value="1">
                        Restore shipped default
                    </label>
                    @error("logos.{$slot}")<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </section>

    <section class="iw-card">
        <div class="iw-card-head">
            <div class="iw-card-title"><i data-lucide="paintbrush" class="h-4 w-4"></i> Theme colours</div>
            <div class="iw-card-sub">These colours control the portal's shared navigation and interface accents.</div>
        </div>
        <div class="iw-card-body grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach([
                'primary_color' => 'Primary',
                'primary_dark_color' => 'Primary dark',
                'green_color' => 'Green',
                'gold_color' => 'Gold',
            ] as $key => $label)
                <div class="iw-field">
                    <label class="iw-label" for="{{ $key }}">{{ $label }}</label>
                    <input class="iw-input h-11 p-1" type="color" id="{{ $key }}" name="{{ $key }}"
                           value="{{ old($key, $portalSettings[$key]) }}" required>
                    @error($key)<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </section>

    <section class="iw-card">
        <div class="iw-card-head">
            <div class="iw-card-title"><i data-lucide="message-square" class="h-4 w-4"></i> Beacon request SMS recipient</div>
            <div class="iw-card-sub">Beacon-request SMS goes to this Surveyor General's Office number, not to the phone numbers on SG user accounts. In-app notifications still go to all active Survey - SG users.</div>
        </div>
        <div class="iw-card-body iw-grid md:grid-cols-2">
            <div class="iw-field">
                <label class="iw-label" for="beacon_request_sms_phone">SG phone number</label>
                <input class="iw-input" type="tel" id="beacon_request_sms_phone" name="beacon_request_sms_phone"
                       maxlength="50" required value="{{ old('beacon_request_sms_phone', $portalSettings['beacon_request_sms_phone']) }}">
                @error('beacon_request_sms_phone')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="iw-card">
        <div class="iw-card-head">
            <div class="iw-card-title"><i data-lucide="life-buoy" class="h-4 w-4"></i> Support contact</div>
            <div class="iw-card-sub">Optional details shown in the portal footer when provided.</div>
        </div>
        <div class="iw-card-body iw-grid md:grid-cols-2">
            <div class="iw-field">
                <label class="iw-label" for="support_email">Support email</label>
                <input class="iw-input" type="email" id="support_email" name="support_email" maxlength="255"
                       value="{{ old('support_email', $portalSettings['support_email']) }}">
            </div>
            <div class="iw-field">
                <label class="iw-label" for="support_phone">Support phone</label>
                <input class="iw-input" type="tel" id="support_phone" name="support_phone" maxlength="50"
                       value="{{ old('support_phone', $portalSettings['support_phone']) }}">
            </div>
        </div>
    </section>

    {{-- NIS / SURCON flash banners (CR 11112026-2, item 11). --}}
    @php
      $fbRows = old('flash_banners', $portalSettings['flash_banners'] ?? []);
      $fbRows = array_values(array_pad((array) $fbRows, max(count((array) $fbRows) + 1, 3), ['org' => '', 'title' => '', 'text' => '', 'link' => '']));
    @endphp
    <section class="iw-card">
        <div class="iw-card-head">
            <div class="iw-card-title"><i data-lucide="megaphone" class="h-4 w-4"></i> Flash banners (NIS / SURCON)</div>
            <div class="iw-card-sub">Notices that rotate in a strip at the top of the portal's homepage and signed-in pages. Leave a title blank to remove that banner. Up to 10.</div>
        </div>
        <div class="iw-card-body space-y-4">
            <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input type="hidden" name="flash_banners_on" value="0">
                <input type="checkbox" name="flash_banners_on" value="1" @checked(old('flash_banners_on', $portalSettings['flash_banners_on'] ?? '1') === '1')>
                Show the flash banners on the portal
            </label>
            <div class="grid grid-cols-1 gap-3" id="fbRows">
                @foreach ($fbRows as $i => $b)
                    <div class="rounded-lg border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-6 gap-3">
                        <div class="iw-field md:col-span-1">
                            <label class="iw-label" for="fb_{{ $i }}_org">Body</label>
                            <select class="iw-input" id="fb_{{ $i }}_org" name="flash_banners[{{ $i }}][org]">
                                @foreach (\App\Services\SurveyorPortalSettings::BANNER_ORGS as $org)
                                    <option value="{{ $org }}" @selected(($b['org'] ?? '') === $org)>{{ $org }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="iw-field md:col-span-2">
                            <label class="iw-label" for="fb_{{ $i }}_title">Title</label>
                            <input class="iw-input" id="fb_{{ $i }}_title" name="flash_banners[{{ $i }}][title]" maxlength="120" value="{{ $b['title'] ?? '' }}" placeholder="e.g. SURCON annual practising licence">
                        </div>
                        <div class="iw-field md:col-span-3">
                            <label class="iw-label" for="fb_{{ $i }}_link">Link (optional)</label>
                            <input class="iw-input" id="fb_{{ $i }}_link" name="flash_banners[{{ $i }}][link]" maxlength="255" value="{{ $b['link'] ?? '' }}" placeholder="https://…">
                        </div>
                        <div class="iw-field md:col-span-6">
                            <label class="iw-label" for="fb_{{ $i }}_text">Text</label>
                            <textarea class="iw-input" id="fb_{{ $i }}_text" name="flash_banners[{{ $i }}][text]" maxlength="300" rows="2">{{ $b['text'] ?? '' }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-500">To add more than one new banner at a time, save, and an empty row appears again.</p>
        </div>
    </section>

    <section class="iw-card">
        <div class="iw-card-head">
            <div class="iw-card-title"><i data-lucide="list-ordered" class="h-4 w-4"></i> Homepage information section</div>
            <div class="iw-card-sub">Edit the “How it works” steps and the private surveyor and preparation cards shown on the portal homepage.</div>
        </div>
        <div class="iw-card-body space-y-5">
            <div class="iw-grid md:grid-cols-2">
                <div class="iw-field">
                    <label class="iw-label" for="how_title">Steps section title</label>
                    <input class="iw-input" id="how_title" name="how_title" maxlength="120" required
                           value="{{ old('how_title', $portalSettings['how_title']) }}">
                </div>
                <div class="iw-field">
                    <label class="iw-label" for="how_subtitle">Steps section subtitle</label>
                    <input class="iw-input" id="how_subtitle" name="how_subtitle" maxlength="255" required
                           value="{{ old('how_subtitle', $portalSettings['how_subtitle']) }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($portalSettings['how_steps'] as $index => $step)
                    <div class="rounded-lg border border-gray-200 p-4">
                        <h3 class="font-semibold text-sm mb-3">Step {{ $index + 1 }}</h3>
                        <div class="iw-field">
                            <label class="iw-label" for="how_step_{{ $index }}_title">Title</label>
                            <input class="iw-input" id="how_step_{{ $index }}_title"
                                   name="how_steps[{{ $index }}][title]" maxlength="100" required
                                   value="{{ old("how_steps.{$index}.title", $step['title']) }}">
                        </div>
                        <div class="iw-field mt-3">
                            <label class="iw-label" for="how_step_{{ $index }}_text">Description</label>
                            <textarea class="iw-input" id="how_step_{{ $index }}_text"
                                      name="how_steps[{{ $index }}][text]" maxlength="500" rows="3" required>{{ old("how_steps.{$index}.text", $step['text']) }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="iw-grid md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4">
                    <h3 class="font-semibold text-sm mb-3">Private surveyors card</h3>
                    <div class="iw-field">
                        <label class="iw-label" for="private_title">Title</label>
                        <input class="iw-input" id="private_title" name="private_title" maxlength="120" required
                               value="{{ old('private_title', $portalSettings['private_title']) }}">
                    </div>
                    <div class="iw-field mt-3">
                        <label class="iw-label" for="private_description">Description</label>
                        <textarea class="iw-input" id="private_description" name="private_description"
                                  maxlength="500" rows="3" required>{{ old('private_description', $portalSettings['private_description']) }}</textarea>
                    </div>
                    <div class="iw-field mt-3">
                        <label class="iw-label" for="private_example">Example beacon number</label>
                        <input class="iw-input" id="private_example" name="private_example" maxlength="80" required
                               value="{{ old('private_example', $portalSettings['private_example']) }}">
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4">
                    <h3 class="font-semibold text-sm mb-3">Preparation card</h3>
                    <div class="iw-field">
                        <label class="iw-label" for="ready_title">Title</label>
                        <input class="iw-input" id="ready_title" name="ready_title" maxlength="120" required
                               value="{{ old('ready_title', $portalSettings['ready_title']) }}">
                    </div>
                    <div class="space-y-3 mt-3">
                        @foreach($portalSettings['ready_items'] as $index => $item)
                            <div class="iw-field">
                                <label class="iw-label" for="ready_item_{{ $index }}">Item {{ $index + 1 }}</label>
                                <input class="iw-input" id="ready_item_{{ $index }}"
                                       name="ready_items[{{ $index }}]" maxlength="200" required
                                       value="{{ old("ready_items.{$index}", $item) }}">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="iw-btn iw-btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i> Save Portal Settings
        </button>
    </div>
</form>
