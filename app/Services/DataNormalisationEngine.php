<?php

namespace App\Services;

use Carbon\Carbon;

class DataNormalisationEngine
{
    public function normaliseTransaction(array $rawData): array
    {
        return [
            'reference' => $this->normaliseReference($rawData),
            'amount' => $this->normaliseAmount($rawData),
            'currency' => $this->normaliseCurrency($rawData),
            'description' => $this->normaliseDescription($rawData),
            'type' => $this->normaliseType($rawData),
            'date' => $this->normaliseDate($rawData),
        ];
    }

    public function normaliseAccount(array $rawData): array
    {
        return [
            'external_id' => $rawData['id'] ?? $rawData['account_id'] ?? $rawData['external_id'],
            'identifier' => $this->maskAccountIdentifier($rawData['account_number'] ?? $rawData['identifier'] ?? ''),
            'currency' => $this->normaliseCurrency($rawData),
            'balance' => $this->normaliseAmount(['amount' => $rawData['balance'] ?? 0]),
            'type' => $rawData['account_type'] ?? $rawData['type'] ?? 'checking',
        ];
    }

    protected function normaliseReference(array $data): string
    {
        return $data['reference'] 
            ?? $data['transaction_id'] 
            ?? $data['id'] 
            ?? 'REF-' . strtoupper(substr(md5(json_encode($data)), 0, 12));
    }

    protected function normaliseAmount(array $data): float
    {
        $amount = $data['amount'] ?? $data['value'] ?? 0;
        
        if (is_string($amount)) {
            $amount = str_replace([',', ' '], '', $amount);
            $amount = (float) $amount;
        }

        return round((float) $amount, 2);
    }

    protected function normaliseCurrency(array $data): string
    {
        $currency = $data['currency'] ?? $data['currency_code'] ?? 'USD';
        return strtoupper(substr(trim($currency), 0, 3));
    }

    protected function normaliseDescription(array $data): string
    {
        $description = $data['description'] 
            ?? $data['narrative'] 
            ?? $data['memo'] 
            ?? $data['details'] 
            ?? '';

        return trim(substr($description, 0, 255));
    }

    protected function normaliseType(array $data): string
    {
        $type = $data['type'] ?? $data['transaction_type'] ?? null;
        
        if ($type) {
            $type = strtolower($type);
            if (in_array($type, ['credit', 'deposit', 'incoming', 'received'])) {
                return 'credit';
            }
            if (in_array($type, ['debit', 'withdrawal', 'outgoing', 'sent'])) {
                return 'debit';
            }
        }

        $amount = $this->normaliseAmount($data);
        return $amount >= 0 ? 'credit' : 'debit';
    }

    protected function normaliseDate(array $data): Carbon
    {
        $date = $data['date'] 
            ?? $data['transaction_date'] 
            ?? $data['created_at'] 
            ?? $data['timestamp'] 
            ?? now();

        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return now();
        }
    }

    protected function maskAccountIdentifier(string $identifier): string
    {
        if (strlen($identifier) <= 4) {
            return $identifier;
        }

        $lastFour = substr($identifier, -4);
        $masked = str_repeat('*', strlen($identifier) - 4);
        
        return $masked . $lastFour;
    }

    public function normaliseInstitutionName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        return ucwords(strtolower($name));
    }

    public function normaliseCurrencyPair(string $pair): array
    {
        $pair = strtoupper(str_replace(['-', '_', ' '], '/', $pair));
        $parts = explode('/', $pair);

        return [
            'base' => $parts[0] ?? 'USD',
            'quote' => $parts[1] ?? 'USD',
        ];
    }
}
