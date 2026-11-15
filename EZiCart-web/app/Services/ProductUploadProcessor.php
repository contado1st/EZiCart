<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ProductUploadProcessor
{
    public function image(UploadedFile $file, string $directory, string $disk = 'public', string $field = 'image'): string
    {
        $size = @getimagesize($file->getPathname());
        if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $size[0] * $size[1] > 12000000) {
            throw ValidationException::withMessages([$field => 'Choose a valid JPEG, PNG or WebP image up to 12 megapixels.']);
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
            $scale = min(1, 1280 / imagesx($source), 720 / imagesy($source));
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
                    throw new \RuntimeException('The product image could not be optimized.');
                }
                rewind($stream);
                $path = $directory.'/'.Str::uuid().'.'.$extension;
                if (! Storage::disk($disk)->put($path, $stream)) {
                    throw new \RuntimeException('The optimized product image could not be saved.');
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

    public function binary(string $name): string
    {
        $configured = config('product-media.'.$name.'_path');
        $found = $configured ?: (new ExecutableFinder)->find($name);
        // A user-level WinGet install can be newer than the PHP service's inherited PATH.
        if (! $found && PHP_OS_FAMILY === 'Windows') {
            $candidate = getenv('LOCALAPPDATA').'/Microsoft/WinGet/Links/'.$name.'.exe';
            $found = is_file($candidate) ? $candidate : null;
        }
        if (! $found) {
            throw ValidationException::withMessages(['product_video' => 'Video processing is unavailable. Contact administration or save without a video.']);
        }

        return $found;
    }

    public function probe(string $path): array
    {
        $process = new Process([$this->binary('ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe', '-show_entries', 'format=duration:stream=codec_type,width,height,duration', '-of', 'json', $path]);
        $process->setTimeout(15);
        try {
            $process->mustRun();
            $data = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['product_video' => 'This video could not be verified. Upload a valid MP4 or WebM file.']);
        }
        $videos = array_values(array_filter($data['streams'] ?? [], fn ($s) => ($s['codec_type'] ?? null) === 'video'));
        $durations = array_filter([$data['format']['duration'] ?? null, ...array_column($data['streams'] ?? [], 'duration')], 'is_numeric');
        $duration = $durations ? max(array_map('floatval', $durations)) : 0;
        if (count($videos) !== 1 || $duration <= 0 || $duration > 60 || ($videos[0]['width'] ?? 0) * ($videos[0]['height'] ?? 0) > 16777216) {
            throw ValidationException::withMessages(['product_video' => 'Choose a video with one video stream, a duration up to 60 seconds and supported dimensions.']);
        }

        return $data;
    }

    public function video(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $this->probe($file->getPathname());
        $temporary = tempnam(storage_path('app'), 'product-video-');
        try {
            $process = new Process([$this->binary('ffmpeg'), '-nostdin', '-v', 'error', '-y', '-threads', '2', '-protocol_whitelist', 'file,pipe', '-i', $file->getPathname(), '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-vf', "scale=w='min(1280,iw)':h='min(720,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1", '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast', '-crf', '24', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', '-f', 'mp4', $temporary]);
            $process->setTimeout(120);
            try {
                $process->mustRun();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['product_video' => 'The video could not be optimized. Choose a shorter or simpler video.']);
            }
            $this->probe($temporary);
            if (filesize($temporary) > config('product-media.video_max_kb') * 1024) {
                throw ValidationException::withMessages(['product_video' => 'The optimized video is too large. Choose a simpler video.']);
            }
            $path = $directory.'/'.Str::uuid().'.mp4';
            $stream = fopen($temporary, 'rb');
            try {
                if (! Storage::disk($disk)->put($path, $stream)) {
                    throw new \RuntimeException('The product video could not be saved.');
                }
            } finally {
                fclose($stream);
            }

            return $path;
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
