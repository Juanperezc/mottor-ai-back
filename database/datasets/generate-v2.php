<?php

declare(strict_types=1);

/**
 * Regenerates the valid and invalid v2 quote datasets.
 *
 * The PRNG is an inline xorshift32 (seed 20261028), not PHP mt_rand, so the
 * files do not depend on the interpreter's RNG. Money and rates are written
 * as decimal literals, never as PHP floats.
 *
 * Usage: ./vendor/bin/sail php database/datasets/generate-v2.php [output-dir]
 */

require_once dirname(__DIR__, 2).'/tests/Support/QuoteDatasetValidator.php';

use Tests\Support\QuoteDatasetValidator;

const DATASET_SEED = 20261028;
const SEEDED_QUOTE_COUNT = 24;
const GENERATED_AT = '2026-10-28';

final class JsonNumber
{
    public function __construct(public string $literal)
    {
        if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $literal) !== 1) {
            throw new InvalidArgumentException('Not a JSON number literal: '.$literal);
        }
    }
}

final class JsonObject
{
    /** @param array<string, mixed> $props */
    public function __construct(public array $props) {}
}

final class JsonEncoder
{
    public static function encode(mixed $value, int $indent = 0): string
    {
        $pad = str_repeat('  ', $indent);
        $inner = str_repeat('  ', $indent + 1);

        if ($value instanceof JsonNumber) {
            return $value->literal;
        }

        if ($value instanceof JsonObject) {
            if ($value->props === []) {
                return '{}';
            }
            $lines = [];
            foreach ($value->props as $key => $child) {
                $lines[] = $inner.json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).': '.self::encode($child, $indent + 1);
            }

            return "{\n".implode(",\n", $lines)."\n".$pad.'}';
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                throw new RuntimeException('Associative arrays must be JsonObject instances.');
            }
            if ($value === []) {
                return '[]';
            }
            $lines = [];
            foreach ($value as $child) {
                $lines[] = $inner.self::encode($child, $indent + 1);
            }

            return "[\n".implode(",\n", $lines)."\n".$pad.']';
        }

        if (is_string($value) || is_int($value) || is_bool($value) || $value === null) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        throw new RuntimeException('Cannot encode '.get_debug_type($value));
    }
}

final class Rng
{
    public function __construct(private int $state)
    {
        $this->state &= 0xFFFFFFFF;
        if ($this->state === 0) {
            $this->state = 0x6D2B79F5;
        }
    }

    public function next(): int
    {
        $x = $this->state & 0xFFFFFFFF;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17) & 0xFFFFFFFF;
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        return $this->state;
    }

    public function int(int $min, int $max): int
    {
        if ($max < $min) {
            throw new InvalidArgumentException('Invalid range.');
        }

        $span = $max - $min + 1;

        return $min + ($this->next() % $span);
    }
}

function scaled(int $units, int $scale): JsonNumber
{
    $sign = $units < 0 ? '-' : '';
    $units = abs($units);
    if ($scale === 0) {
        return new JsonNumber($sign.(string) $units);
    }

    $digits = str_pad((string) $units, $scale + 1, '0', STR_PAD_LEFT);

    return new JsonNumber($sign.substr($digits, 0, -$scale).'.'.substr($digits, -$scale));
}

function num(string $literal): JsonNumber
{
    return new JsonNumber($literal);
}

function obj(array $props): JsonObject
{
    return new JsonObject($props);
}

function item(int $id, string $description, string $category, int|JsonNumber $quantity, string $price, string $currency): JsonObject
{
    return obj([
        'id' => $id,
        'description' => $description,
        'category' => $category,
        'quantity' => $quantity,
        'unit_price' => num($price),
        'currency' => $currency,
    ]);
}

function rates(array $pairs): JsonObject
{
    $props = [];
    foreach ($pairs as $key => $literal) {
        $props[$key] = $literal instanceof JsonNumber ? $literal : num((string) $literal);
    }

    return obj($props);
}

function budget(string $id, string $client, string $event, array $items): JsonObject
{
    return obj([
        'id' => $id,
        'client' => $client,
        'event' => $event,
        'items' => $items,
    ]);
}

function quote(JsonObject $budget, JsonObject $rates, string $currency, array $rules): JsonObject
{
    return obj([
        'budget' => $budget,
        'exchange_rates' => $rates,
        'currency' => $currency,
        'rules' => $rules,
    ]);
}

function promo(string $category, int $buy, int $pay): JsonObject
{
    return obj([
        'type' => 'quantity_promo',
        'category' => $category,
        'buy' => $buy,
        'pay' => $pay,
    ]);
}

function categoryDiscount(string $category, int|JsonNumber $percent): JsonObject
{
    return obj([
        'type' => 'category_discount',
        'category' => $category,
        'percent' => $percent,
    ]);
}

function percentage(int|JsonNumber $percent): JsonObject
{
    return obj([
        'type' => 'percentage_discount',
        'percent' => $percent,
    ]);
}

function fixedDiscount(string $amount, string $currency): JsonObject
{
    return obj([
        'type' => 'fixed_discount',
        'amount' => num($amount),
        'currency' => $currency,
    ]);
}

function cap(int|JsonNumber $maxPercent): JsonObject
{
    return obj([
        'type' => 'discount_cap',
        'max_percent' => $maxPercent,
    ]);
}

function tax(string $name, int|JsonNumber $percent): JsonObject
{
    return obj([
        'type' => 'tax',
        'name' => $name,
        'percent' => $percent,
    ]);
}

function rateFor(string $pair, Rng $rng): JsonNumber
{
    return match ($pair) {
        'USD_ARS' => scaled(138000 + $rng->int(0, 14000), 2),
        'EUR_ARS' => scaled(148000 + $rng->int(0, 17000), 2),
        'ARS_USD' => scaled(650 + $rng->int(0, 80), 6),
        'ARS_EUR' => scaled(600 + $rng->int(0, 80), 6),
        'USD_EUR' => scaled(90 + $rng->int(0, 10), 2),
        'EUR_USD' => scaled(105 + $rng->int(0, 15), 2),
        default => throw new InvalidArgumentException('Unsupported pair '.$pair),
    };
}

