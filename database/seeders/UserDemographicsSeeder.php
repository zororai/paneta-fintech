<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserDemographicsSeeder extends Seeder
{
    public function run(): void
    {
        $countries = ['ZA', 'US', 'GB', 'NG', 'KE', 'GH', 'UG', 'TZ', 'ZW', 'BW'];
        $genders = ['male', 'female', 'other', 'prefer_not_to_say'];
        
        // Update existing users with demographic data
        $users = User::all();
        
        foreach ($users as $user) {
            $user->update([
                'country' => $countries[array_rand($countries)],
                'gender' => $genders[array_rand($genders)],
                'date_of_birth' => now()->subYears(rand(18, 65))->subDays(rand(0, 365))->format('Y-m-d'),
            ]);
        }
        
        $this->command->info('Updated ' . $users->count() . ' users with demographic data');
    }
}
