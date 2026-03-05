<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $accountType = $input['account_type'] ?? 'personal';

        $validated = Validator::make(
            $input,
            $this->rulesFor($accountType)
        )->validate();

        $profilePicturePath = $this->storeUploadedFile($validated['profile_picture'] ?? null, 'profiles');
        $companyLogoPath = $this->storeUploadedFile($validated['company_logo'] ?? null, 'company-logos');

        $pinHash = Hash::make($validated['pin']);

        $attributes = [
            'account_type' => $validated['account_type'],
            'country_code' => $validated['country_code'],
            'pin_hash' => $pinHash,
            'password' => $pinHash,
        ];

        if ($validated['account_type'] === 'business') {
            $attributes = array_merge($attributes, [
                'name' => $validated['company_name'],
                'email' => $validated['business_email'],
                'phone' => $validated['business_phone'],
                'company_name' => $validated['company_name'],
                'business_phone' => $validated['business_phone'],
                'company_type' => $validated['company_type'],
                'business_sector' => $validated['business_sector'],
                'services_offered' => filled($validated['services_offered'] ?? null) ? $validated['services_offered'] : null,
                'registration_number' => $validated['registration_number'],
                'physical_address' => $validated['physical_address'],
                'website' => filled($validated['website'] ?? null) ? $validated['website'] : null,
                'tax_id' => filled($validated['tax_id'] ?? null) ? $validated['tax_id'] : null,
                'business_email' => $validated['business_email'],
                'company_logo_path' => $companyLogoPath,
            ]);
        } else {
            $attributes = array_merge($attributes, [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'date_of_birth' => $validated['date_of_birth'],
                'country_of_origin' => $validated['country_of_origin'],
                'city' => $validated['city'],
                'address' => filled($validated['address'] ?? null) ? $validated['address'] : null,
                'profile_picture_path' => $profilePicturePath,
            ]);
        }

        return User::create($attributes);
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    private function rulesFor(string $accountType): array
    {
        $baseRules = [
            'account_type' => ['required', Rule::in(['personal', 'business'])],
            'country_code' => ['required', 'string', 'max:6'],
            'pin' => ['required', 'digits:4', 'confirmed'],
            'pin_confirmation' => ['required', 'digits:4'],
        ];

        if ($accountType === 'business') {
            return array_merge($baseRules, [
                'company_name' => ['required', 'string', 'max:255'],
                'business_phone' => ['required', 'string', 'max:32'],
                'company_type' => ['required', 'string', 'max:255'],
                'business_sector' => ['required', 'string', 'max:255'],
                'services_offered' => ['nullable', 'string'],
                'registration_number' => ['required', 'string', 'max:100'],
                'physical_address' => ['required', 'string', 'max:255'],
                'website' => ['nullable', 'url', 'max:255'],
                'tax_id' => ['nullable', 'string', 'max:100'],
                'business_email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'company_logo' => ['nullable', 'image', 'max:5120'],
            ]);
        }

        return array_merge($baseRules, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'max:32'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'country_of_origin' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'profile_picture' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function storeUploadedFile(?UploadedFile $file, string $directory): ?string
    {
        return $file?->store($directory, 'public');
    }
}

