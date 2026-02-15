<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricalPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'asset_type',
        'exchange',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'adjusted_close',
        'volume',
        'currency',
        'price_date',
        'metadata',
    ];

    protected $casts = [
        'open_price' => 'decimal:6',
        'high_price' => 'decimal:6',
        'low_price' => 'decimal:6',
        'close_price' => 'decimal:6',
        'adjusted_close' => 'decimal:6',
        'price_date' => 'date',
        'metadata' => 'array',
    ];

    public static function getLatestPrice(string $symbol): ?self
    {
        return self::where('symbol', $symbol)
            ->orderBy('price_date', 'desc')
            ->first();
    }

    public static function getPriceHistory(string $symbol, int $days = 30): \Illuminate\Support\Collection
    {
        return self::where('symbol', $symbol)
            ->where('price_date', '>=', now()->subDays($days))
            ->orderBy('price_date', 'asc')
            ->get();
    }

    public function getPriceChange(): float
    {
        $previousClose = self::where('symbol', $this->symbol)
            ->where('price_date', '<', $this->price_date)
            ->orderBy('price_date', 'desc')
            ->first()?->close_price;

        if (!$previousClose) {
            return 0;
        }

        return $this->close_price - $previousClose;
    }

    public function getPriceChangePercent(): float
    {
        $previousClose = self::where('symbol', $this->symbol)
            ->where('price_date', '<', $this->price_date)
            ->orderBy('price_date', 'desc')
            ->first()?->close_price;

        if (!$previousClose || $previousClose == 0) {
            return 0;
        }

        return round((($this->close_price - $previousClose) / $previousClose) * 100, 2);
    }

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeForAssetType($query, string $type)
    {
        return $query->where('asset_type', $type);
    }

    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('price_date', [$start, $end]);
    }
}
