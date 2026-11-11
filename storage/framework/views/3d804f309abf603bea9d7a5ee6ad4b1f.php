<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening EZiCart</title>
    <script src="<?php echo e(asset('js/tab-sessions.js')); ?>?v=<?php echo e(filemtime(public_path('js/tab-sessions.js'))); ?>" data-tab-bootstrap="1" data-tab-target="<?php echo e($targetUrl); ?>" data-tab-login-url="<?php echo e(route('login')); ?>" defer></script>
</head>
<body>
    <p role="status">Opening your tab’s account…</p>
    <noscript>Enable JavaScript to use separate accounts in different tabs.</noscript>
</body>
</html>
<?php /**PATH C:\Users\Admin\Downloads\EZiCart\resources\views/auth/tab-bootstrap.blade.php ENDPATH**/ ?>