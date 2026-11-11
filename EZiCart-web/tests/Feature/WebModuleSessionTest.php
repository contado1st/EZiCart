<?php

namespace Tests\Feature;

use Illuminate\Auth\SessionGuard;

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebModuleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_six_module_logins_refresh_independently_and_logout_keeps_other_tab_authenticated(): void
    {
        $this->withoutVite();
        $directory = storage_path('framework/testing/module-sessions-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory);
        config(['session.driver' => 'file', 'session.files' => $directory]);
        app('session')->forgetDrivers();
        try {
            $users = [];
            $tokens = ['buyer' => str_repeat('a', 32), 'seller' => str_repeat('b', 32), 'courier' => str_repeat('c', 32), 'sorting_center' => str_repeat('d', 32), 'ecommerce_admin' => str_repeat('e', 32), 'logistics_admin' => str_repeat('f', 32)];
            foreach (array_keys($tokens) as $role) {
                $users[$role] = User::forceCreate(['email' => $role.'@session.example.com', 'password' => 'Password123!', 'role' => $role, 'status' => 'approved', 'first_name' => 'Session', 'last_name' => ucfirst($role), 'contact_no' => '09123456789']);
            }
            foreach ($tokens as $role => $token) {
                Auth::forgetGuards();
                $response = $this->post('/login?__tab='.$token, ['email' => $users[$role]->email, 'password' => 'Password123!']);
                $response->assertRedirect()->assertSessionHasNoErrors();
                $cookieName = config('session.cookie').'-tab-'.substr(hash('sha256', $token), 0, 24);
                $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $cookieName);
                $this->assertNotNull($cookie, 'A separate session cookie must be returned for '.$role);
                $this->withCookie($cookieName, CookieValuePrefix::remove(decrypt($cookie->getValue(), false)));
                $this->assertNotNull($users[$role]->fresh()->last_web_login_at, 'The '.$role.' account must complete its own login.');
            }
            Auth::forgetGuards();
            $this->get(route('buyer.dashboard', ['__tab' => str_repeat('a', 32)]))->assertOk()->assertSee('My purchases');
            Auth::forgetGuards();
            $this->get(route('seller.dashboard', ['__tab' => str_repeat('b', 32)]))->assertOk()->assertSee('Seller Actions');
            foreach (['courier' => 'courier.dashboard', 'sorting_center' => 'logistics.dashboard', 'ecommerce_admin' => 'admin.dashboard', 'logistics_admin' => 'logistics-admin.dashboard'] as $role => $route) {
                Auth::forgetGuards();
                $this->get(route($route, ['__tab' => $tokens[$role]]))->assertOk();
            }
            Auth::forgetGuards();
            $this->get(route('buyer.dashboard', ['__tab' => str_repeat('b', 32)]))->assertForbidden();
            Auth::forgetGuards();
            $this->post('/logout?__tab='.str_repeat('a', 32))->assertRedirect();
            Auth::forgetGuards();
            $this->get(route('seller.dashboard', ['__tab' => str_repeat('b', 32)]))->assertOk();
            Auth::forgetGuards();
            $this->get(route('buyer.dashboard', ['__tab' => str_repeat('a', 32)]))->assertRedirect();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_header_selected_tab_login_does_not_create_a_shared_remember_cookie(): void
    {
        $user = User::forceCreate(['email' => 'header-tab@example.com', 'password' => 'Password123!', 'role' => 'buyer', 'status' => 'approved', 'first_name' => 'Header', 'last_name' => 'Buyer', 'contact_no' => '09123456789']);
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $recaller = $guard->getRecallerName();
        $this->withHeader('X-Ezi-Tab-Session', str_repeat('c', 32))->post(route('login.post'), ['email' => $user->email, 'password' => 'Password123!', 'remember' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertCookieMissing($recaller);
    }

    public function test_legacy_remember_cookie_cannot_sign_a_fresh_tab_into_another_account(): void
    {
        $this->withoutVite();
        $user = User::forceCreate(['email' => 'remembered@example.com', 'password' => 'Password123!', 'role' => 'buyer', 'status' => 'approved', 'first_name' => 'Remembered', 'last_name' => 'Buyer', 'contact_no' => '09123456789', 'remember_token' => 'legacy-remember-token']);
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $cookieName = $guard->getRecallerName();
        $cookie = $user->id.'|'.$user->remember_token.'|'.$user->password;
        Auth::forgetGuards();
        $this->withCookie($cookieName, $cookie)->get('/login?__tab='.str_repeat('d', 32))
            ->assertOk()->assertSee('Sign in');
        $this->assertGuest();
    }

    public function test_login_discards_a_saved_destination_for_another_module(): void
    {
        $user = User::forceCreate(['email' => 'redirect-buyer@example.com', 'password' => 'Password123!', 'role' => 'buyer', 'status' => 'approved', 'first_name' => 'Redirect', 'last_name' => 'Buyer', 'contact_no' => '09123456789']);
        $this->withSession(['url.intended' => route('seller.dashboard')])
            ->post(route('login.post'), ['email' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('buyer.dashboard'));
    }

    public function test_login_preserves_an_authorized_saved_destination(): void
    {
        $user = User::forceCreate(['email' => 'redirect-seller@example.com', 'password' => 'Password123!', 'role' => 'seller', 'status' => 'approved', 'first_name' => 'Redirect', 'last_name' => 'Seller', 'contact_no' => '09123456789']);
        $target = route('seller.products.index');
        $this->withSession(['url.intended' => $target])
            ->post(route('login.post'), ['email' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect($target);
    }
}
