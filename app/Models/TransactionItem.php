<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'menu_id',
        'matcha_type_id',
        'size_id',
        'topping_id',
        'quantity',
        'unit_price',
        'line_total',
        'item_options',
    ];

    protected $casts = [
        'item_options' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function matchaType(): BelongsTo
    {
        return $this->belongsTo(MatchaType::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function topping(): BelongsTo
    {
        return $this->belongsTo(Topping::class);
    }
}
