<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Database\Factories\QuoteRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class QuoteRuleModel extends Model
{
    /** @use HasFactory<QuoteRuleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'quote_rules';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    protected static function newFactory(): QuoteRuleFactory
    {
        return QuoteRuleFactory::new();
    }
}
