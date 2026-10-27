<?php

namespace Tests\Feature;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TabSessionIsolationTest extends TestCase
{
    private string $sessionDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionDirectory = storage_path('framework/testing/tab-sessions-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->sessionDirectory);
        config(['session.driver' => 'file', 'session.files' => $this->sessionDirectory]);
        app(SessionManager::class)->forgetDrivers();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionDirectory);

        parent::tearDown();
    }

    public function test_each_tab_token_uses_an_independent_session_cookie_and_session_data(): void
    {
        Route::middleware('web')->get('/__test/tab-session', function (Request $request) {
            if ($request->query('value')) {
                session()->put('tab-test-value', $request->query('value'));
            }

            return response()->json(['value' => session('tab-test-value')]);
        });

        $firstTab = str_repeat('a', 32);
        $secondTab = str_repeat('b', 32);
        $firstCookie = config('session.cookie').'-tab-'.substr(hash('sha256', $firstTab), 0, 24);
        $secondCookie = config('session.cookie').'-tab-'.substr(hash('sha256', $secondTab), 0, 24);

        $first = $this->get('/__test/tab-session?__tab='.$firstTab.'&value=buyer');
        $first->assertOk()->assertJsonPath('value', 'buyer');
        $firstSessionCookie = collect($first->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $firstCookie);
        $this->assertNotNull($firstSessionCookie);

        $second = $this->get('/__test/tab-session?__tab='.$secondTab.'&value=seller');
        $second->assertOk()->assertJsonPath('value', 'seller');
        $secondSessionCookie = collect($second->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $secondCookie);
        $this->assertNotNull($secondSessionCookie);

        $firstSessionId = CookieValuePrefix::remove(decrypt($firstSessionCookie->getValue(), false));
        $secondSessionId = CookieValuePrefix::remove(decrypt($secondSessionCookie->getValue(), false));

        $this->withCookie($firstCookie, $firstSessionId)
            ->get('/__test/tab-session?__tab='.$firstTab)
            ->assertOk()
            ->assertJsonPath('value', 'buyer');
        $this->withCookie($secondCookie, $secondSessionId)
            ->get('/__test/tab-session?__tab='.$secondTab)
            ->assertOk()
            ->assertJsonPath('value', 'seller');
    }

    public function test_same_site_redirects_keep_the_active_tab_token(): void
    {
        $token = str_repeat('c', 32);
        Route::middleware('web')->post('/__test/tab-redirect', fn () => redirect('/__test/tab-session'));

        $response = $this->post('/__test/tab-redirect?__tab='.$token);

        $response->assertRedirect();
        $this->assertStringContainsString('__tab='.$token, (string) $response->headers->get('Location'));
    }

    public function test_unscoped_html_navigation_receives_a_fresh_tab_token_before_rendering(): void
    {
        Route::middleware('web')->get('/__test/tab-bootstrap', fn () => response('The page should not render yet.', 200));

        $response = $this->withHeaders([
            'Accept' => 'text/html,application/xhtml+xml',
            'Sec-Fetch-Dest' => 'document',
        ])
            ->get('/__test/tab-bootstrap?filter=products');

        $response->assertRedirect();
        $this->assertMatchesRegularExpression('/__tab=[a-f0-9]{32}/', (string) $response->headers->get('Location'));
        $this->assertStringContainsString('filter=products', (string) $response->headers->get('Location'));
    }
}
