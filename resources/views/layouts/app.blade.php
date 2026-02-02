<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Aston Hill') }}</title>

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

@if(auth()->check())
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Echo) return;

    window.Echo.private(`App.Models.User.{{ auth()->id() }}`)
        .notification((notification) => {

            const badge = document.getElementById('notif-count');
            if (badge) {
                badge.style.display = 'inline-block';
                badge.innerText = (parseInt(badge.innerText || 0) + 1);
            }

            console.log('New notification:', notification);
        });
});
</script>
@endif

</body>
</html>