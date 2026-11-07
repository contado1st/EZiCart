<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneStaleUnverifiedAccounts extends Command
{
    protected $signature = 'accounts:prune-unverified';

    protected $description = 'Delete expired unverified registrations and their unreferenced private documents';

    public function handle(): int
    {
        $hours = max(1, (int) config('registration.unverified_retention_hours', 24));
        $cutoff = now()->subHours($hours);
        $deleted = 0;

        User::query()
            ->where('status', 'unverified')
            ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$deleted, $cutoff): void {
                foreach ($users as $user) {
                    $paths = DB::transaction(function () use ($user, $cutoff): array {
                        $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
                        if (! $lockedUser || $lockedUser->status !== 'unverified' || $lockedUser->created_at->gt($cutoff)) {
                            return [];
                        }

                        $paths = collect([
                            $lockedUser->id_path,
                            $lockedUser->permit_path,
                            $lockedUser->bir_certificate_path,
                            $lockedUser->sec_certificate_path,
                        ])->filter()->unique()->values()->all();

                        $lockedUser->delete();

                        return $paths;
                    });

                    foreach ($paths as $path) {
                        $stillReferenced = User::query()->where(function ($query) use ($path): void {
                            foreach (['id_path', 'permit_path', 'bir_certificate_path', 'sec_certificate_path'] as $column) {
                                $query->orWhere($column, $path);
                            }
                        })->exists();

                        if (! $stillReferenced) {
                            Storage::disk('private')->delete($path);
                        }
                    }

                    if ($paths !== [] || ! User::query()->whereKey($user->id)->exists()) {
                        $deleted++;
                    }
                }
            });

        $this->info("Deleted {$deleted} stale unverified account(s) older than {$hours} hour(s).");

        return self::SUCCESS;
    }
}
