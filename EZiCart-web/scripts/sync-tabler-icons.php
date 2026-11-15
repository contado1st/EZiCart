<?php

$root = dirname(__DIR__);
$icons = require $root.'/config/icons.php';
$source = $root.'/node_modules/@tabler/icons';
$destination = $root.'/resources/icons/tabler';

if (! is_dir($destination)) {
    mkdir($destination, 0755, true);
}

foreach (array_unique(array_values($icons)) as $icon) {
    if (! preg_match('/\A[a-z0-9-]+\z/', $icon) || ! is_file($source.'/icons/outline/'.$icon.'.svg')) {
        throw new RuntimeException('Tabler outline icon is missing: '.$icon);
    }
    if (! copy($source.'/icons/outline/'.$icon.'.svg', $destination.'/'.$icon.'.svg')) {
        throw new RuntimeException('Cannot copy Tabler icon: '.$icon);
    }
}
if (! copy($source.'/LICENSE', $destination.'/LICENSE')) {
    throw new RuntimeException('Cannot copy Tabler license.');
}

echo 'Synced '.count(array_unique(array_values($icons)))." Tabler outline icons and license.\n";
