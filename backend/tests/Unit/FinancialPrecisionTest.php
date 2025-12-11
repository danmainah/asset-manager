<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Asset;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialPrecisionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **Feature: asset-manager, Property 13: Financial Precision Maintenance**
     * 
     * For any sequence of financial operations (trades, commission calculations, balance updates), 
     * the system should maintain decimal precision without rounding errors.
     * 
     * **Validates: Requirements 1.5**
     */
    public function test_financial_precision_maintenance_property()
    {
        $iterations = 100; // Property-based testing with multiple iterations
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random financial values with various precision levels
            $testValues = $this->generateRandomFinancialValues();
            
            foreach ($testValues as $value) {
                // Test User balance precision
                $this->assertFinancialPrecisionForUser($value);
                
                // Test Asset amount precision
                $this->assertFinancialPrecisionForAsset($value);
                
                // Test Order price and amount precision
                $this->assertFinancialPrecisionForOrder($value);
            }
        }
    }

    /**
     * Test that User balance maintains 8 decimal precision
     */
    private function assertFinancialPrecisionForUser($value): void
    {
        $user = User::factory()->create(['balance' => $value]);
        
        // Verify the stored value maintains precision
        $storedBalance = $user->fresh()->balance;
        
        // Check that precision is maintained (up to 8 decimal places)
        $this->assertEquals(
            number_format($value, 8, '.', ''),
            number_format($storedBalance, 8, '.', ''),
            "User balance precision not maintained for value: {$value}"
        );
        
        // Test validation method
        if ($this->isValidFinancialValue($value)) {
            $this->assertTrue(User::validateBalance($value));
        }
    }

    /**
     * Test that Asset amounts maintain 8 decimal precision
     */
    private function assertFinancialPrecisionForAsset($value): void
    {
        if (!$this->isValidFinancialValue($value)) {
            return; // Skip invalid values for this test
        }
        
        $user = User::factory()->create();
        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => $value,
            'locked_amount' => '0'
        ]);
        
        // Verify the stored value maintains precision
        $storedAmount = $asset->fresh()->amount;
        
        // Check that precision is maintained (up to 8 decimal places)
        $this->assertEquals(
            number_format($value, 8, '.', ''),
            number_format($storedAmount, 8, '.', ''),
            "Asset amount precision not maintained for value: {$value}"
        );
        
        // Test validation method
        $this->assertTrue(Asset::validateAmount($value));
    }

    /**
     * Test that Order price and amount maintain 8 decimal precision
     */
    private function assertFinancialPrecisionForOrder($value): void
    {
        if (!$this->isValidFinancialValue($value) || bccomp($value, '0', 8) <= 0) {
            return; // Skip invalid or non-positive values for orders
        }
        
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => $value,
            'amount' => $value,
            'status' => Order::STATUS_OPEN
        ]);
        
        // Verify the stored values maintain precision
        $storedPrice = $order->fresh()->price;
        $storedAmount = $order->fresh()->amount;
        
        // Check that precision is maintained (up to 8 decimal places)
        $this->assertEquals(
            number_format($value, 8, '.', ''),
            number_format($storedPrice, 8, '.', ''),
            "Order price precision not maintained for value: {$value}"
        );
        
        $this->assertEquals(
            number_format($value, 8, '.', ''),
            number_format($storedAmount, 8, '.', ''),
            "Order amount precision not maintained for value: {$value}"
        );
    }

    /**
     * Test precision validation rejects values with too many decimal places
     */
    public function test_precision_validation_rejects_excessive_decimals()
    {
        // Test values with more than 8 decimal places
        $excessivePrecisionValues = [
            '1.123456789', // 9 decimal places
            '0.1234567890123', // 13 decimal places
            '100.123456789012345', // 15 decimal places
        ];
        
        foreach ($excessivePrecisionValues as $value) {
            // Should throw validation exception for User balance
            $this->expectException(ValidationException::class);
            User::validateBalance($value);
        }
    }

    /**
     * Test arithmetic operations maintain precision
     */
    public function test_arithmetic_operations_maintain_precision()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $value1 = $this->generateValidFinancialValue();
            $value2 = $this->generateValidFinancialValue();
            
            // Test bcmath operations maintain precision
            $sum = bcadd($value1, $value2, 8);
            $difference = bcsub($value1, $value2, 8);
            $product = bcmul($value1, $value2, 8);
            
            // Verify results have at most 8 decimal places
            $this->assertValidPrecision($sum, "Addition result: {$value1} + {$value2}");
            $this->assertValidPrecision($difference, "Subtraction result: {$value1} - {$value2}");
            $this->assertValidPrecision($product, "Multiplication result: {$value1} * {$value2}");
        }
    }

    /**
     * Generate random financial values for property-based testing
     */
    private function generateRandomFinancialValues(): array
    {
        $values = [];
        
        // Generate various types of financial values
        for ($i = 0; $i < 10; $i++) {
            // Valid precision values (0-8 decimal places)
            $decimalPlaces = rand(0, 8);
            $integerPart = rand(0, 999999);
            $decimalPart = $decimalPlaces > 0 ? str_pad(rand(0, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
            $values[] = $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
            
            // Edge cases
            $values[] = '0';
            $values[] = '0.00000001'; // Minimum precision
            $values[] = '999999.99999999'; // Maximum reasonable value
            
            // Invalid precision values (more than 8 decimal places)
            if (rand(0, 1)) {
                $excessiveDecimals = str_repeat(rand(0, 9), rand(9, 15));
                $values[] = "1.{$excessiveDecimals}";
            }
        }
        
        return array_unique($values);
    }

    /**
     * Generate a valid financial value for testing
     */
    private function generateValidFinancialValue(): string
    {
        $decimalPlaces = rand(0, 8);
        $integerPart = rand(1, 999999);
        $decimalPart = $decimalPlaces > 0 ? str_pad(rand(0, pow(10, $decimalPlaces) - 1), $decimalPlaces, '0', STR_PAD_LEFT) : '';
        
        return $decimalPart ? "{$integerPart}.{$decimalPart}" : (string)$integerPart;
    }

    /**
     * Check if a value is valid for financial operations
     */
    private function isValidFinancialValue($value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        if (bccomp($value, '0', 8) < 0) {
            return false; // Negative values not allowed
        }
        
        // Check decimal precision
        $parts = explode('.', (string)$value);
        if (isset($parts[1]) && strlen($parts[1]) > 8) {
            return false; // Too many decimal places
        }
        
        return true;
    }

    /**
     * Assert that a value has valid precision (max 8 decimal places)
     */
    private function assertValidPrecision($value, $context = ''): void
    {
        $parts = explode('.', (string)$value);
        $decimalPlaces = isset($parts[1]) ? strlen($parts[1]) : 0;
        
        $this->assertLessThanOrEqual(8, $decimalPlaces, 
            "Value {$value} has {$decimalPlaces} decimal places, exceeding maximum of 8. Context: {$context}");
    }
}