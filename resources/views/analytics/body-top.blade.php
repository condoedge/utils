@if(config('services.google_tag_manager.enabled') && config('services.google_tag_manager.container_id'))
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.google_tag_manager.container_id') }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@endif
