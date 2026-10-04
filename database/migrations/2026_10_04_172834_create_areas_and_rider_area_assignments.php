<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('area_municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('province');
            $table->string('municipality');
            $table->string('province_normalized', 191);
            $table->string('municipality_normalized', 191);
            $table->timestamps();
            $table->unique(['province_normalized', 'municipality_normalized']);
        });

        Schema::create('area_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'area_id']);
            $table->index(['user_id', 'is_active', 'is_primary']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('destination_area_id')->nullable()->after('delivery_area')->constrained('areas')->restrictOnDelete();
        });

        $normalize = static fn (?string $value): string => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $value)));
        $addressRows = DB::table('orders')->select('province', 'municipality')->whereNotNull('municipality')->distinct()->get()
            ->merge(DB::table('users')->select('province', 'assigned_area as municipality')->whereNotNull('assigned_area')->distinct()->get());

        foreach ($addressRows as $address) {
            $province = trim((string) $address->province);
            $municipality = trim((string) $address->municipality);
            if ($municipality === '') {
                continue;
            }

            $provinceNormalized = $normalize($province);
            $municipalityNormalized = $normalize($municipality);
            $mapping = DB::table('area_municipalities')
                ->where('province_normalized', $provinceNormalized)
                ->where('municipality_normalized', $municipalityNormalized)
                ->first();

            if ($mapping !== null) {
                continue;
            }

            $baseCode = Str::slug(($province !== '' ? $province.'-' : '').$municipality) ?: 'area';
            $code = substr($baseCode, 0, 180).'-'.substr(sha1($provinceNormalized.'|'.$municipalityNormalized), 0, 12);
            $areaId = DB::table('areas')->insertGetId([
                'name' => $municipality,
                'code' => $code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('area_municipalities')->insert([
                'area_id' => $areaId,
                'province' => $province,
                'municipality' => $municipality,
                'province_normalized' => $provinceNormalized,
                'municipality_normalized' => $municipalityNormalized,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($province !== '') {
                $riders = DB::table('users')->where('role', 'courier')->where('assigned_area', $municipality)->pluck('id');
                foreach ($riders as $riderId) {
                    DB::table('area_user')->insertOrIgnore([
                        'user_id' => $riderId,
                        'area_id' => $areaId,
                        'is_primary' => true,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        foreach (DB::table('orders')->select('id', 'province', 'municipality')->whereNotNull('municipality')->get() as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'destination_area_id' => DB::table('area_municipalities')
                    ->where('province_normalized', $normalize($order->province))
                    ->where('municipality_normalized', $normalize($order->municipality))
                    ->value('area_id'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_area_id');
        });
        Schema::dropIfExists('area_user');
        Schema::dropIfExists('area_municipalities');
        Schema::dropIfExists('areas');
    }
};
