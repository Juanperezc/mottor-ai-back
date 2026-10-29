<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QuoteModel extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    protected $table = 'quotes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['exchange_rates' => 'array'];
    }

    protected static function newFactory(): QuoteFactory
    {
        return QuoteFactory::new();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItemModel::class, 'quote_id')->orderBy('position');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(QuoteRuleModel::class, 'quote_id')->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(QuoteStepModel::class, 'quote_id')->orderBy('step');
    }
}
