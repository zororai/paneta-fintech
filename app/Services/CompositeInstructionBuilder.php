<?php

namespace App\Services;

use App\Models\CrossBorderTransactionIntent;
use App\Models\FxQuote;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Str;

class CompositeInstructionBuilder
{
    public function buildCrossBorderInstruction(
        User $user,
        LinkedAccount $sourceAccount,
        string $destinationIdentifier,
        float $sourceAmount,
        FxQuote $fxQuote,
        float $feeAmount
    ): array {
        $destinationAmount = round($sourceAmount * $fxQuote->rate, 2);

        return [
            'instruction_id' => 'CBX-INS-' . strtoupper(Str::random(12)),
            'created_at' => now()->toISOString(),
            'user_id' => $user->id,
            
            'leg_1_source_debit' => [
                'type' => 'debit',
                'account_id' => $sourceAccount->id,
                'institution_id' => $sourceAccount->institution_id,
                'amount' => $sourceAmount + $feeAmount,
                'currency' => $sourceAccount->currency,
                'reference' => 'LEG1-' . strtoupper(Str::random(8)),
            ],
            
            'leg_2_fx_conversion' => [
                'type' => 'fx_conversion',
                'provider_id' => $fxQuote->fx_provider_id,
                'quote_id' => $fxQuote->id,
                'source_currency' => $fxQuote->base_currency,
                'destination_currency' => $fxQuote->quote_currency,
                'source_amount' => $sourceAmount,
                'destination_amount' => $destinationAmount,
                'rate' => $fxQuote->rate,
                'reference' => 'LEG2-' . strtoupper(Str::random(8)),
            ],
            
            'leg_3_destination_credit' => [
                'type' => 'credit',
                'destination_identifier' => $destinationIdentifier,
                'amount' => $destinationAmount,
                'currency' => $fxQuote->quote_currency,
                'reference' => 'LEG3-' . strtoupper(Str::random(8)),
            ],
            
            'fee' => [
                'amount' => $feeAmount,
                'currency' => $sourceAccount->currency,
                'type' => 'cross_border',
            ],
            
            'totals' => [
                'source_total' => $sourceAmount + $feeAmount,
                'source_currency' => $sourceAccount->currency,
                'destination_total' => $destinationAmount,
                'destination_currency' => $fxQuote->quote_currency,
            ],
        ];
    }

    public function buildLocalInstruction(
        User $user,
        LinkedAccount $sourceAccount,
        string $destinationIdentifier,
        float $amount,
        float $feeAmount
    ): array {
        return [
            'instruction_id' => 'LOC-INS-' . strtoupper(Str::random(12)),
            'created_at' => now()->toISOString(),
            'user_id' => $user->id,
            
            'leg_1_debit' => [
                'type' => 'debit',
                'account_id' => $sourceAccount->id,
                'institution_id' => $sourceAccount->institution_id,
                'amount' => $amount + $feeAmount,
                'currency' => $sourceAccount->currency,
                'reference' => 'LEG1-' . strtoupper(Str::random(8)),
            ],
            
            'leg_2_credit' => [
                'type' => 'credit',
                'destination_identifier' => $destinationIdentifier,
                'amount' => $amount,
                'currency' => $sourceAccount->currency,
                'reference' => 'LEG2-' . strtoupper(Str::random(8)),
            ],
            
            'fee' => [
                'amount' => $feeAmount,
                'currency' => $sourceAccount->currency,
                'type' => 'platform',
            ],
            
            'totals' => [
                'total_debit' => $amount + $feeAmount,
                'total_credit' => $amount,
                'currency' => $sourceAccount->currency,
            ],
        ];
    }

    public function signInstruction(array $instruction): string
    {
        $payload = json_encode($instruction, JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $payload, config('paneta.instruction_secret', config('app.key')));
    }

    public function verifySignature(array $instruction, string $signature): bool
    {
        $expectedSignature = $this->signInstruction($instruction);
        return hash_equals($expectedSignature, $signature);
    }

    public function extractLegs(array $instruction): array
    {
        $legs = [];
        
        foreach ($instruction as $key => $value) {
            if (str_starts_with($key, 'leg_') && is_array($value)) {
                $legs[$key] = $value;
            }
        }
        
        return $legs;
    }

    public function validateInstruction(array $instruction): array
    {
        $errors = [];

        if (empty($instruction['instruction_id'])) {
            $errors[] = 'Missing instruction_id';
        }

        if (empty($instruction['user_id'])) {
            $errors[] = 'Missing user_id';
        }

        $legs = $this->extractLegs($instruction);
        
        if (empty($legs)) {
            $errors[] = 'No legs found in instruction';
        }

        foreach ($legs as $legKey => $leg) {
            if (empty($leg['type'])) {
                $errors[] = "Missing type in {$legKey}";
            }
            if (!isset($leg['amount']) || $leg['amount'] <= 0) {
                $errors[] = "Invalid amount in {$legKey}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
