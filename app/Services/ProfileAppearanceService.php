<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProfileAppearanceService
{
    public const THEMES = ['rose' => 'Rose', 'wine' => 'Wine', 'navy' => 'Navy', 'teal' => 'Teal'];

    public function update(User $actor, array $data): void
    {
        $newFiles = [];
        try {
            foreach (['avatar' => [400, 400], 'banner' => [1600, 500]] as $type => [$width, $height]) {
                if (($data[$type] ?? null) instanceof UploadedFile) {
                    $path = 'profile-media/'.$actor->id.'/'.Str::uuid().'.jpg';
                    $newFiles[$type] = $path;
                    if (! Storage::disk('private')->put($path, $this->jpeg($data[$type], $width, $height, $type))) {
                        throw new RuntimeException('The profile image could not be stored.');
                    }
                }
            }
            DB::transaction(function () use ($actor, $data, $newFiles): void {
                $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $attributes = ['profile_display_name' => $data['profile_display_name'] ?? null,
                    'profile_bio' => $data['profile_bio'] ?? null, 'profile_theme' => $data['profile_theme']];
                $oldFiles = [];
                foreach (['avatar', 'banner'] as $type) {
                    $column = 'profile_'.$type.'_path';
                    if (isset($newFiles[$type]) || ! empty($data['remove_'.$type])) {
                        if ($user->$column) {
                            $oldFiles[] = $user->$column;
                        }
                        $attributes[$column] = $newFiles[$type] ?? null;
                    }
                }
                $user->forceFill($attributes)->save();
                if ($oldFiles) {
                    DB::afterCommit(function () use ($oldFiles): void {
                        try {
                            Storage::disk('private')->delete($oldFiles);
                        } catch (Throwable $exception) {
                            report($exception);
                        }
                    });
                }
            }, 3);
        } catch (Throwable $exception) {
            foreach ($newFiles as $path) {
                Storage::disk('private')->delete($path);
            }
            throw $exception;
        }
    }

    private function jpeg(UploadedFile $file, int $width, int $height, string $field): string
    {
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages([$field => 'This image could not be read. Choose a JPEG, PNG or WebP image.']);
        }
        $target = null;
        try {
            if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
                $orientation = (@exif_read_data($file->getRealPath()) ?: [])['Orientation'] ?? 1;
                $rotation = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
                if ($rotation && ($rotated = imagerotate($source, $rotation, 0))) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }
            $target = imagecreatetruecolor($width, $height);
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            $scale = max($width / imagesx($source), $height / imagesy($source));
            $cropWidth = (int) round($width / $scale);
            $cropHeight = (int) round($height / $scale);
            imagecopyresampled($target, $source, 0, 0, (int) ((imagesx($source) - $cropWidth) / 2), (int) ((imagesy($source) - $cropHeight) / 2), $width, $height, $cropWidth, $cropHeight);
            ob_start();
            try {
                if (! imagejpeg($target, null, 88)) {
                    throw new RuntimeException('Image conversion failed.');
                }

                $bytes = ob_get_contents();
                if ($bytes === false) {
                    throw new RuntimeException('Image conversion produced no data.');
                }

                return $bytes;
            } finally {
                ob_end_clean();
            }
        } finally {
            imagedestroy($source);
            if ($target) {
                imagedestroy($target);
            }
        }
    }
}
