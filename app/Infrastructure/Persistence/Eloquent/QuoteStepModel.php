<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Database\Factories\QuoteStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class QuoteStepModel extends Model
{
    /** @use HasFactory<QuoteStepFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'quote_steps';

    protected $guarded = [];

    protected static function newFactory(): QuoteStepFactory
    {
        return QuoteStepFactory::new();
    }
}
