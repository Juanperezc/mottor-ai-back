# Quote datasets

Fixtures for the v2 quote payload (`budget`, `exchange_rates`, `currency`, `rules`).

| File | Cases | What it is |
| --- | ---: | --- |
| `official-v2.json` | 10,000 | Official dataset, copied byte for byte. SHA-256 `fb8f930b866cfe4731b80edb9b38fc84e0a02de138c8dcd958c97989ac7ccf82`. |
| `valid-v2.json` | 56 | Extra valid dataset. Same envelope (`version`, `generated_at`, `count`, `quotes`). |
| `invalid-v2.json` | 98 | Invalid quotes. Each case has `id`, `title`, `expected_errors` and `quote`. |

Pair counts in the official file: `USD_ARS` 9,825, `EUR_ARS` 2,084, `ARS_USD` 175. Every quote has the direct or inverse pair it needs. 1,579 quotes use at least one inverse pair. 1,401 have an empty `rules` array.

`valid-v2.json` is produced by `generate_datasets.php` (seed `20261028`, xorshift32, 32 hand-listed quotes plus 24 seeded quotes). It covers every ARS/USD/EUR pair in both directions, all six rule types, and valid edges (empty rules, empty rates, zero price, zero fixed amount, percent and cap at 0 and 100, `pay` 0, a category with no items, a promo remainder, a word longer than 28 characters, a repeated `budget.id`).

Regenerate and check the committed bytes:

```bash
php tests/Fixtures/datasets/generate_datasets.php
./vendor/bin/pest
```

`official-v2.json` is not rewritten by the script. Do not pretty-print it.

## How a quote is judged

`App\Domain\Money\RateSet` and `App\Http\Requests\QuoteValidationRules` are not in this repository. Pest uses `Tests\Support\QuoteDatasetValidator` as a stand-in derived from the challenge statement. Assumptions, because the statement leaves them open:

- Currencies are exactly `ARS`, `USD` and `EUR`.
- A pair key matches `^[A-Z]{3}_[A-Z]{3}$`, the two sides differ, and both are supported. The value is a JSON number greater than zero.
- Conversion is direct or inverse. Rates through a third currency do not count. A zero, negative or non-numeric rate does not satisfy the pair.
- `exchange_rates` may be `{}` when no item and no fixed discount needs a conversion. `rules` may be `[]`.
- Quantity is a whole number greater than or equal to 1. `unit_price` and fixed `amount` are JSON numbers greater than or equal to zero, with at most two decimal places.
- `percent` and `max_percent` are from 0 to 100 inclusive, with at most two decimal places.
- `quantity_promo` needs `buy >= 1`, `pay >= 0` and `pay < buy`. A category that matches no item is valid.
- Unknown rule types are rejected. Blank required strings are rejected. Extra properties are ignored. Repeated `budget.id` values are allowed.

`invalid-v2.json` records the code the stand-in returns (`required`, `blank`, `invalid_type`, `empty_items`, `unsupported_currency`, `malformed_pair_key`, `invalid_pair`, `rate_not_positive`, `missing_rate`, `unknown_rule_type`, `quantity_not_numeric`, `quantity_not_integer`, `quantity_not_positive`, `price_not_numeric`, `price_negative`, `price_scale`, `amount_not_numeric`, `amount_negative`, `amount_scale`, `percent_not_numeric`, `percent_out_of_range`, `percent_scale`, `buy_pay_invalid`, `invalid_item_id`). `missing_rate` details the direct pair that was absent, for example `USD_ARS`.
