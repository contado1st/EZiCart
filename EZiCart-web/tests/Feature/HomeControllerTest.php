<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public static function operationalRoles(): array
    {
        return [
            'admin' => ['admin', 'admin.dashboard'],
            'courier' => ['courier', 'courier.dashboard'],
            'sorting center' => ['sorting_center', 'logistics.dashboard'],
        ];
    }

    #[DataProvider('operationalRoles')]
    public function test_operational_roles_are_redirected_to_their_dashboard_from_home(string $role, string $dashboardRoute): void
    {
        $user = $this->user($role);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route($dashboardRoute));

        $this->get(route($dashboardRoute))
            ->assertOk()
            ->assertSee('href="'.route($dashboardRoute).'"', false)
            ->assertDontSee('Search for products, shops and more');
    }

    public function test_shoppers_can_still_open_the_marketplace_home(): void
    {
        $this->get(route('home'))->assertOk();

        $this->actingAs($this->user('buyer'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Search for products, shops and more');

        $this->actingAs($this->user('seller'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Search for products, shops and more');
    }

    private function user(string $role): User
    {
        return User::query()->forceCreate([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => $role,
            'status' => 'approved',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'sex' => 'Other',
            'contact_no' => '09123456789',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Laguna',
            'municipality' => 'Majayjay',
            'barangay' => 'Poblacion',
            'street_address' => '1 Test Street',
        ]);
    }
}
