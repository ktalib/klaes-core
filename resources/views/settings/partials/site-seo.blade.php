{{-- Site SEO Settings --}}
{{ Form::model($settings, ['route' => ['setting.site.seo'], 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
<div class="space-y-6">
    <div class="space-y-5">
        <div class="space-y-2">
            {{ Form::label('meta_seo_title', __('Meta Title'), ['class' => $labelClass]) }}
            {{ Form::text('meta_seo_title', $settings['meta_seo_title'], ['class' => $inputClass, 'placeholder' => __('Enter meta SEO title'), 'required' => 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('meta_seo_keyword', __('Meta Keyword'), ['class' => $labelClass]) }}
            {{ Form::text('meta_seo_keyword', $settings['meta_seo_keyword'], ['class' => $inputClass, 'placeholder' => __('Enter meta SEO keyword'), 'required' => 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('meta_seo_description', __('Meta Description'), ['class' => $labelClass]) }}
            {{ Form::textarea('meta_seo_description', $settings['meta_seo_description'], ['class' => $textareaClass, 'placeholder' => __('Enter meta SEO description'), 'required' => 'required', 'rows' => '2']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('meta_seo_image', __('Meta Image'), ['class' => $labelClass]) }}
            {{ Form::file('meta_seo_image', ['class' => $fileInputClass]) }}
        </div>
        @if (!empty($settings['meta_seo_image']))
            <div class="mt-4">
                <img src="{{ asset(Storage::url('upload/seo')) . '/' . $settings['meta_seo_image'] }}"
                    class="h-24 w-auto rounded-xl border border-slate-100 bg-white p-2 shadow-sm" alt="SEO Image">
            </div>
        @endif
    </div>

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
