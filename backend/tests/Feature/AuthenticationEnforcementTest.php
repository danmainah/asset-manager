<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **Feature: asset-manager, Property 11: Authentication Enforcement**
     * 
     * For any protected endpoint access, the system should verify valid authentication tokens 
     * and reject unauthorized requests.
     * 
     * **Validates: Requirements 8.1, 8.2**
     */
    public function test_authentication_enforcement_property()
    {
        // Property: All protected endpoints should reject unauthenticated requests
        $protectedEndpoints = $this->getProtectedEndpoints();
        
        foreach ($protectedEndpoints as $endpoint) {
            $this->assertEndpointRequiresAuthentication($endpoint);
        }
    }

    /**
     * Test that authenticated requests are accepted for protected endpoints
     */
    public function test_authenticated_requests_are_accepted()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        $protectedEndpoints = $this->getProtectedEndpoints();
        
        foreach ($protectedEndpoints as $endpoint) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->json($endpoint['method'], $endpoint['url'], $endpoint['data'] ?? []);
            
            // Should not return 401 Unauthorized
            $this->assertNotEquals(401, $response->getStatusCode(), 
                "Endpoint {$endpoint['method']} {$endpoint['url']} should accept authenticated requests");
        }
    }

    /**
     * Test with various invalid token formats
     */
    public function test_invalid_token_formats_are_rejected()
    {
        $invalidTokens = [
            '', // Empty token
            'invalid-token', // Invalid format
            'Bearer', // Missing token
            'Bearer ', // Empty bearer token
            'Basic dGVzdA==', // Wrong auth type
            'Bearer ' . str_repeat('a', 100), // Invalid token
        ];

        $endpoint = ['method' => 'GET', 'url' => '/api/profile'];

        foreach ($invalidTokens as $token) {
            $headers = ['Accept' => 'application/json'];
            if (!empty($token)) {
                $headers['Authorization'] = $token;
            }

            $response = $this->withHeaders($headers)
                ->json($endpoint['method'], $endpoint['url']);

            $this->assertEquals(401, $response->getStatusCode(),
                "Invalid token '{$token}' should be rejected");
        }
    }

    /**
     * Test that expired/revoked tokens are rejected
     */
    public function test_revoked_tokens_are_rejected()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;
        
        // Revoke the token
        $token->accessToken->delete();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
            'Accept' => 'application/json',
        ])->json('GET', '/api/profile');
        
        $this->assertEquals(401, $response->getStatusCode(),
            'Revoked tokens should be rejected');
    }

    /**
     * Property-based test: Generate random endpoint combinations and verify authentication
     */
    public function test_authentication_property_with_random_data()
    {
        $iterations = 10; // Run multiple iterations for property-based testing
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random invalid authorization headers
            $invalidAuth = $this->generateRandomInvalidAuth();
            
            // Test random protected endpoint
            $endpoints = $this->getProtectedEndpoints();
            $randomEndpoint = $endpoints[array_rand($endpoints)];
            
            $response = $this->withHeaders([
                'Authorization' => $invalidAuth,
                'Accept' => 'application/json',
            ])->json($randomEndpoint['method'], $randomEndpoint['url'], $randomEndpoint['data'] ?? []);
            
            $this->assertEquals(401, $response->getStatusCode(),
                "Random invalid auth '{$invalidAuth}' should be rejected for {$randomEndpoint['method']} {$randomEndpoint['url']}");
        }
    }

    /**
     * Helper method to assert that an endpoint requires authentication
     */
    private function assertEndpointRequiresAuthentication(array $endpoint): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json($endpoint['method'], $endpoint['url'], $endpoint['data'] ?? []);

        $this->assertEquals(401, $response->getStatusCode(),
            "Endpoint {$endpoint['method']} {$endpoint['url']} should require authentication");
        
        $this->assertJson($response->getContent());
    }

    /**
     * Get list of protected endpoints to test
     */
    private function getProtectedEndpoints(): array
    {
        return [
            ['method' => 'GET', 'url' => '/api/user'],
            ['method' => 'GET', 'url' => '/api/profile'],
            ['method' => 'GET', 'url' => '/api/orders'],
            ['method' => 'POST', 'url' => '/api/orders', 'data' => ['symbol' => 'BTC', 'side' => 'buy']],
        ];
    }

    /**
     * Generate random invalid authorization headers for property-based testing
     */
    private function generateRandomInvalidAuth(): string
    {
        $invalidFormats = [
            'Bearer ' . bin2hex(random_bytes(rand(10, 50))), // Random hex string
            'Basic ' . base64_encode(bin2hex(random_bytes(rand(5, 20)))), // Random basic auth
            bin2hex(random_bytes(rand(5, 30))), // Random string without Bearer
            'Token ' . bin2hex(random_bytes(rand(10, 40))), // Wrong prefix
            '', // Empty string
        ];
        
        return $invalidFormats[array_rand($invalidFormats)];
    }
}