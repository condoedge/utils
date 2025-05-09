@component('mail::message')

<p>{!! __('translate.export-ready-message') !!}</p>

<p>{!! makeMailButton(__('translate.export-ready-button'), $downloadUrl) !!}</p>

@endcomponent
