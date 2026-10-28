# Implementation Plan: Quotes MVP (backend)

**Branch**: `001-quotes-mvp` | **Date**: 2026-10-28 | **Spec**: [spec.md](./spec.md) (Draft)

**Input**: Feature specification from `/specs/001-quotes-mvp/spec.md`

> Plan en borrador: depende de que la spec pase a *Approved*. Repo: `mottor-ai-back`.

## Summary

API REST en Laravel que expone quotes paginadas con conversión de moneda, el detalle con su desglose ordenado de reglas comerciales calculado por un motor extensible (Strategy/OCP), la importación asíncrona de datasets en batches con errores por fila y el ticket editable. Todos los montos se calculan con bcmath a través del value object `Money` y viajan como decimal string. El contrato es `contracts/openapi.yaml`.

## Technical Context

**Language/Version**: PHP 8.3+, Laravel 12+ [NEEDS CLARIFICATION: versión exigida por el challenge]

**Primary Dependencies**: Laravel (HTTP, Eloquent, Queue, Validation), ext-bcmath, `league/openapi-psr7-validator` (o `osteel/openapi-httpfoundation-testing`) para contract tests

**Storage**: MySQL 8 o PostgreSQL 16 con columnas `DECIMAL(18,4)` para montos y `DECIMAL(18,6)` para tasas; SQLite en memoria para tests [NEEDS CLARIFICATION: motor de DB]

**Testing**: Pest 3 (unit, feature y contract), Larastan, Pint

**Target Platform**: servidor Linux / Docker (Laravel Sail) [NEEDS CLARIFICATION: ¿se entrega con Docker?]

**Project Type**: web-service (API JSON consumida por `mottor-ai-front`)

**Performance Goals**: listado p95 < 300 ms con 10.000 quotes; import de 5.000 filas en < 60 s

**Constraints**: sin float en montos; reglas deterministas; batches transaccionales de 500 filas; archivo de hasta 20 MB

**Scale/Scope**: 3 pantallas, unos 9 endpoints, 5 tipos de regla iniciales

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cómo se cumple | Estado |
|---|---|---|
| I. SOLID | `RuleEngine` depende de `CommercialRule` (interface); estrategias registradas con tags; `ExchangeRateProvider`, `DatasetParser` y `ImportRowValidator` como interfaces chicas; controllers finos | ✅ |
| II. Spec antes que código | spec en Draft → no se implementa hasta tener aprobación | ⏳ bloqueado por aprobación |
| III. Contrato fuente de verdad | `openapi.yaml` compartido; contract tests por endpoint (T014) | ✅ |
| IV. Sin float | `Money` sobre bcmath; casts `DECIMAL` como string; Resources serializan string | ✅ (redondeo pendiente de aclarar) |
| V. Tests | Pest primero en dominio (T020 a T024) | ✅ |
| VI. Simplicidad | sin CQRS ni event sourcing; colas `database` | ✅ |
| VII. Capas | Http → Application → Domain ← Infrastructure | ✅ |

## Architecture

```text
Http (Controllers, FormRequests, Resources)
   │  sólo validan, delegan y serializan
   ▼
Application (Actions / Use cases)
   ListQuotes, GetQuoteDetail, StartImport, ProcessImportBatch, CancelImport, UpdateTicket
   │
   ▼
Domain (sin Laravel)
   Money (bcmath) · QuoteCalculation (DTO) · RuleEngine
   interface CommercialRule { code(); priority(); appliesTo(ctx): bool; apply(Money base, ctx): RuleResult }
   PercentageDiscountRule · FixedDiscountRule · PercentageSurchargeRule · FixedFeeRule · TaxRule
   interface ExchangeRateProvider · interface DatasetParser · interface ImportRowValidator · TicketRenderer
   ▲
Infrastructure
   Eloquent models/repositories · DbExchangeRateProvider · JsonDatasetParser · ProcessImportChunk (Job)
```

- **Motor de reglas (Strategy + OCP)**: `RuleEngine::calculate(Money $subtotal, RuleContext $ctx): QuoteCalculation` recorre las reglas activas ordenadas por `priority`, y por cada regla aplicable registra `{order, code, name, type, base, delta, runningTotal}`. Para agregar una regla se crea la clase y se registra con `$this->app->tag([...], 'commercial-rules')`; el motor no cambia.
- **Conversión de moneda**: los montos se calculan y persisten en la moneda original. La conversión a `displayCurrency` se hace al serializar, con `ExchangeRateProvider::rateFor(from, to, date)`, multiplicando por la tasa con bcmath y redondeando a los `decimals` de la moneda.
- **Import**: `StartImport` guarda el archivo y crea `imports` (`queued`) → `Bus::batch()` de jobs `ProcessImportChunk` (500 filas, una transacción cada uno) → cada job valida filas, persiste las válidas (calculando reglas) y registra los `import_errors` → contadores atómicos en `imports`. Para cancelar: `$batch->cancel()` y los jobs chequean `batch()->cancelled()`.
- **Ticket**: `TicketRenderer` arma el texto a `width` columnas (header, items, subtotal, reglas, total, footer) con word-wrap.

## Project Structure

### Documentation (this feature)

```text
specs/001-quotes-mvp/
├── spec.md              # compartida con front
├── plan.md              # este archivo
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/openapi.yaml  # compartido con front (idéntico)
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/Api/   # CurrencyController, ClientController, QuoteController, QuoteTicketController, ImportController
│   ├── Requests/          # ListQuotesRequest, StoreImportRequest, UpdateTicketRequest
│   └── Resources/         # QuoteSummaryResource, QuoteDetailResource, AppliedRuleResource, ImportResource...
├── Application/
│   ├── Quotes/            # ListQuotes, GetQuoteDetail, ConvertQuoteAmounts
│   ├── Imports/           # StartImport, ProcessImportBatch, CancelImport
│   └── Tickets/           # GetTicket, UpdateTicket
├── Domain/
│   ├── Money/             # Money, Currency, RoundingMode
│   ├── Rules/             # CommercialRule, RuleEngine, RuleContext, RuleResult, Strategies/*
│   ├── Currency/          # ExchangeRateProvider (interface)
│   ├── Imports/           # DatasetParser, ImportRowValidator, RowError (interfaces + DTOs)
│   └── Tickets/           # TicketRenderer
├── Infrastructure/
│   ├── Persistence/       # Eloquent models (Quote, QuoteItem, Client, ...)
│   ├── Currency/          # DbExchangeRateProvider
│   ├── Imports/           # JsonDatasetParser, QuoteRowValidator
│   └── Jobs/              # ProcessImportChunk
└── Providers/             # DomainServiceProvider (bindings + tagged rules)
database/{migrations,seeders,factories}/
tests/{Unit,Feature,Contract}/
```

**Structure Decision**: un solo proyecto Laravel con capas por carpeta en `app/`. No se usan paquetes ni módulos separados (YAGNI).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Capa `Domain` separada de Eloquent | El motor de reglas tiene que testearse en aislamiento y extenderse por OCP | Calcular en el modelo Eloquent acopla reglas a la persistencia y rompe SRP |
