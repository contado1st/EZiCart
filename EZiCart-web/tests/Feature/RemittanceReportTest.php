<?php

namespace Tests\Feature;

use App\Models\RemittanceBatch;
use App\Models\User;
use App\Services\RemittanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;
use ZipArchive;

class RemittanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $hub;

    private User $otherHub;

    private User $rider;

    private User $otherRider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(12, 0));
        $this->hub = $this->user('sorting_center');
        $this->otherHub = $this->user('sorting_center');
        $this->rider = $this->user('courier');
        $this->otherRider = $this->user('courier');
    }

    public function test_screen_and_both_exports_share_filtered_totals_across_all_pages(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->batch(['reference' => 'OWN-'.$i]);
        }
        $this->batch(['hub_id' => $this->otherHub->id, 'reference' => 'FOREIGN-PRIVATE']);
        $this->batch(['submitted_at' => today()->subSecond(), 'reference' => 'PREVIOUS-DAY']);
        $this->batch(['submitted_at' => today()->addDay(), 'reference' => 'NEXT-MIDNIGHT']);
        $this->batch(['status' => 'VERIFIED', 'reference' => 'WRONG-STATUS']);
        $filters = ['from' => '2026-11-14', 'to' => '2026-11-14', 'status' => 'SUBMITTED', 'rider_id' => $this->rider->id, 'hub_id' => $this->hub->id];
        $screen = $this->signIn($this->hub)->get(route('finance.batches', $filters))->assertOk()->assertSee('Download Excel')->assertDontSee('FOREIGN-PRIVATE');
        $this->assertSame(27, $screen->viewData('batches')->total());
        $this->assertCount(25, $screen->viewData('batches')->items());
        $this->assertEquals(405000, $screen->viewData('totals')->expected);
        $report = app(RemittanceReportService::class)->report($this->hub, $filters);
        $this->assertSame(['expected' => 405000, 'submitted' => 405000, 'received' => 0, 'discrepancy' => 0, 'not_counted' => 27], $report['totals']);
        $csv = $this->get(route('finance.statements', ['type' => 'remittances', ...$filters]))->assertOk()->assertDownload()->streamedContent();
        $rows = $this->csv($csv);
        $this->assertSame(RemittanceReportService::HEADERS, $rows[6]);
        $this->assertSame('Not available', $rows[7][5]);
        $this->assertSame('150.00', $rows[7][3]);
        $this->assertStringNotContainsString('FOREIGN-PRIVATE', $csv);
        $sheets = $this->workbook($this->get(route('finance.statements', ['type' => 'remittances', ...$filters, 'format' => 'xlsx']))->assertOk()->assertDownload()->streamedContent());
        $this->assertSame(34, substr_count($sheets['details'], '<row '));
        $this->assertStringContainsString('<v>4050</v>', $sheets['summary']);
        $this->assertStringNotContainsString('FOREIGN-PRIVATE', implode('', $sheets));
    }

    #[TestWith(['courier'])]
    #[TestWith(['sorting_center'])]
    #[TestWith(['logistics_admin'])]
    public function test_role_scope_precedes_rider_and_hub_filters(string $role): void
    {
        $this->batch(['reference' => 'OWN-ROW']);
        $this->batch(['reference' => 'OTHER-RIDER', 'rider_id' => $this->otherRider->id]);
        $this->batch(['reference' => 'OTHER-HUB', 'hub_id' => $this->otherHub->id, 'rider_id' => $this->otherRider->id]);
        $actor = match ($role) {
            'courier' => $this->rider, 'sorting_center' => $this->hub, default => $this->user('logistics_admin')
        };
        $response = $this->signIn($actor)->get(route('finance.batches'))->assertOk();
        $this->assertSame(match ($role) {
            'courier' => 1, 'sorting_center' => 2, default => 3
        }, $response->viewData('batches')->total());
        $filters = ['hub_id' => $this->otherHub->id, 'rider_id' => $this->otherRider->id];
        $this->assertSame($role === 'logistics_admin' ? 1 : 0, app(RemittanceReportService::class)->report($actor, $filters)['count']);
        $body = $this->get(route('finance.statements', ['type' => 'remittances', ...$filters]))->assertOk()->streamedContent();
        $role === 'logistics_admin' ? $this->assertStringContainsString('OTHER-HUB', $body) : $this->assertStringNotContainsString('OTHER-HUB', $body);
    }

    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    #[TestWith(['admin'])]
    #[TestWith(['ecommerce_admin'])]
    public function test_unrelated_roles_cannot_read_screen_or_exports(string $role): void
    {
        $this->signIn($this->user($role))->get(route('finance.batches'))->assertForbidden();
        foreach (['csv', 'xlsx'] as $format) {
            $this->get(route('finance.statements', ['type' => 'remittances', 'format' => $format]))->assertForbidden();
        }
    }

    public function test_empty_exports_keep_headings_filters_zero_totals_and_no_fictional_batch(): void
    {
        $this->signIn($this->hub);
        $csv = $this->get(route('finance.statements', ['type' => 'remittances', 'from' => '2026-11-01']))->assertOk()->streamedContent();
        $rows = $this->csv($csv);
        $this->assertStringContainsString('No matching remittance batches', $rows[4][0]);
        $this->assertSame(RemittanceReportService::HEADERS, $rows[6]);
        $this->assertSame(array_fill(0, 9, ''), $rows[7]);
        $this->assertSame('Expected cash', $rows[10][0]);
        $this->assertSame('0.00', $rows[10][1]);
        $this->assertStringContainsString('2026-11-01', $csv);
        $sheets = $this->workbook($this->get(route('finance.statements', ['type' => 'remittances', 'format' => 'xlsx']))->assertOk()->streamedContent());
        $this->assertSame(7, substr_count($sheets['details'], '<row '));
        $this->assertStringContainsString('No matching remittance batches', $sheets['details']);
        $this->assertStringContainsString('<v>0</v>', $sheets['summary']);
        $this->assertStringContainsString('ySplit="7"', $sheets['details']);
        $this->assertStringContainsString('<autoFilter ref="A7:I7"', $sheets['details']);
    }

    public function test_formula_like_and_numeric_references_are_safe_text_and_evidence_paths_are_private(): void
    {
        foreach (['=SUM(1,2)', '+123', '@SUM(A1)', '0000123', '1234567890123456789'] as $reference) {
            $this->batch(['reference' => $reference, 'evidence_path' => 'private/original-sensitive-evidence.pdf']);
        }
        $this->signIn($this->hub);
        $csv = $this->get(route('finance.statements', ['type' => 'remittances']))->assertOk()->streamedContent();
        $rows = $this->csv($csv);
        $this->assertSame("'=SUM(1,2)", $rows[7][0]);
        $this->assertSame("'0000123", $rows[10][0]);
        $this->assertSame("'1234567890123456789", $rows[11][0]);
        $sheets = $this->workbook($this->get(route('finance.statements', ['type' => 'remittances', 'format' => 'xlsx']))->assertOk()->streamedContent());
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">0000123</t>', $sheets['details']);
        $this->assertStringContainsString('=SUM(1,2)', $sheets['details']);
        $this->assertStringNotContainsString('<f>', $sheets['details']);
        $this->assertStringNotContainsString('original-sensitive-evidence', $csv.implode('', $sheets));
        $this->assertStringContainsString('PHP', $sheets['styles']);
    }

    public function test_missing_counts_and_signed_discrepancies_are_preserved_without_new_money(): void
    {
        $this->batch(['received_cents' => 14000, 'discrepancy_cents' => -1000, 'status' => 'DISCREPANCY']);
        $this->batch(['received_cents' => 16000, 'discrepancy_cents' => 1000, 'status' => 'DISCREPANCY']);
        $this->batch();
        $report = app(RemittanceReportService::class)->report($this->hub, []);
        $this->assertSame(0, $report['totals']['discrepancy']);
        $this->assertSame(30000, $report['totals']['received']);
        $this->assertSame(1, $report['totals']['not_counted']);
        $this->assertEquals(-10, $report['rows'][0][6]);
        $this->assertEquals(10, $report['rows'][1][6]);
        $this->assertNull($report['rows'][2][5]);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('administrative_audits', 0);
    }

    #[TestWith(['from', '2026-11-31'])]
    #[TestWith(['status', 'UNKNOWN'])]
    #[TestWith(['rider_id', -1])]
    #[TestWith(['hub_id', 'not-a-number'])]
    public function test_invalid_filters_fail_on_screen_and_exports(string $key, mixed $value): void
    {
        $this->signIn($this->hub)->getJson(route('finance.batches', [$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
        $this->getJson(route('finance.statements', ['type' => 'remittances', $key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public function test_invalid_range_format_and_guest_access_fail_before_streaming(): void
    {
        $this->get(route('finance.statements', 'remittances'))->assertRedirect();
        $this->signIn($this->hub)->getJson(route('finance.statements', ['type' => 'remittances', 'format' => 'pdf']))->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->getJson(route('finance.statements', ['type' => 'remittances', 'from' => '2026-11-14', 'to' => '2026-11-01']))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_oversized_statement_is_rejected_without_silently_truncating_rows(): void
    {
        $base = $this->batch()->getRawOriginal();
        unset($base['id']);
        $rows = [];
        for ($i = 0; $i < 10000; $i++) {
            $rows[] = ['reference' => 'LARGE-'.$i] + $base;
            if (count($rows) === 500) {
                DB::table('remittance_batches')->insert($rows);
                $rows = [];
            }
        }
        $this->signIn($this->hub)->getJson(route('finance.statements', ['type' => 'remittances', 'format' => 'xlsx']))->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->assertDatabaseCount('remittance_batches', 10001);
    }

    private function workbook(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'remittance-test-');
        file_put_contents($path, $content);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path));

            return ['summary' => $zip->getFromName('xl/worksheets/sheet1.xml'), 'details' => $zip->getFromName('xl/worksheets/sheet2.xml'), 'styles' => $zip->getFromName('xl/styles.xml')];
        } finally {
            $zip->close();
            unlink($path);
        }
    }

    private function csv(string $content): array
    {
        $file = fopen('php://memory', 'w+');
        fwrite($file, $content);
        rewind($file);
        $rows = [];
        while (($row = fgetcsv($file, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($file);

        return $rows;
    }

    private function batch(array $extra = []): RemittanceBatch
    {
        return RemittanceBatch::forceCreate($extra + ['reference' => 'REM-'.Str::random(10), 'rider_id' => $this->rider->id, 'hub_id' => $this->hub->id, 'submitted_by' => $this->rider->id,
            'expected_cents' => 15000, 'submitted_cents' => 15000, 'received_cents' => null, 'discrepancy_cents' => null, 'evidence_path' => 'private/synthetic.pdf', 'reason' => 'Synthetic reporting fixture', 'status' => 'SUBMITTED', 'submitted_at' => now()]);
    }

    private function signIn(User $user): static
    {
        $this->flushSession();
        Auth::forgetGuards();

        return $this->actingAs($user);
    }

    private function user(string $role): User
    {
        return User::forceCreate(['email' => Str::uuid().'@example.test', 'password' => 'Password123!', 'first_name' => 'Report', 'last_name' => 'Tester', 'role' => $role, 'status' => 'approved', 'email_verified_at' => now(), 'contact_no' => '09123456789', 'province' => 'Laguna', 'municipality' => 'Majayjay', 'barangay' => 'San Isidro']);
    }
}
