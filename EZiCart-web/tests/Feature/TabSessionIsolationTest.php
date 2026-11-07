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

        $response->assertOk()->assertViewIs('auth.tab-bootstrap')
            ->assertSee('filter=products')->assertDontSee('The page should not render yet.');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_domain_bootstrap_loads_its_session_script_from_the_current_site_even_with_a_local_asset_override(): void
    {
        app('url')->useAssetOrigin('http://localhost:8000');
        Route::middleware('web')->get('/__test/domain-bootstrap', fn () => response('Actual page'));

        $this->withHeaders(['Accept' => 'text/html', 'Sec-Fetch-Dest' => 'document'])
            ->get('https://shop.ezicart.trade/__test/domain-bootstrap')
            ->assertOk()->assertViewIs('auth.tab-bootstrap')
            ->assertSee('src="/js/tab-sessions.js?v=', false)
            ->assertDontSee('http://localhost:8000', false);
    }

    public function test_trusted_tunnel_preserves_https_in_bootstrap_targets_and_session_redirects(): void
    {
        config(['trustedproxy.proxies' => ['127.0.0.1']]);
        Route::middleware('web')->get('/__test/tunnel-bootstrap', fn () => response('Actual page'));
        Route::middleware('web')->get('/__test/tunnel-redirect', fn () => redirect()->route('login'));
        $headers = ['Accept' => 'text/html', 'Sec-Fetch-Dest' => 'document', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'];

        $this->withHeaders($headers)->get('http://shop.ezicart.trade/__test/tunnel-bootstrap')
            ->assertOk()->assertSee('data-tab-target="https://shop.ezicart.trade/__test/tunnel-bootstrap"', false)
            ->assertSee('data-tab-login-url="https://shop.ezicart.trade/login"', false);
        $token = str_repeat('e', 32);
        $this->get('http://shop.ezicart.trade/__test/tunnel-redirect?__tab='.$token)
            ->assertRedirect('https://shop.ezicart.trade/login?__tab='.$token);
    }

    public function test_untrusted_clients_cannot_change_the_session_origin_with_forwarded_headers(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        Route::middleware('web')->get('/__test/untrusted-bootstrap', fn () => response('Actual page'));

        $this->withHeaders(['Accept' => 'text/html', 'Sec-Fetch-Dest' => 'document', 'X-Forwarded-Proto' => 'https'])
            ->get('http://shop.ezicart.trade/__test/untrusted-bootstrap')
            ->assertOk()->assertSee('data-tab-target="http://shop.ezicart.trade/__test/untrusted-bootstrap"', false);
    }
}
