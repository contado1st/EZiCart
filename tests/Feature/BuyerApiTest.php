<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuyerApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBuyer(array $overrides = []): User
    {
        return User::forceCreate(array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'email' => 'buyer@example.com',
            'contact_no' => '09171234567',
            'birthday' => '1995-05-15',
            'age' => 30,
            'province' => 'Laguna',
            'municipality' => 'Lumban',
            'barangay' => 'Poblacion',
            'street_address' => 'Rizal Street',
            'password' => Hash::make('Password123!'),
            'role' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_buyer_can_login_via_api_and_receives_token(): void
    {
        $this->createBuyer([
            'email' => 'buyer_api_test@example.com',
            'password' => Hash::make('TestSecret123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'buyer_api_test@example.com',
            'password' => 'TestSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'token_type',
                'user' => ['id', 'email', 'role', 'status'],
            ]);

        $this->assertEquals('buyer', $response->json('user.role'));
    }

    public function test_non_buyer_cannot_login_via_buyer_api(): void
    {
        $this->createBuyer([
            'email' => 'seller_api_test@example.com',
            'password' => Hash::make('TestSecret123!'),
            'role' => 'seller',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'seller_api_test@example.com',
            'password' => 'TestSecret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_buyer_can_fetch_products_and_categories(): void
    {
        $response = $this->getJson('/api/products');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'current_page',
                'total',
            ]);

        $catResponse = $this->getJson('/api/categories');
        $catResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['name', 'icon', 'product_count'],
                ],
            ]);
    }

    public function test_authenticated_buyer_can_access_me_endpoint(): void
    {
        $buyer = $this->createBuyer();

        $tokenResult = ApiToken::createTokenForUser($buyer);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenResult['plainTextToken'])
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $buyer->id,
                    'role' => 'buyer',
                ],
            ]);
    }
}
