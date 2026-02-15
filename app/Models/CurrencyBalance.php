<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyBalance extends Model
{
    protected $fillable = [
        'currency',
        'total_fees_collected',
        'total_refunded',
        'total_adjustments',
        'net_position',
    ];

    protected $casts = [
        'total_fees_collected' => 'decimal:8',
        'total_refunded' => 'decimal:8',
        'total_adjustments' => 'decimal:8',
        'net_position' => 'decimal:8',
    ];

    public function recalculateNetPosition(): self
    {
        $this->net_position = $this->total_fees_collected 
            - $this->total_refunded 
            + $this->total_adjustments;
        $this->save();
        
        return $this;
    }

    public function addFee(float $amount): self
    {
        $this->increment('total_fees_collected', $amount);
        $this->recalculateNetPosition();
        
        return $this;
    }

    public function addRefund(float $amount): self
    {
        $this->increment('total_refunded', $amount);
        $this->recalculateNetPosition();
        
        return $this;
    }

    public function addAdjustment(float $amount): self
    {
        $this->increment('total_adjustments', $amount);
        $this->recalculateNetPosition();
        
        return $this;
    }

    public static function forCurrency(string $currency): self
    {
        return static::firstOrCreate(
            ['currency' => strtoupper($currency)],
            [
                'total_fees_collected' => 0,
                'total_refunded' => 0,
                'total_adjustments' => 0,
                'net_position' => 0,
            ]
        );
    }
}
