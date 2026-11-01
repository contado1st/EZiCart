<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdministrativeAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'users:create-administrator {email} {--role=ecommerce_admin}';

    protected $description = 'Provision a marketplace or logistics administrator with an interactive password';

    public function handle(AdministrativeAuditService $audits): int
    {
        $data = ['email' => mb_strtolower(trim($this->argument('email'))), 'role' => $this->option('role'), 'password' => $this->secret('Password (at least 12 characters)')];
        $validator = Validator::make($data, ['email' => ['required', 'email', 'max:254', 'unique:users,email'], 'role' => ['required', 'in:ecommerce_admin,logistics_admin'], 'password' => ['required', 'string', 'min:12', 'max:128']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        DB::transaction(function () use ($data, $audits): void {
            $user = User::query()->forceCreate([...$data, 'canonical_email' => $data['email'], 'first_name' => 'System', 'last_name' => 'Administrator', 'contact_no' => '', 'status' => 'approved', 'email_verified_at' => now()]);
            $audits->record(null, $user, 'administrator_provisioned', [], ['role' => $user->role], 'Provisioned by an authorized server operator through CLI.');
        });
        $this->info('Administrator provisioned.');

        return self::SUCCESS;
    }
}
