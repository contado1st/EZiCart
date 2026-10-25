<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LoadingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_exposes_get_route_shapes_and_keeps_placeholders_inert_until_needed(): void
    {
        $this->withoutVite();
        $response = $this->get(route('login'))->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new DOMXPath($dom);
        /** @var DOMElement|null $root */
        $root = $xpath->query('//*[@data-page-loading]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $root);
        $this->assertSame('login', $root->getAttribute('data-loading-current'));
        $this->assertTrue($root->hasAttribute('hidden'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $root->getAttribute('data-loading-scope'));
        $this->assertCount(1, $xpath->query('//*[@data-loading-stage]'));
        $this->assertCount(1, $xpath->query('//template[@data-loading-template="product"]'));
        $routes = json_decode($xpath->query('//script[@data-loading-routes]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        $map = collect($routes)->keyBy('path');
        $this->assertSame('product', $map['/product/{product}']['shape']);
        $this->assertSame('checkout', $map['/checkout']['shape']);
        $this->assertSame('announcements', $map['/admin/announcements']['shape']);
        $this->assertSame('thread', $map['/chat/conversations/{conversation}']['shape']);
        $this->assertFalse($map->has('/seller/orders/{order}/status'));
    }

    public function test_announcements_use_the_form_and_bulletin_layout_and_embedded_threads_get_their_own_profile(): void
    {
        $this->withoutVite();
        $admin = User::forceCreate(['email' => 'loading-admin@example.test', 'password' => 'password', 'role' => 'admin', 'status' => 'approved', 'first_name' => 'Demo', 'last_name' => 'Admin', 'contact_no' => '09123456780']);
        $this->actingAs($admin)->get(route('admin.announcements.index'))->assertOk()
            ->assertSee('data-loading-current="announcements"', false)
            ->assertSee('ezi-skeleton-announcements', false)
            ->assertSee('Create Announcement');
        $this->get(route('chat.index', ['panel' => 1]))->assertOk()
            ->assertSee('data-loading-current="messages"', false)
            ->assertSee('data-page-content', false);
    }

    public function test_product_and_login_skeletons_have_their_expected_distinct_shapes(): void
    {
        $product = Blade::render('<x-loading-skeleton variant="product" />');
        $this->assertStringContainsString('ezi-skeleton-gallery', $product);
        $this->assertStringNotContainsString('ezi-skeleton-metrics', $product);
        $login = Blade::render('<x-loading-skeleton variant="login" />');
        $this->assertSame(2, substr_count($login, 'class="ezi-skeleton-field"'));
        $this->assertStringNotContainsString('ezi-skeleton-table', $login);
    }
}