/** @return list<JsonObject> */
function catalogQuotes(): array
{
    $quotes = [];

    $quotes[] = quote(
        budget('PRE-2026-0418', 'Agencia Norte', 'Lanzamiento de producto · 120 personas', [
            item(1, 'Menú de tres pasos', 'catering', 120, '18000.00', 'ARS'),
            item(2, 'Barra de tragos (módulo)', 'catering', 3, '150000.00', 'ARS'),
            item(3, 'Sistema de sonido line array', 'sonido', 1, '1200.00', 'USD'),
            item(4, 'Mesa redonda de 8', 'mobiliario', 15, '9500.00', 'ARS'),
            item(5, 'Silla tiffany', 'mobiliario', 120, '1800.00', 'ARS'),
            item(6, 'Coordinador en evento', 'personal', 2, '85000.00', 'ARS'),
        ]),
        rates(['USD_ARS' => '1450.00']),
        'ARS',
        [
            promo('mobiliario', 3, 2),
            categoryDiscount('catering', 15),
            percentage(10),
            fixedDiscount('50000.00', 'ARS'),
            cap(20),
            tax('IVA', 21),
        ],
    );

    $quotes[] = quote(
        budget('PRE-2026-0419', 'Agencia Norte', 'Lanzamiento de producto · 120 personas', [
            item(1, 'Menú de tres pasos', 'catering', 120, '18000.00', 'ARS'),
            item(2, 'Barra de tragos (módulo)', 'catering', 3, '150000.00', 'ARS'),
            item(3, 'Sistema de sonido line array', 'sonido', 2, '1200.00', 'USD'),
            item(4, 'Mesa redonda de 8', 'mobiliario', 16, '9500.00', 'ARS'),
            item(5, 'Silla tiffany', 'mobiliario', 121, '1800.00', 'ARS'),
            item(6, 'Coordinador en evento', 'personal', 2, '85000.00', 'ARS'),
            item(7, 'Pantalla LED 6×3 m con operador técnico', 'tecnica', 1, '850.00', 'EUR'),
        ]),
        rates(['USD_ARS' => '1437.35', 'EUR_ARS' => '1562.10']),
        'ARS',
        [
            promo('mobiliario', 3, 2),
            categoryDiscount('catering', 15),
            percentage(num('12.5')),
            fixedDiscount('100.00', 'USD'),
            tax('IVA', 21),
            cap(18),
        ],
    );

    $quotes[] = quote(
        budget('VAL-2026-0001', 'Agencia Norte', 'Reunión interna', [
            item(1, 'Café de bienvenida', 'catering', 1, '0.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [],
    );

    $quotes[] = quote(
        budget('VAL-2026-0002', 'Sur Eventos', 'Showroom en dólares', [
            item(1, 'Sistema de sonido', 'sonido', 1, '1200.00', 'USD'),
        ]),
        rates([]),
        'USD',
        [],
    );

    $quotes[] = quote(
        budget('VAL-2026-0003', 'Andes Live', 'Cena en euros', [
            item(1, 'Menú degustación', 'catering', 8, '85.00', 'EUR'),
        ]),
        rates([]),
        'EUR',
        [tax('IVA', 0)],
    );

    $direct = [
        ['USD', 'ARS', 'USD_ARS', '1450.00'],
        ['EUR', 'ARS', 'EUR_ARS', '1562.10'],
        ['ARS', 'USD', 'ARS_USD', '0.000690'],
        ['ARS', 'EUR', 'ARS_EUR', '0.000640'],
        ['USD', 'EUR', 'USD_EUR', '0.92'],
        ['EUR', 'USD', 'EUR_USD', '1.08'],
    ];
    foreach ($direct as $index => [$itemCurrency, $output, $pair, $rate]) {
        $quotes[] = quote(
            budget(sprintf('VAL-2026-01%02d', $index + 1), 'Agencia Norte', 'Conversión directa '.$pair, [
                item(1, 'Equipo técnico', 'tecnica', 1, '100.00', $itemCurrency),
            ]),
            rates([$pair => $rate]),
            $output,
            [],
        );
    }

    $inverse = [
        ['USD', 'ARS', 'ARS_USD', '0.000690'],
        ['ARS', 'USD', 'USD_ARS', '1450.00'],
        ['EUR', 'ARS', 'ARS_EUR', '0.000640'],
        ['ARS', 'EUR', 'EUR_ARS', '1562.10'],
        ['USD', 'EUR', 'EUR_USD', '1.08'],
        ['EUR', 'USD', 'USD_EUR', '0.92'],
    ];
    foreach ($inverse as $index => [$itemCurrency, $output, $pair, $rate]) {
        $quotes[] = quote(
            budget(sprintf('VAL-2026-02%02d', $index + 1), 'Agencia Norte', 'Conversión inversa '.$pair, [
                item(1, 'Equipo técnico', 'tecnica', 2, '50.00', $itemCurrency),
            ]),
            rates([$pair => $rate]),
            $output,
            [],
        );
    }

    $quotes[] = quote(
        budget('VAL-2026-0004', 'Pampa Producciones', 'Par directo e inverso a la vez', [
            item(1, 'Consola de audio', 'sonido', 1, '800.00', 'USD'),
            item(2, 'Silla tiffany', 'mobiliario', 10, '1800.00', 'ARS'),
        ]),
        rates(['USD_ARS' => '1450.00', 'ARS_USD' => '0.000690']),
        'ARS',
        [],
    );

    $quotes[] = quote(
        budget('VAL-2026-0005', 'Agencia Norte', 'Las seis reglas en un presupuesto en pesos', [
            item(1, 'Mesa redonda de 8', 'mobiliario', 16, '9500.00', 'ARS'),
            item(2, 'Menú de tres pasos', 'catering', 10, '18000.00', 'ARS'),
            item(3, 'Coordinador en evento', 'personal', 1, '85000.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [
            promo('mobiliario', 3, 2),
            categoryDiscount('catering', 15),
            percentage(10),
            fixedDiscount('50000.00', 'ARS'),
            cap(20),
            tax('IVA', 21),
        ],
    );

    $quotes[] = quote(
        budget('VAL-2026-0006', 'Sur Eventos', 'Promo que no completa grupo y pay cero', [
            item(1, 'Silla tiffany', 'mobiliario', 16, '1800.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [promo('mobiliario', 3, 0)],
    );

    $quotes[] = quote(
        budget('VAL-2026-0007', 'Andes Live', 'Descuento de una categoría que no está en los ítems', [
            item(1, 'Sistema de sonido', 'sonido', 1, '1200.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [categoryDiscount('logistica', 10)],
    );

    $quotes[] = quote(
        budget('VAL-2026-0008', 'Agencia Norte', 'Porcentaje cero', [
            item(1, 'Café de bienvenida', 'catering', 4, '2500.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [percentage(0)],
    );

    $quotes[] = quote(
        budget('VAL-2026-0009', 'Agencia Norte', 'Porcentaje cien', [
            item(1, 'Café de bienvenida', 'catering', 4, '2500.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [percentage(100)],
    );

    $quotes[] = quote(
        budget('VAL-2026-0010', 'Agencia Norte', 'Tope de descuento cero', [
            item(1, 'Café de bienvenida', 'catering', 4, '2500.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [cap(0)],
    );

    $quotes[] = quote(
        budget('VAL-2026-0011', 'Agencia Norte', 'Tope de descuento cien', [
            item(1, 'Café de bienvenida', 'catering', 4, '2500.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [cap(100)],
    );

    $quotes[] = quote(
        budget('VAL-2026-0012', 'Agencia Norte', 'Descuento fijo en otra moneda', [
            item(1, 'Menú de tres pasos', 'catering', 10, '18000.00', 'ARS'),
        ]),
        rates(['USD_ARS' => '1450.00']),
        'ARS',
        [fixedDiscount('100.00', 'USD')],
    );

    $quotes[] = quote(
        budget('VAL-2026-0013', 'Agencia Norte', 'Descuento fijo de monto cero', [
            item(1, 'Menú de tres pasos', 'catering', 10, '18000.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [fixedDiscount('0.00', 'ARS')],
    );

    $longEvent = 'Fiesta corporativa de fin de año para toda la planta con cena de gala y show en vivo en el salón principal de Pilar Norte';
    $quotes[] = quote(
        budget('VAL-2026-0014', 'Pampa Producciones', $longEvent, [
            item(1, 'ElectroacusticaLineArrayModular en escenario principal', 'sonido', 1, '2400.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [],
    );

    $quotes[] = quote(
        budget('PRE-SHARED', 'Agencia Norte', 'Primer presupuesto con id repetido', [
            item(1, 'Silla tiffany', 'mobiliario', 8, '1800.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [],
    );

    $quotes[] = quote(
        budget('PRE-SHARED', 'Sur Eventos', 'Segundo presupuesto con el mismo id', [
            item(1, 'Silla tiffany', 'mobiliario', 9, '1800.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [],
    );

    $quotes[] = quote(
        budget('VAL-2026-0015', 'Agencia Núñez', 'Reunión anual · Auditorio', [
            item(10, 'Guirnalda de luces', 'decoracion', 6, '3200.00', 'ARS'),
            item(20, 'Foco de ambiente', 'iluminacion', 4, '1500.50', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [tax('Percepcion', num('1.5')), tax('IIBB', num('7.5'))],
    );

    $quotes[] = quote(
        budget('VAL-2026-0016', 'Entre Ríos Group', 'Ítems en las tres monedas', [
            item(1, 'Menú de tres pasos', 'catering', 20, '18000.00', 'ARS'),
            item(2, 'Sistema de sonido', 'sonido', 1, '1200.00', 'USD'),
            item(3, 'Pantalla LED', 'tecnica', 1, '850.00', 'EUR'),
            item(4, 'Silla tiffany', 'mobiliario', 24, '1800.00', 'ARS'),
            item(5, 'Coordinador en evento', 'personal', 2, '85000.00', 'ARS'),
            item(6, 'Barra de tragos', 'catering', 2, '150000.00', 'ARS'),
            item(7, 'Mesa redonda', 'mobiliario', 5, '9500.00', 'ARS'),
            item(8, 'Backline', 'sonido', 1, '400.00', 'USD'),
        ]),
        rates(['USD_ARS' => '1514.12', 'EUR_ARS' => '1649.92', 'ARS_EUR' => '0.000610']),
        'ARS',
        [
            promo('mobiliario', 5, 1),
            categoryDiscount('catering', num('12.50')),
            percentage(num('7.5')),
            cap(30),
            tax('IVA', 21),
        ],
    );

    return $quotes;
}

/** @return list<JsonObject> */
function seededQuotes(Rng $rng, int $count): array
{
    $currencies = ['ARS', 'USD', 'EUR'];
    $categories = ['catering', 'decoracion', 'iluminacion', 'mobiliario', 'personal', 'sonido', 'tecnica'];
    $clients = ['Agencia Norte', 'Entre Ríos Group', 'Sur Eventos', 'Pampa Producciones', 'Andes Live'];
    $events = [
        'Congreso regional',
        'Lanzamiento de producto',
        'Cena de fin de año',
        'Ensayo general',
        'Feria comercial',
    ];
    $descriptions = [
        'Menú de tres pasos',
        'Silla tiffany',
        'Sistema de sonido',
        'Coordinador en evento',
        'Pantalla LED',
        'Guirnalda de luces',
    ];
    $taxNames = ['IVA', 'IIBB', 'Percepcion'];
    $percentChoices = [0, 10, 15, 21, 100, num('1.5'), num('7.5'), num('12.5')];
    // A random 100% discount followed by a fixed discount or a promo can push
    // the total below zero, which the API rejects (CAL-11). Seeded discounts
    // stop at 50%; VAL-2026-0009 keeps the 100% case.
    $discountPercentChoices = [0, 10, 15, 21, 50, num('1.5'), num('7.5'), num('12.5')];
    $capChoices = [0, 10, 18, 20, 100];
    $allPairs = ['USD_ARS', 'EUR_ARS', 'ARS_USD', 'ARS_EUR', 'USD_EUR', 'EUR_USD'];

    $quotes = [];
    for ($i = 0; $i < $count; $i++) {
        $output = $currencies[$i % 3];
        $itemCount = 1 + ($i % 5);
        $items = [];
        for ($n = 1; $n <= $itemCount; $n++) {
            $items[] = item(
                $n,
                $descriptions[$rng->int(0, count($descriptions) - 1)],
                $categories[$rng->int(0, count($categories) - 1)],
                $rng->int(1, 30),
                scaled($rng->int(100, 5_000_000), 2)->literal,
                $currencies[$rng->int(0, 2)],
            );
        }

        $ruleCount = $rng->int(0, 6);
        $rules = [];
        for ($r = 0; $r < $ruleCount; $r++) {
            $type = ['quantity_promo', 'category_discount', 'percentage_discount', 'fixed_discount', 'discount_cap', 'tax'][$rng->int(0, 5)];
            $rules[] = match ($type) {
                'quantity_promo' => promo(
                    $categories[$rng->int(0, count($categories) - 1)],
                    $buy = $rng->int(2, 5),
                    $rng->int(0, $buy - 1),
                ),
                'category_discount' => categoryDiscount(
                    $categories[$rng->int(0, count($categories) - 1)],
                    $discountPercentChoices[$rng->int(0, count($discountPercentChoices) - 1)],
                ),
                'percentage_discount' => percentage($discountPercentChoices[$rng->int(0, count($discountPercentChoices) - 1)]),
                'fixed_discount' => fixedDiscount(
                    scaled($rng->int(0, 1_000_000), 2)->literal,
                    $currencies[$rng->int(0, 2)],
                ),
                'discount_cap' => cap($capChoices[$rng->int(0, count($capChoices) - 1)]),
                'tax' => tax(
                    $taxNames[$rng->int(0, count($taxNames) - 1)],
                    $percentChoices[$rng->int(0, count($percentChoices) - 1)],
                ),
            };
        }

        $needed = [];
        foreach ($items as $line) {
            $currency = $line->props['currency'];
            if ($currency !== $output) {
                $needed[$currency.'_'.$output] = true;
            }
        }
        foreach ($rules as $rule) {
            if ($rule->props['type'] === 'fixed_discount' && $rule->props['currency'] !== $output) {
                $needed[$rule->props['currency'].'_'.$output] = true;
            }
        }

        $rateProps = [];
        foreach (array_keys($needed) as $direct) {
            [$base, $quoteCurrency] = explode('_', $direct);
            $key = $rng->int(0, 1) === 1 ? $quoteCurrency.'_'.$base : $direct;
            if (! array_key_exists($key, $rateProps)) {
                $rateProps[$key] = rateFor($key, $rng);
            }
        }
        if ($rng->int(0, 2) === 0) {
            $extra = $allPairs[$rng->int(0, count($allPairs) - 1)];
            if (! array_key_exists($extra, $rateProps)) {
                $rateProps[$extra] = rateFor($extra, $rng);
            }
        }

        $quotes[] = quote(
            budget(
                sprintf('VAL-2026-1%03d', $i + 1),
                $clients[$rng->int(0, count($clients) - 1)],
                $events[$rng->int(0, count($events) - 1)],
                $items,
            ),
            obj($rateProps),
            $output,
            $rules,
        );
    }

    return $quotes;
}

function baseQuote(string $id): JsonObject
{
    return quote(
        budget($id, 'Agencia Norte', 'Lanzamiento de producto', [
            item(1, 'Menu de tres pasos', 'catering', 2, '18000.00', 'ARS'),
        ]),
        rates([]),
        'ARS',
        [],
    );
}

function expected(string $path, string $code, string $detail = ''): JsonObject
{
    $props = ['path' => $path, 'code' => $code];
    if ($detail !== '') {
        $props['detail'] = $detail;
    }

    return obj($props);
}

/** @return list<JsonObject> */
function invalidCases(): array
{
    $cases = [];
    $seen = [];
    $add = function (string $id, string $title, array $errors, mixed $quote) use (&$cases, &$seen): void {
        if (isset($seen[$id])) {
            throw new RuntimeException('Duplicate invalid case id '.$id);
        }
        $seen[$id] = true;
        $cases[] = obj([
            'id' => $id,
            'title' => $title,
            'expected_errors' => $errors,
            'quote' => $quote,
        ]);
    };

    $q = baseQuote('PRE-ERR-BUDGET');
    unset($q->props['budget']);
    $add('missing-budget', 'Budget object is required', [expected('budget', 'required')], $q);

    $q = baseQuote('PRE-ERR-BUDGET-TYPE');
    $q->props['budget'] = [1];
    $add('budget-not-object', 'Budget must be an object', [expected('budget', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-ID');
    unset($q->props['budget']->props['id']);
    $add('missing-budget-id', 'budget.id is required', [expected('budget.id', 'required')], $q);

    $q = baseQuote('PRE-ERR-ID-TYPE');
    $q->props['budget']->props['id'] = 418;
    $add('budget-id-not-string', 'budget.id must be a string', [expected('budget.id', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-ID-BLANK');
    $q->props['budget']->props['id'] = '   ';
    $add('budget-id-blank', 'budget.id cannot be blank', [expected('budget.id', 'blank')], $q);

    $q = baseQuote('PRE-ERR-CLIENT');
    unset($q->props['budget']->props['client']);
    $add('missing-client', 'budget.client is required', [expected('budget.client', 'required')], $q);

    $q = baseQuote('PRE-ERR-CLIENT-BLANK');
    $q->props['budget']->props['client'] = '   ';
    $add('client-blank', 'budget.client cannot be blank', [expected('budget.client', 'blank')], $q);

    $q = baseQuote('PRE-ERR-EVENT');
    unset($q->props['budget']->props['event']);
    $add('missing-event', 'budget.event is required', [expected('budget.event', 'required')], $q);

    $q = baseQuote('PRE-ERR-EVENT-BLANK');
    $q->props['budget']->props['event'] = ' ';
    $add('event-blank', 'budget.event cannot be blank', [expected('budget.event', 'blank')], $q);

    $q = baseQuote('PRE-ERR-ITEMS');
    unset($q->props['budget']->props['items']);
    $add('missing-items', 'budget.items is required', [expected('budget.items', 'required')], $q);

    $q = baseQuote('PRE-ERR-ITEMS-TYPE');
    $q->props['budget']->props['items'] = 'no';
    $add('items-not-array', 'budget.items must be an array', [expected('budget.items', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-ITEMS-NULL');
    $q->props['budget']->props['items'] = null;
    $add('items-null', 'budget.items cannot be null', [expected('budget.items', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-ITEMS-EMPTY');
    $q->props['budget']->props['items'] = [];
    $add('empty-items', 'A quote needs at least one item', [expected('budget.items', 'empty_items')], $q);

    $q = baseQuote('PRE-ERR-ITEM-TYPE');
    $q->props['budget']->props['items'] = ['bad'];
    $add('item-not-object', 'Each item must be an object', [expected('budget.items.0', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-ITEM-ID');
    unset($q->props['budget']->props['items'][0]->props['id']);
    $add('missing-item-id', 'Item id is required', [expected('budget.items.0.id', 'required')], $q);

    $q = baseQuote('PRE-ERR-ITEM-ID-TYPE');
    $q->props['budget']->props['items'][0]->props['id'] = '1';
    $add('item-id-not-integer', 'Item id must be a positive integer', [expected('budget.items.0.id', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-ITEM-ID-ZERO');
    $q->props['budget']->props['items'][0]->props['id'] = 0;
    $add('item-id-not-positive', 'Item id must be greater than zero', [expected('budget.items.0.id', 'invalid_item_id')], $q);

    $q = baseQuote('PRE-ERR-DESC');
    unset($q->props['budget']->props['items'][0]->props['description']);
    $add('missing-description', 'Item description is required', [expected('budget.items.0.description', 'required')], $q);

    $q = baseQuote('PRE-ERR-DESC-BLANK');
    $q->props['budget']->props['items'][0]->props['description'] = '  ';
    $add('description-blank', 'Item description cannot be blank', [expected('budget.items.0.description', 'blank')], $q);

    $q = baseQuote('PRE-ERR-CAT');
    unset($q->props['budget']->props['items'][0]->props['category']);
    $add('missing-item-category', 'Item category is required', [expected('budget.items.0.category', 'required')], $q);

    $q = baseQuote('PRE-ERR-CAT-BLANK');
    $q->props['budget']->props['items'][0]->props['category'] = ' ';
    $add('item-category-blank', 'Item category cannot be blank', [expected('budget.items.0.category', 'blank')], $q);

    $q = baseQuote('PRE-ERR-QTY');
    unset($q->props['budget']->props['items'][0]->props['quantity']);
    $add('missing-quantity', 'Item quantity is required', [expected('budget.items.0.quantity', 'required')], $q);

    $q = baseQuote('PRE-ERR-QTY-STRING');
    $q->props['budget']->props['items'][0]->props['quantity'] = '10';
    $add('quantity-string', 'Quantity must be a JSON number', [expected('budget.items.0.quantity', 'quantity_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-QTY-BOOL');
    $q->props['budget']->props['items'][0]->props['quantity'] = true;
    $add('quantity-boolean', 'Quantity cannot be a boolean', [expected('budget.items.0.quantity', 'quantity_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-QTY-NULL');
    $q->props['budget']->props['items'][0]->props['quantity'] = null;
    $add('quantity-null', 'Quantity cannot be null', [expected('budget.items.0.quantity', 'quantity_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-QTY-FRAC');
    $q->props['budget']->props['items'][0]->props['quantity'] = num('2.5');
    $add('quantity-fractional', 'Quantity must be a whole number of units', [expected('budget.items.0.quantity', 'quantity_not_integer')], $q);

    $q = baseQuote('PRE-ERR-QTY-NEG');
    $q->props['budget']->props['items'][0]->props['quantity'] = -1;
    $add('quantity-negative', 'Quantity cannot be negative', [expected('budget.items.0.quantity', 'quantity_not_positive')], $q);

    $q = baseQuote('PRE-ERR-QTY-ZERO');
    $q->props['budget']->props['items'][0]->props['quantity'] = 0;
    $add('quantity-zero', 'Quantity must be at least 1', [expected('budget.items.0.quantity', 'quantity_not_positive')], $q);

    $q = baseQuote('PRE-ERR-PRICE');
    unset($q->props['budget']->props['items'][0]->props['unit_price']);
    $add('missing-unit-price', 'unit_price is required', [expected('budget.items.0.unit_price', 'required')], $q);

    $q = baseQuote('PRE-ERR-PRICE-STRING');
    $q->props['budget']->props['items'][0]->props['unit_price'] = '18000.00';
    $add('price-string', 'unit_price must be a JSON number, not text', [expected('budget.items.0.unit_price', 'price_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-PRICE-NULL');
    $q->props['budget']->props['items'][0]->props['unit_price'] = null;
    $add('price-null', 'unit_price cannot be null', [expected('budget.items.0.unit_price', 'price_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-PRICE-NEG');
    $q->props['budget']->props['items'][0]->props['unit_price'] = num('-1.00');
    $add('price-negative', 'unit_price cannot be negative', [expected('budget.items.0.unit_price', 'price_negative')], $q);

    $q = baseQuote('PRE-ERR-PRICE-SCALE');
    $q->props['budget']->props['items'][0]->props['unit_price'] = num('10.125');
    $add('price-three-decimals', 'Money amounts have at most two decimal places', [expected('budget.items.0.unit_price', 'price_scale')], $q);

    $q = baseQuote('PRE-ERR-ITEM-CCY');
    unset($q->props['budget']->props['items'][0]->props['currency']);
    $add('missing-item-currency', 'Item currency is required', [expected('budget.items.0.currency', 'required')], $q);

    $q = baseQuote('PRE-ERR-ITEM-GBP');
    $q->props['budget']->props['items'][0]->props['currency'] = 'GBP';
    $add('item-currency-unsupported', 'Item currency outside ARS, USD and EUR', [expected('budget.items.0.currency', 'unsupported_currency', 'GBP')], $q);

    $q = baseQuote('PRE-ERR-ITEM-CASE');
    $q->props['budget']->props['items'][0]->props['currency'] = 'usd';
    $add('item-currency-lowercase', 'Currency codes are uppercase ISO codes', [expected('budget.items.0.currency', 'unsupported_currency', 'usd')], $q);

    $q = baseQuote('PRE-ERR-RATES');
    unset($q->props['exchange_rates']);
    $add('missing-exchange-rates', 'exchange_rates is required', [expected('exchange_rates', 'required')], $q);

    $q = baseQuote('PRE-ERR-RATES-TYPE');
    $q->props['exchange_rates'] = [];
    $add('exchange-rates-not-object', 'exchange_rates must be an object, not an array', [expected('exchange_rates', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-CCY');
    unset($q->props['currency']);
    $add('missing-quote-currency', 'Output currency is required', [expected('currency', 'required')], $q);

    $q = baseQuote('PRE-ERR-CCY-BLANK');
    $q->props['currency'] = '';
    $add('quote-currency-blank', 'Output currency cannot be blank', [expected('currency', 'blank')], $q);

    $q = baseQuote('PRE-ERR-CCY-GBP');
    $q->props['currency'] = 'GBP';
    $q->props['budget']->props['items'][0]->props['currency'] = 'USD';
    $add(
        'quote-currency-unsupported',
        'Unsupported output currency is not also reported as a missing rate',
        [expected('currency', 'unsupported_currency', 'GBP')],
        $q,
    );

    $q = baseQuote('PRE-ERR-CCY-TYPE');
    $q->props['currency'] = 1;
    $add('quote-currency-not-string', 'Output currency must be a string', [expected('currency', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-RULES');
    unset($q->props['rules']);
    $add('missing-rules', 'rules is required and may be an empty array', [expected('rules', 'required')], $q);

    $q = baseQuote('PRE-ERR-RULES-TYPE');
    $q->props['rules'] = obj(['type' => 'tax']);
    $add('rules-not-array', 'rules must be an array', [expected('rules', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-RULE-TYPE');
    $q->props['rules'] = [1];
    $add('rule-not-object', 'Each rule must be an object', [expected('rules.0', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-RULE-MISSING-TYPE');
    $q->props['rules'] = [obj(['percent' => 10])];
    $add('missing-rule-type', 'Rule type is required', [expected('rules.0.type', 'required')], $q);

    $q = baseQuote('PRE-ERR-RULE-TYPE-NUM');
    $q->props['rules'] = [obj(['type' => 1])];
    $add('rule-type-not-string', 'Rule type must be a string', [expected('rules.0.type', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-RULE-UNKNOWN');
    $q->props['rules'] = [obj(['type' => 'percentage_surcharge', 'percent' => 10])];
    $add(
        'unknown-rule-type',
        'percentage_surcharge is not one of the six challenge rule types',
        [expected('rules.0.type', 'unknown_rule_type', 'percentage_surcharge')],
        $q,
    );

    $q = baseQuote('PRE-ERR-RULE-UNKNOWN-FEE');
    $q->props['rules'] = [obj(['type' => 'fixed_fee', 'amount' => num('10.00')])];
    $add(
        'unknown-rule-type-fixed-fee',
        'fixed_fee is not one of the six challenge rule types',
        [expected('rules.0.type', 'unknown_rule_type', 'fixed_fee')],
        $q,
    );

    $q = baseQuote('PRE-ERR-PAIR-LOWER');
    $q->props['exchange_rates'] = obj(['usd_ars' => num('1450.00')]);
    $add('malformed-pair-lowercase', 'Pair keys are uppercase BASE_QUOTE', [expected('exchange_rates.usd_ars', 'malformed_pair_key')], $q);

    $q = baseQuote('PRE-ERR-PAIR-DASH');
    $q->props['exchange_rates'] = obj(['USD-ARS' => num('1450.00')]);
    $add('malformed-pair-dash', 'Pair keys use an underscore separator', [expected('exchange_rates.USD-ARS', 'malformed_pair_key')], $q);

    $q = baseQuote('PRE-ERR-PAIR-COMPACT');
    $q->props['exchange_rates'] = obj(['USDARS' => num('1450.00')]);
    $add('malformed-pair-compact', 'Pair keys need a separator', [expected('exchange_rates.USDARS', 'malformed_pair_key')], $q);

    $q = baseQuote('PRE-ERR-PAIR-SHORT');
    $q->props['exchange_rates'] = obj(['US_ARS' => num('1450.00')]);
    $add('malformed-pair-short', 'Each side of a pair is a 3-letter code', [expected('exchange_rates.US_ARS', 'malformed_pair_key')], $q);

    $q = baseQuote('PRE-ERR-PAIR-LONG');
    $q->props['exchange_rates'] = obj(['USD_ARS_EUR' => num('1450.00')]);
    $add('malformed-pair-long', 'A pair key has exactly two codes', [expected('exchange_rates.USD_ARS_EUR', 'malformed_pair_key')], $q);

    $q = baseQuote('PRE-ERR-PAIR-SAME');
    $q->props['exchange_rates'] = obj(['USD_USD' => num('1.00')]);
    $add('invalid-pair-same-currency', 'A pair must join two different currencies', [expected('exchange_rates.USD_USD', 'invalid_pair', 'USD_USD')], $q);

    $q = baseQuote('PRE-ERR-PAIR-GBP');
    $q->props['exchange_rates'] = obj(['GBP_ARS' => num('1800.00')]);
    $add('unsupported-pair-currency', 'Both sides of a pair must be supported currencies', [expected('exchange_rates.GBP_ARS', 'unsupported_currency', 'GBP')], $q);

    $q = baseQuote('PRE-ERR-RATE-ZERO');
    $q->props['exchange_rates'] = obj(['USD_ARS' => 0]);
    $add('rate-zero', 'An exchange rate of zero is rejected', [expected('exchange_rates.USD_ARS', 'rate_not_positive')], $q);

    $q = baseQuote('PRE-ERR-RATE-NEG');
    $q->props['exchange_rates'] = obj(['USD_ARS' => num('-1450.00')]);
    $add('rate-negative', 'A negative exchange rate is rejected', [expected('exchange_rates.USD_ARS', 'rate_not_positive')], $q);

    $q = baseQuote('PRE-ERR-RATE-STRING');
    $q->props['exchange_rates'] = obj(['USD_ARS' => '1450.00']);
    $add('rate-string', 'An exchange rate must be a JSON number', [expected('exchange_rates.USD_ARS', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-RATE-NULL');
    $q->props['exchange_rates'] = obj(['USD_ARS' => null]);
    $add('rate-null', 'An exchange rate cannot be null', [expected('exchange_rates.USD_ARS', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-RATE-BOOL');
    $q->props['exchange_rates'] = obj(['USD_ARS' => true]);
    $add('rate-boolean', 'An exchange rate cannot be a boolean', [expected('exchange_rates.USD_ARS', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-MISSING-EMPTY');
    $q->props['budget']->props['items'][0]->props['currency'] = 'USD';
    $q->props['currency'] = 'ARS';
    $add('missing-rate-empty', 'USD item priced in ARS with an empty rate object', [expected('exchange_rates', 'missing_rate', 'USD_ARS')], $q);

    $q = baseQuote('PRE-ERR-MISSING-OTHER');
    $q->props['budget']->props['items'][0]->props['currency'] = 'USD';
    $q->props['currency'] = 'ARS';
    $q->props['exchange_rates'] = rates(['EUR_ARS' => '1562.10']);
    $add('missing-rate-unrelated-pair', 'A present but unrelated pair does not cover the item', [expected('exchange_rates', 'missing_rate', 'USD_ARS')], $q);

    $q = baseQuote('PRE-ERR-NO-TRIANGLE');
    $q->props['budget']->props['items'][0]->props['currency'] = 'USD';
    $q->props['currency'] = 'EUR';
    $q->props['exchange_rates'] = rates(['USD_ARS' => '1450.00', 'EUR_ARS' => '1562.10']);
    $add(
        'missing-rate-no-triangulation',
        'USD to EUR is not implied by USD_ARS and EUR_ARS',
        [expected('exchange_rates', 'missing_rate', 'USD_EUR')],
        $q,
    );

    $q = baseQuote('PRE-ERR-FIXED-RATE');
    $q->props['rules'] = [fixedDiscount('100.00', 'USD')];
    $add('missing-rate-fixed-discount', 'A fixed discount in another currency needs a pair', [expected('exchange_rates', 'missing_rate', 'USD_ARS')], $q);

    $q = baseQuote('PRE-ERR-BAD-KEY-RATE');
    $q->props['budget']->props['items'][0]->props['currency'] = 'USD';
    $q->props['exchange_rates'] = obj(['usd_ars' => num('1450.00')]);
    $add(
        'malformed-key-does-not-satisfy-rate',
        'A malformed key is not a usable USD_ARS rate',
        [
            expected('exchange_rates.usd_ars', 'malformed_pair_key'),
            expected('exchange_rates', 'missing_rate', 'USD_ARS'),
        ],
        $q,
    );

    $q = baseQuote('PRE-ERR-ZERO-UNUSABLE');
    $q->props['budget']->props['items'][0]->props['currency'] = 'USD';
    $q->props['exchange_rates'] = obj(['USD_ARS' => 0]);
    $add(
        'zero-rate-does-not-satisfy',
        'A zero rate does not satisfy the required pair',
        [
            expected('exchange_rates.USD_ARS', 'rate_not_positive'),
            expected('exchange_rates', 'missing_rate', 'USD_ARS'),
        ],
        $q,
    );

    $q = baseQuote('PRE-ERR-PERCENT-NEG');
    $q->props['rules'] = [percentage(-5)];
    $add('percent-negative', 'A percentage cannot be negative', [expected('rules.0.percent', 'percent_out_of_range')], $q);

    $q = baseQuote('PRE-ERR-PERCENT-HIGH');
    $q->props['rules'] = [percentage(150)];
    $add('percent-over-100', 'A percentage cannot exceed 100', [expected('rules.0.percent', 'percent_out_of_range')], $q);

    $q = baseQuote('PRE-ERR-PERCENT-STRING');
    $q->props['rules'] = [obj(['type' => 'percentage_discount', 'percent' => '15'])];
    $add('percent-string', 'A percentage must be a JSON number', [expected('rules.0.percent', 'percent_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-PERCENT-SCALE');
    $q->props['rules'] = [percentage(num('10.125'))];
    $add('percent-three-decimals', 'Percentages have at most two decimal places', [expected('rules.0.percent', 'percent_scale')], $q);

    $q = baseQuote('PRE-ERR-PERCENT-MISSING');
    $q->props['rules'] = [obj(['type' => 'percentage_discount'])];
    $add('missing-percent', 'percentage_discount requires percent', [expected('rules.0.percent', 'required')], $q);

    $q = baseQuote('PRE-ERR-CAT-PERCENT');
    $q->props['rules'] = [obj(['type' => 'category_discount', 'category' => 'catering'])];
    $add('missing-category-discount-percent', 'category_discount requires percent', [expected('rules.0.percent', 'required')], $q);

    $q = baseQuote('PRE-ERR-CAP-NEG');
    $q->props['rules'] = [cap(-1)];
    $add('max-percent-negative', 'discount_cap max_percent cannot be negative', [expected('rules.0.max_percent', 'percent_out_of_range')], $q);

    $q = baseQuote('PRE-ERR-CAP-HIGH');
    $q->props['rules'] = [cap(101)];
    $add('max-percent-over-100', 'discount_cap max_percent cannot exceed 100', [expected('rules.0.max_percent', 'percent_out_of_range')], $q);

    $q = baseQuote('PRE-ERR-CAP-STRING');
    $q->props['rules'] = [obj(['type' => 'discount_cap', 'max_percent' => '20'])];
    $add('max-percent-string', 'max_percent must be a JSON number', [expected('rules.0.max_percent', 'percent_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-CAP-MISSING');
    $q->props['rules'] = [obj(['type' => 'discount_cap'])];
    $add('missing-max-percent', 'discount_cap requires max_percent', [expected('rules.0.max_percent', 'required')], $q);

    $q = baseQuote('PRE-ERR-BUY-PAY');
    $q->props['rules'] = [promo('mobiliario', 2, 2)];
    $add('buy-pay-equal', 'pay must be lower than buy', [expected('rules.0', 'buy_pay_invalid', 'out_of_range')], $q);

    $q = baseQuote('PRE-ERR-BUY-ZERO');
    $q->props['rules'] = [promo('mobiliario', 0, 0)];
    $add('buy-zero', 'buy must be at least 1', [expected('rules.0', 'buy_pay_invalid', 'out_of_range')], $q);

    $q = baseQuote('PRE-ERR-PAY-NEG');
    $q->props['rules'] = [obj(['type' => 'quantity_promo', 'category' => 'mobiliario', 'buy' => 3, 'pay' => -1])];
    $add('pay-negative', 'pay cannot be negative', [expected('rules.0', 'buy_pay_invalid', 'out_of_range')], $q);

    $q = baseQuote('PRE-ERR-BUY-FRAC');
    $q->props['rules'] = [obj([
        'type' => 'quantity_promo',
        'category' => 'mobiliario',
        'buy' => num('3.5'),
        'pay' => 1,
    ])];
    $add('buy-fractional', 'buy and pay are whole numbers', [expected('rules.0.buy', 'buy_pay_invalid', 'not_integer')], $q);

    $q = baseQuote('PRE-ERR-BUY-STRING');
    $q->props['rules'] = [obj(['type' => 'quantity_promo', 'category' => 'mobiliario', 'buy' => '3', 'pay' => 1])];
    $add('buy-string', 'buy must be a JSON number', [expected('rules.0.buy', 'invalid_type')], $q);

    $q = baseQuote('PRE-ERR-BUY-MISSING');
    $q->props['rules'] = [obj(['type' => 'quantity_promo', 'category' => 'mobiliario', 'pay' => 1])];
    $add('missing-buy', 'quantity_promo requires buy', [expected('rules.0.buy', 'required')], $q);

    $q = baseQuote('PRE-ERR-PAY-MISSING');
    $q->props['rules'] = [obj(['type' => 'quantity_promo', 'category' => 'mobiliario', 'buy' => 3])];
    $add('missing-pay', 'quantity_promo requires pay', [expected('rules.0.pay', 'required')], $q);

    $q = baseQuote('PRE-ERR-PROMO-CAT');
    $q->props['rules'] = [obj(['type' => 'quantity_promo', 'buy' => 3, 'pay' => 2])];
    $add('missing-promo-category', 'quantity_promo requires category', [expected('rules.0.category', 'required')], $q);

    $q = baseQuote('PRE-ERR-DISC-CAT');
    $q->props['rules'] = [obj(['type' => 'category_discount', 'percent' => 10])];
    $add('missing-discount-category', 'category_discount requires category', [expected('rules.0.category', 'required')], $q);

    $q = baseQuote('PRE-ERR-AMOUNT-NEG');
    $q->props['rules'] = [fixedDiscount('-50.00', 'ARS')];
    $add('fixed-amount-negative', 'A fixed discount amount cannot be negative', [expected('rules.0.amount', 'amount_negative')], $q);

    $q = baseQuote('PRE-ERR-AMOUNT-STRING');
    $q->props['rules'] = [obj(['type' => 'fixed_discount', 'amount' => '50.00', 'currency' => 'ARS'])];
    $add('fixed-amount-string', 'A fixed discount amount must be a JSON number', [expected('rules.0.amount', 'amount_not_numeric')], $q);

    $q = baseQuote('PRE-ERR-AMOUNT-SCALE');
    $q->props['rules'] = [fixedDiscount('10.125', 'ARS')];
    $add('fixed-amount-three-decimals', 'A fixed discount amount has at most two decimal places', [expected('rules.0.amount', 'amount_scale')], $q);

    $q = baseQuote('PRE-ERR-AMOUNT-MISSING');
    $q->props['rules'] = [obj(['type' => 'fixed_discount', 'currency' => 'ARS'])];
    $add('missing-fixed-amount', 'fixed_discount requires amount', [expected('rules.0.amount', 'required')], $q);

    $q = baseQuote('PRE-ERR-FIXED-CCY');
    $q->props['rules'] = [obj(['type' => 'fixed_discount', 'amount' => num('50.00')])];
    $add('missing-fixed-currency', 'fixed_discount requires currency', [expected('rules.0.currency', 'required')], $q);

    $q = baseQuote('PRE-ERR-FIXED-GBP');
    $q->props['rules'] = [fixedDiscount('50.00', 'GBP')];
    $add('fixed-currency-unsupported', 'Fixed discount currency outside ARS, USD and EUR', [expected('rules.0.currency', 'unsupported_currency', 'GBP')], $q);

    $q = baseQuote('PRE-ERR-TAX-NAME');
    $q->props['rules'] = [obj(['type' => 'tax', 'percent' => 21])];
    $add('missing-tax-name', 'tax requires name', [expected('rules.0.name', 'required')], $q);

    $q = baseQuote('PRE-ERR-TAX-NAME-BLANK');
    $q->props['rules'] = [tax('  ', 21)];
    $add('tax-name-blank', 'tax name cannot be blank', [expected('rules.0.name', 'blank')], $q);

    $q = baseQuote('PRE-ERR-TAX-PERCENT');
    $q->props['rules'] = [obj(['type' => 'tax', 'name' => 'IVA'])];
    $add('missing-tax-percent', 'tax requires percent', [expected('rules.0.percent', 'required')], $q);

    $q = baseQuote('PRE-ERR-SECOND');
    $second = item(2, 'Silla tiffany', 'mobiliario', 4, '1800.00', 'ARS');
    $second->props['quantity'] = -3;
    $q->props['budget']->props['items'][] = $second;
    $add('second-item-quantity-negative', 'A later item can be the one that fails', [expected('budget.items.1.quantity', 'quantity_not_positive')], $q);

    $q = baseQuote('PRE-ERR-MULTI');
    $q->props['budget']->props['items'][0]->props['quantity'] = -1;
    $q->props['budget']->props['items'][0]->props['unit_price'] = num('-5.00');
    $q->props['rules'] = [obj(['type' => 'nope'])];
    $add(
        'multi-error-quantity-price-and-unknown-rule',
        'Independent errors on the same quote are all reported',
        [
            expected('budget.items.0.quantity', 'quantity_not_positive'),
            expected('budget.items.0.unit_price', 'price_negative'),
            expected('rules.0.type', 'unknown_rule_type', 'nope'),
        ],
        $q,
    );

    $add('quote-not-object', 'The quote itself must be an object', [expected('quote', 'invalid_type')], [1, 2]);

    return $cases;
}

/**
 * @param  list<array{path: string, code: string, detail: string}|stdClass>  $errors
 * @return list<string>
 */
function errorSignature(array $errors): array
{
    $out = [];
    foreach ($errors as $error) {
        if ($error instanceof stdClass) {
            $path = $error->path;
            $code = $error->code;
            $detail = $error->detail ?? '';
        } else {
            $path = $error['path'];
            $code = $error['code'];
            $detail = $error['detail'] ?? '';
        }
        $out[] = $path.'|'.$code.'|'.$detail;
    }
    sort($out);

    return $out;
}

/** @param list<stdClass> $quotes */
function assertValidCoverage(array $quotes): void
{
    $pairs = [];
    $inverseOnly = [];
    $ruleTypes = [];
    $flags = [
        'empty rules' => false,
        'empty rates' => false,
        'zero price' => false,
        'percent 0' => false,
        'percent 100' => false,
        'cap 0' => false,
        'cap 100' => false,
        'pay 0' => false,
        'absent category' => false,
        'remainder' => false,
        'long event' => false,
        'long word' => false,
        'dup budget id' => false,
        'fixed converted' => false,
        'all six rules' => false,
        'zero amount' => false,
    ];
    $ids = [];
    $currencies = [];
    $itemCurrencies = [];

    foreach ($quotes as $quote) {
        $currencies[$quote->currency] = true;
        if ($quote->rules === []) {
            $flags['empty rules'] = true;
        }
        if (get_object_vars($quote->exchange_rates) === []) {
            $flags['empty rates'] = true;
        }
        if (mb_strlen($quote->budget->event) > 112) {
            $flags['long event'] = true;
        }
        $ids[$quote->budget->id] = ($ids[$quote->budget->id] ?? 0) + 1;
        $types = [];
        $itemCats = [];
        foreach ($quote->budget->items as $line) {
            $itemCurrencies[$line->currency] = true;
            $itemCats[$line->category] = true;
            if ($line->unit_price == 0) {
                $flags['zero price'] = true;
            }
            foreach (preg_split('/\s+/u', trim($line->description)) ?: [] as $word) {
                if (mb_strlen($word) > 28) {
                    $flags['long word'] = true;
                }
            }
        }
        foreach (array_keys(get_object_vars($quote->exchange_rates)) as $key) {
            $pairs[$key] = true;
        }
        foreach ($quote->rules as $rule) {
            $types[$rule->type] = true;
            $ruleTypes[$rule->type] = true;
            if (in_array($rule->type, ['percentage_discount', 'category_discount', 'tax'], true)) {
                if ($rule->percent == 0) {
                    $flags['percent 0'] = true;
                }
                if ($rule->percent == 100) {
                    $flags['percent 100'] = true;
                }
            }
            if ($rule->type === 'discount_cap' && $rule->max_percent == 0) {
                $flags['cap 0'] = true;
            }
            if ($rule->type === 'discount_cap' && $rule->max_percent == 100) {
                $flags['cap 100'] = true;
            }
            if ($rule->type === 'quantity_promo' && $rule->pay === 0) {
                $flags['pay 0'] = true;
            }
            if ($rule->type === 'quantity_promo') {
                foreach ($quote->budget->items as $line) {
                    if ($line->category === $rule->category && $rule->buy > 0 && $line->quantity % $rule->buy !== 0) {
                        $flags['remainder'] = true;
                    }
                }
            }
            if (in_array($rule->type, ['category_discount', 'quantity_promo'], true) && ! isset($itemCats[$rule->category])) {
                $flags['absent category'] = true;
            }
            if ($rule->type === 'fixed_discount' && $rule->amount == 0) {
                $flags['zero amount'] = true;
            }
            if ($rule->type === 'fixed_discount' && $rule->currency !== $quote->currency && $rule->amount > 0) {
                $flags['fixed converted'] = true;
            }
        }
        if (count($types) === 6) {
            $flags['all six rules'] = true;
        }

        $needed = [];
        foreach ($quote->budget->items as $line) {
            if ($line->currency !== $quote->currency) {
                $needed[$line->currency.'_'.$quote->currency] = true;
            }
        }
        foreach ($quote->rules as $rule) {
            if ($rule->type === 'fixed_discount' && $rule->currency !== $quote->currency) {
                $needed[$rule->currency.'_'.$quote->currency] = true;
            }
        }
        $have = array_fill_keys(array_keys(get_object_vars($quote->exchange_rates)), true);
        foreach (array_keys($needed) as $direct) {
            [$base, $quoteCurrency] = explode('_', $direct);
            $inverse = $quoteCurrency.'_'.$base;
            if (! isset($have[$direct]) && isset($have[$inverse])) {
                $inverseOnly[$direct] = true;
            }
        }
    }

    foreach ($ids as $count) {
        if ($count >= 2) {
            $flags['dup budget id'] = true;
        }
    }

    foreach (['USD_ARS', 'EUR_ARS', 'ARS_USD', 'ARS_EUR', 'USD_EUR', 'EUR_USD'] as $pair) {
        if (! isset($pairs[$pair])) {
            throw new RuntimeException('Valid dataset is missing pair key '.$pair);
        }
    }
    foreach (['ARS', 'USD', 'EUR'] as $from) {
        foreach (['ARS', 'USD', 'EUR'] as $to) {
            if ($from === $to) {
                continue;
            }
            $direct = $from.'_'.$to;
            if (! isset($inverseOnly[$direct])) {
                throw new RuntimeException('Valid dataset is missing an inverse-only '.$direct.' conversion');
            }
        }
    }
    foreach (['quantity_promo', 'category_discount', 'percentage_discount', 'fixed_discount', 'discount_cap', 'tax'] as $type) {
        if (! isset($ruleTypes[$type])) {
            throw new RuntimeException('Valid dataset is missing rule type '.$type);
        }
    }
    foreach (['ARS', 'USD', 'EUR'] as $currency) {
        if (! isset($currencies[$currency]) || ! isset($itemCurrencies[$currency])) {
            throw new RuntimeException('Valid dataset is missing currency '.$currency);
        }
    }
    foreach ($flags as $label => $ok) {
        if (! $ok) {
            throw new RuntimeException('Valid dataset is missing edge: '.$label);
        }
    }
}

$dir = $argv[1] ?? __DIR__;
if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
    throw new RuntimeException('Cannot create '.$dir);
}

$validator = new QuoteDatasetValidator;
$quotes = array_merge(catalogQuotes(), seededQuotes(new Rng(DATASET_SEED), SEEDED_QUOTE_COUNT));
$decodedQuotes = [];
foreach ($quotes as $index => $candidate) {
    $decoded = json_decode(JsonEncoder::encode($candidate), false, 512, JSON_THROW_ON_ERROR);
    $errors = $validator->validateQuote($decoded);
    if ($errors !== []) {
        fwrite(STDERR, 'Valid quote '.$index.' failed: '.json_encode($errors).PHP_EOL);
        exit(1);
    }
    $decodedQuotes[] = $decoded;
}
assertValidCoverage($decodedQuotes);

$validDocument = obj([
    'version' => 'v2',
    'generated_at' => GENERATED_AT,
    'count' => count($quotes),
    'quotes' => $quotes,
]);

$cases = invalidCases();
foreach ($cases as $case) {
    $decodedQuote = json_decode(JsonEncoder::encode($case->props['quote']), false, 512, JSON_THROW_ON_ERROR);
    $actual = errorSignature($validator->validateQuote($decodedQuote));
    $expectedDecoded = json_decode(JsonEncoder::encode($case->props['expected_errors']), false, 512, JSON_THROW_ON_ERROR);
    $expectedSignature = errorSignature($expectedDecoded);
    if ($actual === [] || $actual !== $expectedSignature) {
        fwrite(STDERR, 'Case '.$case->props['id'].PHP_EOL);
        fwrite(STDERR, 'actual: '.implode(', ', $actual).PHP_EOL);
        fwrite(STDERR, 'expected: '.implode(', ', $expectedSignature).PHP_EOL);
        exit(1);
    }
}

$invalidDocument = obj([
    'version' => 'v2-invalid',
    'generated_at' => GENERATED_AT,
    'description' => 'Labeled invalid quotes. Each case must fail with exactly the expected errors.',
    'count' => count($cases),
    'cases' => $cases,
]);

file_put_contents($dir.'/valid-v2.json', JsonEncoder::encode($validDocument).PHP_EOL);
file_put_contents($dir.'/invalid-v2.json', JsonEncoder::encode($invalidDocument).PHP_EOL);

fwrite(STDOUT, 'valid quotes: '.count($quotes).PHP_EOL);
fwrite(STDOUT, 'invalid cases: '.count($cases).PHP_EOL);
