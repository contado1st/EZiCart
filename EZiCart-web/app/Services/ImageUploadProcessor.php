<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageUploadProcessor
{
    public function image(UploadedFile $file, string $directory, string $disk = 'public', string $field = 'image', int $maxWidth = 1280, int $maxHeight = 720, int $maxPixels = 12000000): string
    {
        $size = @getimagesize($file->getPathname());
        if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $maxPixels < $size[0] * $size[1]) {
            throw ValidationException::withMessages([$field => 'Choose a valid JPEG, PNG or WebP image up to '.round($maxPixels / 1000000, 1).' megapixels.']);
        }
        $source = @imagecreatefromstring(file_get_contents($file->getPathname()));
        if (! $source) {
            throw ValidationException::withMessages([$field => 'This image could not be decoded. Choose another image.']);
        }
        $target = null;
        try {
            if ($size[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $orientation = @exif_read_data($file->getPathname())['Orientation'] ?? 1;
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($source, in_array($orientation, [2, 5, 7], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
                }
                $degrees = match ($orientation) {
                    3 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
                };
                if ($degrees) {
                    $rotated = imagerotate($source, $degrees, 0);
                    imagedestroy($source);
                    $source = $rotated;
                }
            }
            $scale = min(1, $maxWidth / imagesx($source), $maxHeight / imagesy($source));
            $width = max(1, (int) floor(imagesx($source) * $scale));
            $height = max(1, (int) floor(imagesy($source) * $scale));
            $target = imagecreatetruecolor($width, $height);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
            $stream = fopen('php://temp', 'w+b');
            try {
                $extension = match ($size[2]) {
                    IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => 'jpg'
                };
                $ok = match ($extension) {
                    'png' => imagepng($target, $stream, 9), 'webp' => imagewebp($target, $stream, 82), default => imagejpeg($target, $stream, 85)
                };
                if (! $ok) {
                    throw new \RuntimeException('The image could not be optimized.');
                }
                rewind($stream);
                $path = $directory.'/'.Str::uuid().'.'.$extension;
                if (! Storage::disk($disk)->put($path, $stream)) {
                    throw new \RuntimeException('The optimized image could not be saved.');
                }

                return $path;
            } finally {
                fclose($stream);
            }
        } finally {
            imagedestroy($source);
            if ($target) {
                imagedestroy($target);
            }
        }
    }
}
