<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Aston Hill') }}</title>

        <!-- Favicon: use app icon for all routes (SPA and Blade). Absolute URL so path-based requests always resolve. -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div id="app"></div>

<script>
(function () {
  // create per-tab id
  if (!sessionStorage.getItem('tab_id')) {
    sessionStorage.setItem('tab_id', (crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random())));
  }

  const tabId = sessionStorage.getItem('tab_id');

  // store it in cookie too (for refresh/direct url fallback)
  document.cookie = "__tab=" + encodeURIComponent(tabId) + "; path=/; samesite=lax";

  // auto-append __tab to same-origin <a> links
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a');
    if (!a) return;

    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return;

    const url = new URL(a.href, window.location.href);
    if (url.origin !== window.location.origin) return;

    if (!url.searchParams.has('__tab')) {
      url.searchParams.set('__tab', tabId);
      a.href = url.toString();
    }
  }, true);
})();

function markNotificationRead(id) {
    fetch(`/notifications/${id}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    }).then(() => {
        location.reload();
    });
}
</script>
{{-- Echo/notification subscription moved to Vue (after /bootstrap) to avoid auth()->check() on every SPA document request. --}}

</body>
</html>