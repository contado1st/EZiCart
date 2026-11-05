<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('users')->get([
            'id', 'role', 'email', 'contact_no', 'shop_name', 'business_name',
            'canonical_company_name', 'tin', 'sec_registration_no', 'license_number', 'plate_number',
        ]);
        $valuesById = [];
        $seen = [];

        foreach ($rows as $row) {
            $phoneDigits = preg_replace('/\D/u', '', (string) $row->contact_no) ?? '';
            if (str_starts_with($phoneDigits, '63') && strlen($phoneDigits) === 12) {
                $phoneDigits = '0'.substr($phoneDigits, 2);
            } elseif (strlen($phoneDigits) === 10 && str_starts_with($phoneDigits, '9')) {
                $phoneDigits = '0'.$phoneDigits;
            }
            $mobile = preg_match('/^09\d{9}$/', $phoneDigits) ? $phoneDigits : null;
            $canonicalCompany = $row->role === 'sorting_center'
                ? Str::lower(preg_replace('/\s+/u', ' ', trim((string) ($row->business_name ?: $row->canonical_company_name))))
                : null;
            $values = [
                'canonical_email' => Str::lower(trim((string) $row->email)),
                'canonical_mobile' => $mobile,
                'canonical_shop_name' => filled($row->shop_name) ? Str::lower(preg_replace('/\s+/u', ' ', trim((string) $row->shop_name))) : null,
                'canonical_company_name' => filled($canonicalCompany) ? $canonicalCompany : null,
                'canonical_tin' => preg_match('/^\d{3}-\d{3}-\d{3}-\d{3}$/', (string) $row->tin)
                    ? preg_replace('/\D/u', '', (string) $row->tin)
                    : null,
                'canonical_sec_registration_no' => preg_match('/^[A-Za-z]{2}\d{9}$/', (string) $row->sec_registration_no)
                    ? Str::upper(trim((string) $row->sec_registration_no))
                    : null,
                'canonical_license_number' => preg_match('/^[A-Za-z]\d{2}-\d{2}-\d{6}$/', (string) $row->license_number)
                    ? Str::upper(trim((string) $row->license_number))
                    : null,
                'canonical_plate' => filled($row->plate_number)
                    ? Str::upper(preg_replace('/[\s-]+/u', '', trim((string) $row->plate_number)))
                    : null,
            ];

            foreach ($values as $column => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if (isset($seen[$column][$value])) {
                    throw new RuntimeException("Canonical duplicate values exist in {$column} for user IDs {$seen[$column][$value]} and {$row->id}. Resolve the duplicate records before rerunning this migration.");
                }
                $seen[$column][$value] = $row->id;
            }

            $valuesById[$row->id] = $values;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('canonical_shop_name', 30)->nullable()->unique();
            $table->string('canonical_tin', 12)->nullable()->unique();
            $table->string('canonical_sec_registration_no', 11)->nullable()->unique();
            $table->string('canonical_license_number', 16)->nullable()->unique();
        });

        foreach ($valuesById as $id => $values) {
            DB::table('users')->where('id', $id)->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['canonical_shop_name']);
            $table->dropUnique(['canonical_tin']);
            $table->dropUnique(['canonical_sec_registration_no']);
            $table->dropUnique(['canonical_license_number']);
            $table->dropColumn([
                'canonical_shop_name',
                'canonical_tin',
                'canonical_sec_registration_no',
                'canonical_license_number',
            ]);
        });
    }
};
