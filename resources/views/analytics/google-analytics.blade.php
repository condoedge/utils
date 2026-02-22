{{-- Google Analytics 4 (gtag.js) - Direct Integration --}}
@if(config('services.google_analytics.measurement_id'))
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.measurement_id') }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{{ config('services.google_analytics.measurement_id') }}');
</script>

@auth
@php
    $userData = \Condoedge\Utils\Services\Analytics\GoogleTagManager::getUserData();
    $userDataForGA = array_diff_key($userData, ['userIdRaw' => '', 'environment' => '', 'timestamp' => '']);
@endphp
<script>
  // Set GA4 user properties
  gtag('set', 'user_properties', {!! json_encode($userDataForGA, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!});

  @if(session()->has('gtm_login_event'))
  // Track login event
  gtag('event', 'login', {!! json_encode(session()->pull('gtm_login_event'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!});
  @endif

  @if(session()->has('gtm_pending_events'))
  // Track pending business events (inscriptions, camps, etc.)
  @foreach(session()->pull('gtm_pending_events', []) as $pendingEvent)
  gtag('event', '{{ $pendingEvent['event'] }}', {!! json_encode($pendingEvent['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!});
  @endforeach
  @endif
</script>
@endauth

@if(session()->has('gtm_logout_event'))
<script>
  // Track logout event (before user session is destroyed)
  gtag('event', 'logout', {!! json_encode(session()->pull('gtm_logout_event'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!});
</script>
@endif
@endif
