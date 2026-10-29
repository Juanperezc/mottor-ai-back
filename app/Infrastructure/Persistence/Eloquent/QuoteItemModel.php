<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Database\Factories\QuoteItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class QuoteItemModel extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'quote_items';

    protected $guarded = [];

    protected static function newFactory(): QuoteItemFactory
    {
        return QuoteItemFactory::new();
    }
}
