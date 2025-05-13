@component('mail::message')

<p>{!! __('utils.export-ready-message') !!}</p>

<p>{!! makeMailButton(__('utils.export-ready-button'), $downloadUrl) !!}</p>

@endcomponent
