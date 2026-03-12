<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="">

        <title><?php echo e(config('app.name', 'Aston Hill')); ?></title>

        <!-- Favicon: use app icon for all routes (SPA and Blade). Absolute URL so path-based requests always resolve. -->
        <link rel="icon" type="image/x-icon" href="<?php echo e(asset('favicon.ico')); ?>">
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo e(asset('favicon.ico')); ?>">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    </head>
    <body class="font-sans antialiased">
        <div id="app">
            <?php if(request()->is('login')): ?>
                <div id="boot-fallback" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f4f6;padding:16px;">
                    <div style="width:100%;max-width:420px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -4px rgba(0,0,0,.1);padding:24px;">
                        <h1 style="margin:0 0 8px 0;font-size:22px;font-weight:600;color:#111827;">Sign in</h1>
                        <p style="margin:0 0 16px 0;font-size:14px;color:#4b5563;">Fallback login is shown because app initialization is delayed.</p>
                        <?php if(session('status')): ?>
                            <div style="margin-bottom:12px;padding:10px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:8px;color:#1d4ed8;font-size:13px;">
                                <?php echo e(session('status')); ?>

                            </div>
                        <?php endif; ?>
                        <?php if(isset($errors) && $errors->any()): ?>
                            <div style="margin-bottom:12px;padding:10px;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#b91c1c;font-size:13px;">
                                <?php echo e($errors->first()); ?>

                            </div>
                        <?php endif; ?>
                        <form method="POST" action="<?php echo e(route('login')); ?>">
                            <?php echo csrf_field(); ?>
                            <label for="fallback_email" style="display:block;margin-bottom:6px;font-size:13px;color:#374151;">Email</label>
                            <input id="fallback_email" type="email" name="email" value="<?php echo e(old('email')); ?>" required autocomplete="username" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;margin-bottom:12px;outline:none;" />
                            <label for="fallback_password" style="display:block;margin-bottom:6px;font-size:13px;color:#374151;">Password</label>
                            <input id="fallback_password" type="password" name="password" required autocomplete="current-password" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;margin-bottom:16px;outline:none;" />
                            <button type="submit" style="width:100%;border:0;border-radius:8px;padding:10px 12px;background:#16a34a;color:#fff;font-weight:600;cursor:pointer;">
                                Sign in
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

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
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''),
            'Accept': 'application/json'
        }
    }).then(() => {
        location.reload();
    });
}
</script>


</body>
</html><?php /**PATH C:\Users\yousa\aston-hill-crm\resources\views/layouts/app.blade.php ENDPATH**/ ?>