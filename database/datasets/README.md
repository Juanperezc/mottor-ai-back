# Datasets de prueba para la importación

Variantes del `dataset.json` de ejemplo (enunciado v2) para probar la importación desde el front (`/import`).
Todos tienen **el mismo formato** que el ejemplo: `{"version", "generated_at", "count", "quotes": [...]}`. Cada presupuesto
tiene `budget` (`id`, `client`, `event`, `items`), `exchange_rates`, `currency` y `rules`; los montos son números
(`12800.0`) y las tasas van como `{"USD_ARS": 1514.12}`. Los presupuestos válidos se copiaron tal cual del ejemplo.

> Los archivos de `tests/Fixtures/` **no** sirven para importar: son casos sueltos para PHPUnit, sin la clave `quotes`.

El front parte cada archivo en lotes de 500:

- En modo **sincrónico** manda cada lote a `POST /quotes/batch`, que es atómico por lote.
- En modo **cola** manda el archivo entero a `POST /imports` y Horizon procesa los mismos lotes de 500.

Las rutas de error (`quotes.{n}...`) usan el índice **dentro del lote** (de 0 a 499), no el índice global del archivo.

## Resumen

| Archivo | Cantidad | Lotes | Modo recomendado | Resultado esperado |
|---|---|---|---|---|
| `valid-small.json` | 20 | 1 | Sincrónico | `201`, se importan los 20. Cubre salida en ARS/USD/EUR, ítems en las 3 monedas, la tasa inversa `ARS_USD`, los 6 tipos de regla, presupuestos sin reglas y `fixed_discount` en otra moneda |
| `valid-multi-batch.json` | 1.200 | 3 (500 + 500 + 200) | Cola (para ver el progreso por lote) | Los 3 lotes quedan `processed`, se importan los 1.200 y el estado es `completed` |
| `official-cases.json` | 2 | 1 | Sincrónico | `201`. `PRE-2026-0418`: total **4.722.388,00 ARS** (subtotal 4.878.500,00). `PRE-2026-0419`: total **7.832.871,49 ARS** (subtotal 7.927.225,00) |
| `errors-one-batch.json` | 1.200 | 3 | Los dos | Falla solo el **lote 2**: `422` en `quotes.242.budget.items.0.quantity` (`quantity: 0`; índice global 742, `PRE-2026-0743`). Los lotes 1 y 3 se importan: 700 presupuestos y 1 lote fallido (en cola queda `completed_with_errors`) |
| `errors-mixed.json` | 34 | 1 | Sincrónico (con el modal de errores) | `422` y no se guarda ninguno. Hay 25 inválidos, cada uno con un error distinto (ver la tabla de abajo), y 9 válidos |
| `ticket-edge.json` | 12 | 1 | Sincrónico | `201`, se importan los 12. Son textos al límite del ticket de 28 × 4 (ver la tabla de abajo) |
| `invalid-json.json` | — | — | Cualquiera | Lo frena el front con "El archivo no es un JSON válido." (es un JSON cortado a la mitad). Si se manda directo a `POST /imports`: `422` en `file` |
| `empty-quotes.json` | 0 | — | Cualquiera | Lo frena el front con "El archivo no tiene presupuestos." (`"quotes": []`). Si se manda directo a `POST /imports`: `422` en `quotes` |
| `wrong-shape.json` | 3 | — | Cualquiera | Lo frena el front con "El JSON tiene que ser {"quotes": [...]} o un array." (la lista está en `presupuestos` y falta la clave `quotes`). Si se manda directo a `POST /imports`: `422` en `file` |
| `too-many.json` (no se versiona, ver [Regenerarlos](#regenerarlos)) | 10.001 | 21 | Cola | `POST /imports` responde `422` en `quotes` ("between 1 and 10000"). En modo sincrónico **no** hay un límite total, así que se importan los 21 lotes (10.001 presupuestos) |

Nota: el front también acepta un array suelto `[...]` como archivo. Por eso `wrong-shape.json` usa otra clave raíz en lugar de un array.

## `errors-mixed.json`: errores por índice

El back valida en dos etapas, y eso se ve en el modal:

1. **Reglas de Laravel** (`QuoteValidationRules`): el primer `422` trae **todos** estos errores juntos (16 en total).
2. **Dominio** (normalizador de decimales, tasas, registro de reglas y total negativo): solo se evalúa cuando pasa la etapa 1, y corta en el **primer** presupuesto que falla.
   - Cada reintento muestra un error nuevo, en el orden de la columna "Orden" (9 reintentos más).
   - Con todo corregido, o sacando los inválidos, el lote da `201`.

| Índice | `budget.id` | Etapa | Orden | Error puesto a propósito | Ruta del `422` |
|---|---|---|---|---|---|
| 1 | `PRE-2026-2002` | 1 · Laravel | 1.º envío | falta budget.client (requerido) | `quotes.1.budget.client` |
| 2 | `PRE-2026-2003` | 1 · Laravel | 1.º envío | quantity 0 | `quotes.2.budget.items.0.quantity` |
| 4 | `PRE-2026-2005` | 1 · Laravel | 1.º envío | quantity negativa (-3) | `quotes.4.budget.items.0.quantity` |
| 5 | `PRE-2026-2006` | 1 · Laravel | 1.º envío | quantity con decimales (2.5), tiene que ser entero | `quotes.5.budget.items.0.quantity` |
| 6 | `PRE-2026-2007` | 1 · Laravel | 1.º envío | moneda de ítem inválida ("usd", minúsculas) | `quotes.6.budget.items.0.currency` |
| 8 | `PRE-2026-2009` | 1 · Laravel | 1.º envío | moneda de salida inválida ("PESOS") | `quotes.8.currency` |
| 9 | `PRE-2026-2010` | 1 · Laravel | 1.º envío | porcentaje fuera de rango (120) | `quotes.9.rules.0.percent` |
| 10 | `PRE-2026-2011` | 1 · Laravel | 1.º envío | max_percent negativo (-5) en la regla 6 | `quotes.10.rules.6.max_percent` |
| 12 | `PRE-2026-2013` | 1 · Laravel | 1.º envío | tasa en 0 (USD_ARS) | `quotes.12.exchange_rates.USD_ARS` |
| 13 | `PRE-2026-2014` | 1 · Laravel | 1.º envío | unit_price negativo (-100.0) | `quotes.13.budget.items.0.unit_price` |
| 14 | `PRE-2026-2015` | 1 · Laravel | 1.º envío | budget.items vacío | `quotes.14.budget.items` |
| 15 | `PRE-2026-2016` | 1 · Laravel | 1.º envío | unit_price como texto con formato local ("12.800,00") | `quotes.15.budget.items.0.unit_price` |
| 17 | `PRE-2026-2018` | 1 · Laravel | 1.º envío | falta budget.event (requerido) | `quotes.17.budget.event` |
| 18 | `PRE-2026-2019` | 1 · Laravel | 1.º envío | falta la clave exchange_rates (tiene que estar, aunque sea {}) | `quotes.18.exchange_rates` |
| 19 | `PRE-2026-2020` | 1 · Laravel | 1.º envío | quantity_promo con buy 0 | `quotes.19.rules.0.buy` |
| 20 | `PRE-2026-2021` | 1 · Laravel | 1.º envío | fixed_discount con amount negativo | `quotes.20.rules.0.amount` |
| 22 | `PRE-2026-2023` | 2 · Dominio | reintento 1 | type de regla desconocido ("bonus") | `quotes.22.rules.0.type` |
| 23 | `PRE-2026-2061` | 2 · Dominio | reintento 2 | falta la tasa: ítem en EUR y solo hay USD_ARS | `quotes.23.exchange_rates` |
| 25 | `PRE-2026-2025` | 2 · Dominio | reintento 3 | total negativo (fixed_discount mayor al subtotal) | `quotes.25.rules` |
| 26 | `PRE-2026-2026` | 2 · Dominio | reintento 4 | unit_price en notación científica (1e-07): el back solo acepta decimales planos | `quotes.26.budget.items.0.unit_price` |
| 27 | `PRE-2026-2027` | 2 · Dominio | reintento 5 | quantity_promo con pay > buy (2x3) | `quotes.27.rules.0.pay` |
| 29 | `PRE-2026-2029` | 2 · Dominio | reintento 6 | tax sin name | `quotes.29.rules.0.name` |
| 30 | `PRE-2026-2030` | 2 · Dominio | reintento 7 | category_discount sin category | `quotes.30.rules.0.category` |
| 31 | `PRE-2026-2031` | 2 · Dominio | reintento 8 | clave de tasa mal formada ("USDARS" en vez de "USD_ARS") | `quotes.31.exchange_rates.USDARS` |
| 32 | `PRE-2026-2032` | 2 · Dominio | reintento 9 | percentage_discount sin percent | `quotes.32.rules.0.percent` |

Válidos: índices 0, 3, 7, 11, 16, 21, 24, 28, 33.

Notas:
- El índice 26 (`unit_price: 1e-07`) es el caso de un número que el back rechaza.
  - El decoder deja los números como texto, y el normalizador solo acepta decimales planos (`^-?\d+(\.\d+)?$`).
  - `JSON.stringify` del front lo sigue mandando como `1e-7`, así que también falla desde el front.
  - Un `12800.0` o un `12.345` **no** fallan.
- El índice 5 (`quantity: 2.5`) es el caso de un decimal donde va un entero.
- Las tasas faltantes (índice 23) se reportan en `exchange_rates`, sin el par (FX-03).

## `ticket-edge.json`: textos del ticket

- El texto va en `budget.event` y también en la descripción del primer ítem (`budget.items.0.description`, con un máximo de 255).
- Todo está en NFC y el conteo se hace por code point.
- Los renglones esperados son los que devuelve el `TicketLineFormatter` del back.

| `budget.id` | Caso | Code points | Renglones esperados |
|---|---|---|---|
| `PRE-2026-T001` | enunciado-1 | 46 | `Reunión anual de accionistas` / `· Auditorio Norte` |
| `PRE-2026-T002` | 4-renglones-justos | 106 | `Fiesta corporativa de fin de` / `año para toda la planta con` / `cena de gala y show en vivo` / `en el salón principal` |
| `PRE-2026-T003` | 5-renglones-trunca | 121 | `Fiesta corporativa de fin de` / `año para toda la planta con` / `cena de gala y show en vivo` / `en el salón principal de…` |
| `PRE-2026-T004` | palabra-29 | 33 | `ABCDEFGHIJKLMNOPQRSTUVWXYZAB` / `B fin` |
| `PRE-2026-T005` | palabra-28 | 28 | `AAAAAAAAAAAAAAAAAAAAAAAAAAAA` |
| `PRE-2026-T006` | palabra-larga-87 | 89 | `Electroencefalografistasssss` / `ssssssssssssssssssssssssssss` / `ssssssssssssssssssssssssssss` / `ss ok` |
| `PRE-2026-T007` | acentos-nfc | 70 | `Ñandú y pingüino: canción,` / `jamón, corazón, acción y` / `emoción en Neuquén` |
| `PRE-2026-T008` | emojis | 62 | `Fiesta 🎉 con 🍕 pizzas 🍷` / `vinos y 🎶 música en vivo` / `hasta las 4 🕓` |
| `PRE-2026-T009` | emoji-28-cp | 32 | `🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉` / `fin` |
| `PRE-2026-T010` | bandera-y-zwj | 33 | `Brindis 🇦🇷 equipo 👨‍👩‍👧 y` / `familia` |
| `PRE-2026-T011` | blancos-multiples | 27 | `Menú de tres pasos` |
| `PRE-2026-T012` | 28-exactos-mas-1 | 45 | `Lanzamiento de producto · 12` / `personas en sala` |

Notas:
- La bandera 🇦🇷 cuenta 2 code points y la familia 👨‍👩‍👧 cuenta 5 (con los ZWJ), aunque cada una se vea como un solo símbolo.
- `PRE-2026-T011` tiene tabs, saltos de línea y espacios dobles a propósito.

## Cómo se verificaron

1. **Pipeline real del back, sin guardar nada.** Se usó un script local de solo lectura (no versionado), corrido dentro del contenedor de Sail.
   - Parte en lotes de 500 y aplica lo mismo que `POST /quotes/batch` y `ProcessImportBatch`: `ExactJsonDecoder`, las reglas de `QuoteValidationRules` y después `DecimalInputNormalizer` + `QuoteCalculator`.
   - Se corrió sobre los archivos crudos y sobre la versión que manda el front (`JSON.parse` + `JSON.stringify`, emulado con Node). El resultado fue el mismo.
2. **`sail artisan quotes:validate-dataset database/datasets/<archivo>.json`.** Da `invalid_quotes: 0` en todos los válidos y en `too-many.json`.
   - Ojo: este comando **no** aplica las reglas de Laravel, solo el normalizador y el motor. No detecta `quantity: 0`, campos faltantes, monedas en minúscula, etc.
   - Por eso marca 0 inválidos en `errors-one-batch.json` y 21 de 25 en `errors-mixed.json`.
   - Para los errores, la referencia es el punto 1.
3. **Prueba manual desde el front.** El 2026-10-28, Juan importó todos los archivos desde `mottor-ai-front` (`/import`), y todos dieron el resultado de la tabla.

## Regenerarlos

Los JSON versionados se generaron una vez a partir del `dataset.json` de ejemplo (sha256 `fb8f930b…f82`), y se editan a mano si cambia un caso.

`too-many.json` pesa unos 10 MB, así que **no se versiona** (está en `.gitignore`). Para generarlo:

```bash
# 10.001 presupuestos repitiendo los de valid-multi-batch.json (ids PRE-2026-0001 a PRE-2026-10001)
./vendor/bin/sail php database/datasets/generate-too-many.php

# O, con el dataset completo montado en /inputs (DATASET_PATH), los 10.000 del ejemplo + PRE-2026-10001
./vendor/bin/sail php database/datasets/generate-too-many.php /inputs/dataset.json
```

El segundo argumento opcional cambia la cantidad (por defecto, 10001). La segunda variante es la que se probó desde el front.
