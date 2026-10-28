<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuVariant extends Model
{
    use HasFactory;

    protected $fillable = ['menu_id', 'name', 'slug', 'price_adjustment', 'is_active'];

    protected function casts(): array
    {
        return ['price_adjustment' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
