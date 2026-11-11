<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$css = 'file:///C:/Users/Admin/Downloads/EZiCart/public/css/loading-skeleton.css';
foreach (['product', 'announcements', 'search', 'thread'] as $variant) {
    $shapes = Illuminate\Support\Facades\Blade::render('<x-loading-skeleton variant="'.$variant.'" />');
    $document = '<!doctype html><html class="ezi-loading-enabled"><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="'.$css.'"><style>body{margin:0;font:14px Arial,sans-serif;background:#f8f9fc}.preview{padding:24px;box-sizing:border-box}</style></head><body><main class="preview" data-loading-stage><div class="ezi-skeleton-visible">'.$shapes.'</div></main></body></html>';
    file_put_contents(__DIR__.'/skeleton-'.$variant.'.html', $document);
    file_put_contents(__DIR__.'/skeleton-review-'.$variant.'.html', '<!doctype html><html><head><title>EZiCart skeleton layout review</title><style>body{margin:20px;font:14px Arial,sans-serif;color:#202634}.previews{display:flex;gap:20px;align-items:flex-start}iframe{height:650px;border:1px solid #dce0e8}nav a{margin-right:18px}</style></head><body><h1>EZiCart · '.$variant.' skeleton</h1><nav><a href="skeleton-review-product.html">Product</a><a href="skeleton-review-announcements.html">Announcements</a><a href="skeleton-review-search.html">Search</a><a href="skeleton-review-thread.html">Conversation</a></nav><div class="previews"><div><h2>Desktop · 950px</h2><iframe title="Desktop skeleton" width="950" src="skeleton-'.$variant.'.html"></iframe></div><div><h2>Mobile · 390px</h2><iframe title="Mobile skeleton" width="390" src="skeleton-'.$variant.'.html"></iframe></div></div></body></html>');
}
echo "Synthetic skeleton preview generated.\n";
