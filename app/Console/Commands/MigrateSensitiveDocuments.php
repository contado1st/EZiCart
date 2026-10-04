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
        $userDocumentFields = ['id_path', 'permit_path', 'license_path', 'or_cr_path', 'id_upload_path', 'business_permit_path', 'or_cr_upload_path'];
        $paths = User::query()->get($userDocumentFields)
            ->flatMap(fn (User $user): array => array_values($user->only($userDocumentFields)))
            ->filter(fn (?string $path): bool => filled($path))
            ->merge(Dispute::query()->whereNotNull('evidence_path')->pluck('evidence_path'))
            ->unique();

        foreach ($paths as $path) {
            if ($private->exists($path)) {
                if ($public->exists($path)) {
                    $public->delete($path);
                }

                continue;
            }

            if (! $public->exists($path)) {
                $this->warn("Missing legacy document: {$path}");

                continue;
            }

            $stream = $public->readStream($path);
            if (! is_resource($stream)) {
                $this->error("Unable to read legacy document: {$path}");

                continue;
            }

            $stored = $private->writeStream($path, $stream);
            fclose($stream);

            if (! $stored) {
                $this->error("Unable to write private document: {$path}");

                continue;
            }

            $public->delete($path);
            $migrated++;
        }

        $this->info("Migrated {$migrated} sensitive file(s) to private storage.");

        return self::SUCCESS;
    }
}
