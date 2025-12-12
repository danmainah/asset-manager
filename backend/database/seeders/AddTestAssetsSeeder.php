<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Asset;

class AddTestAssetsSeeder extends Seeder
{
    /**
     * Seed test assets for existing users
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Get or create BTC asset
            $btcAsset = Asset::where('user_id', $user->id)
                ->where('symbol', 'BTC')
                ->first();

            if ($btcAsset) {
                // Add 1 BTC if balance is 0
                if ($btcAsset->amount == '0.00000000') {
                    $btcAsset->update(['amount' => '1.00000000']);
                    $this->command->info("Added 1 BTC to user: {$user->email}");
                }
            } else {
                // Create new BTC asset
                Asset::create([
                    'user_id' => $user->id,
                    'symbol' => 'BTC',
                    'amount' => '1.00000000',
                    'locked_amount' => '0.00000000',
                ]);
                $this->command->info("Created BTC asset with 1 BTC for user: {$user->email}");
            }

            // Get or create ETH asset
            $ethAsset = Asset::where('user_id', $user->id)
                ->where('symbol', 'ETH')
                ->first();

            if ($ethAsset) {
                // Add 10 ETH if balance is 0
                if ($ethAsset->amount == '0.00000000') {
                    $ethAsset->update(['amount' => '10.00000000']);
                    $this->command->info("Added 10 ETH to user: {$user->email}");
                }
            } else {
                // Create new ETH asset
                Asset::create([
                    'user_id' => $user->id,
                    'symbol' => 'ETH',
                    'amount' => '10.00000000',
                    'locked_amount' => '0.00000000',
                ]);
                $this->command->info("Created ETH asset with 10 ETH for user: {$user->email}");
            }
        }

        $this->command->info('Test assets added successfully!');
    }
}
