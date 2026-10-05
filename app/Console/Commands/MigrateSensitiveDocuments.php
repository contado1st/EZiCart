<?php

namespace App\Console\Commands;

use App\Models\Dispute;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:migrate-sensitive-documents')]
#[Description('Move existing sensitive uploads from public storage to private storage')]
class MigrateSensitiveDocuments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('private');
        $migrated = 0;
        $failed = false;
        $userDocumentFields = ['id_path', 'permit_path', 'license_path', 'or_cr_path', 'id_upload_path', 'business_permit_path', 'or_cr_upload_path'];
        $sensitiveDirectories = [
            'documents/ids',
            'documents/permits',
            'documents/licenses',
            'documents/or_cr',
            'disputes/evidence',
        ];
        $paths = User::query()->get($userDocumentFields)
            ->flatMap(fn (User $user): array => array_values($user->only($userDocumentFields)))
            ->filter(fn (?string $path): bool => filled($path))
            ->merge(Dispute::query()->whereNotNull('evidence_path')->pluck('evidence_path'))
            ->merge(collect($sensitiveDirectories)->flatMap(fn (string $directory): array => $public->allFiles($directory)))
            ->unique();

        foreach ($paths as $path) {
            if ($private->exists($path)) {
                if ($public->exists($path)) {
                    $publicContents = $public->get($path);
                    $privateContents = $private->get($path);

                    if (! is_string($publicContents)
                        || ! is_string($privateContents)
                        || ! hash_equals(hash('sha256', $publicContents), hash('sha256', $privateContents))) {
                        $this->error("Private document already exists with different contents: {$path}");
                        $failed = true;

                        continue;
                    }

                    if (! $public->delete($path)) {
                        $this->error("Unable to remove public document: {$path}");
                        $failed = true;

                        continue;
                    }

                    $migrated++;
                }

                continue;
            }

            if (! $public->exists($path)) {
                $this->warn("Missing legacy document: {$path}");
                $failed = true;

                continue;
            }

            $stream = $public->readStream($path);
            if (! is_resource($stream)) {
                $this->error("Unable to read legacy document: {$path}");
                $failed = true;

                continue;
            }

            $stored = $private->writeStream($path, $stream);
            fclose($stream);

            if (! $stored) {
                $this->error("Unable to write private document: {$path}");
                $failed = true;

                continue;
            }

            if (! $public->delete($path)) {
                $this->error("Unable to remove public document: {$path}");
                $failed = true;

                continue;
            }

            $migrated++;
        }

        $this->info("Migrated {$migrated} sensitive file(s) to private storage.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
