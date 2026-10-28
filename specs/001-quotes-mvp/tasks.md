---
description: "Tasks backend para 001-quotes-mvp (mottor-ai-back)"
---

# Tasks: Quotes MVP (backend)

**Input**: Design documents from `/specs/001-quotes-mvp/`

**Prerequisites**: plan.md, spec.md (**Approved**: hoy está en Draft, así que estas tareas NO arrancan), research.md, data-model.md, contracts/openapi.yaml

**Tests**: obligatorios según la constitución (principio V). Los tests de dominio se escriben primero y tienen que fallar antes de implementar.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: paralelizable (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1 Dashboard · US2 Import · US3 Detail/Reglas/Ticket
- `(deps: ...)`: tareas que tienen que estar terminadas antes

## Phase 1: Setup

- [ ] T001 Crear el proyecto Laravel (PHP 8.3+, Laravel 12+) con ext-bcmath y API routes en `routes/api.php`
- [ ] T002 [P] Configurar Pest, Larastan (nivel 6) y Pint; script `composer test` (deps: T001)
- [ ] T003 [P] Configurar CI (GitHub Actions): pint --test, phpstan y pest (deps: T001)
- [ ] T004 [P] Copiar `specs/001-quotes-mvp/contracts/openapi.yaml` y agregar el helper de contract testing en `tests/Contract/OpenApiAssertions.php` (deps: T002)

## Phase 2: Foundational (bloquea todas las US)

- [ ] T010 Tests unitarios de `Money` (suma, resta, multiplicación, porcentaje, redondeo, sin float) en `tests/Unit/Domain/MoneyTest.php` (deps: T002)
- [ ] T011 Implementar `app/Domain/Money/Money.php` sobre bcmath (deps: T010)
- [ ] T012 [P] Migraciones `currencies`, `exchange_rates`, `clients` + modelos + factories (deps: T001)
- [ ] T013 Migraciones `quotes`, `quote_items`, `commercial_rules`, `quote_applied_rules` + modelos + factories (deps: T012)
- [ ] T014 [P] Middleware o handler de errores JSON: `422 {message, errors}` y `{message}` para 404/409/500 en `bootstrap/app.php` (deps: T001)
- [ ] T015 [P] Interfaz `ExchangeRateProvider` + `DbExchangeRateProvider` + tests (deps: T011, T012)
- [ ] T016 `DomainServiceProvider` con bindings de interfaces (deps: T015)
- [ ] T017 Seeders: monedas, tasas, clientes, reglas y quotes demo (deps: T013)

**Checkpoint**: dominio base y persistencia listos.

## Phase 3: User Story 3 (núcleo): motor de reglas comerciales 🎯

> Se adelanta porque US1 y US2 necesitan `subtotal` y `total` calculados.

- [ ] T020 [P] [US3] Tests de `RuleEngine`: orden por prioridad, base/delta/acumulado, sin reglas → total = subtotal, invariante total = último acumulado, en `tests/Unit/Domain/Rules/RuleEngineTest.php` (deps: T011)
- [ ] T021 [P] [US3] Tests por estrategia (`PercentageDiscountRule`, `FixedDiscountRule`, `PercentageSurchargeRule`, `FixedFeeRule`, `TaxRule`) en `tests/Unit/Domain/Rules/Strategies/` (deps: T011)
- [ ] T022 [US3] Interfaz `CommercialRule`, DTOs `RuleContext` y `RuleResult`, y `RuleEngine` en `app/Domain/Rules/` (deps: T020)
- [ ] T023 [P] [US3] Implementar las 5 estrategias en `app/Domain/Rules/Strategies/` (deps: T021, T022)
- [ ] T024 [US3] Registrar estrategias con tag `commercial-rules` y `RuleFactory` desde `commercial_rules.type`; test "regla nueva sin tocar el motor" (SC-004) (deps: T016, T023)
- [ ] T025 [US3] Acción `CalculateQuote` (items → subtotal → reglas → snapshot `quote_applied_rules`) + tests (deps: T013, T024)

## Phase 4: User Story 1: Quotes Dashboard (P1)

- [ ] T030 [P] [US1] Feature + contract tests de `GET /api/currencies` (deps: T004, T012)
- [ ] T031 [P] [US1] Feature + contract tests de `GET /api/clients?search=&page=` (deps: T004, T012)
- [ ] T032 [P] [US1] Feature + contract tests de `GET /api/quotes` (paginación, `clientId`, `currency` con conversión, orden date desc, `meta`) (deps: T004, T017)
- [ ] T033 [US1] `CurrencyController` + `CurrencyResource` (deps: T030)
- [ ] T034 [US1] `ClientController` con búsqueda paginada + `ClientResource` (deps: T031)
- [ ] T035 [US1] `ListQuotesRequest`, acción `ListQuotes`, `ConvertQuoteAmounts` y `QuoteSummaryResource` (montos string, `itemsCount`) (deps: T015, T025, T032)
- [ ] T036 [US1] Índices `(client_id, date)` y verificación de p95 < 300 ms con 10.000 quotes (SC-001) (deps: T035)

**Checkpoint**: el dashboard se puede consumir con datos de seed.

## Phase 5: User Story 3 (API): Quote Detail y Ticket

- [ ] T040 [P] [US3] Feature + contract tests de `GET /api/quotes/{id}` (items, appliedRules ordenadas, 404) (deps: T004, T025)
- [ ] T041 [US3] `GetQuoteDetail` + `QuoteDetailResource` + `AppliedRuleResource` con conversión de moneda (deps: T035, T040)
- [ ] T042 [P] [US3] Tests unitarios de `TicketRenderer` (40 columnas, word-wrap, montos alineados) (deps: T011)
- [ ] T043 [US3] `TicketRenderer` en `app/Domain/Tickets/` (deps: T042)
- [ ] T044 [US3] Feature + contract tests de `GET` y `PATCH /api/quotes/{id}/ticket` (deps: T004, T043)
- [ ] T045 [US3] `QuoteTicketController`, `UpdateTicketRequest` (header y footer ≤ 500) y acciones `GetTicket` / `UpdateTicket` (deps: T044)

## Phase 6: User Story 2: Dataset Import (P2)

- [ ] T050 [US2] Migraciones `imports`, `import_errors` + modelos + tabla `job_batches` (deps: T013)
- [ ] T051 [P] [US2] Interfaces `DatasetParser` e `ImportRowValidator` + DTO `RowError` en `app/Domain/Imports/` (deps: T011)
- [ ] T052 [P] [US2] Tests de `JsonDatasetParser` (streaming, JSON inválido → failed) con fixtures en `tests/fixtures/` (deps: T051)
- [ ] T053 [P] [US2] Tests de `QuoteRowValidator` (errores por campo, `items[n].unitPrice`) (deps: T051)
- [ ] T054 [US2] Implementar `JsonDatasetParser` y `QuoteRowValidator` (deps: T052, T053)
- [ ] T055 [US2] Feature + contract tests: `POST /api/imports` (202, 413, 422), `GET /api/imports/{id}`, `POST /cancel` y `GET /errors` (deps: T004, T050)
- [ ] T056 [US2] Acción `StartImport` + `ImportController@store` (guarda el archivo y crea `Bus::batch`) (deps: T054, T055)
- [ ] T057 [US2] Job `ProcessImportChunk` (500 filas, transacción por chunk, `CalculateQuote`, contadores atómicos, chequeo de `cancelled()`) (deps: T025, T056)
- [ ] T058 [US2] `CancelImport` + endpoint de errores paginado + transición de estados final (deps: T057)
- [ ] T059 [US2] Test de carga: 5.000 filas en < 60 s (SC-002) (deps: T058)

## Phase 7: Polish & Cross-Cutting

- [ ] T070 [P] Verificar que `openapi.yaml` sea idéntico al del repo front (checksum en CI)
- [ ] T071 [P] README del repo: setup, colas y cómo correr tests
- [ ] T072 [P] Revisar la cobertura de `Domain/` y `Application/` (≥ 80%)
- [ ] T073 Revisión SOLID (MAI - Architect) y actualización del *Complexity Tracking*

## Dependencies & Execution Order

- Setup (T001 a T004) → Foundational (T010 a T017) → Motor de reglas (T020 a T025) → US1 (T030 a T036) → US3 API (T040 a T045). US2 (T050 a T059) puede correr en paralelo a US1 y US3 una vez listo T025.
- Paralelizables típicos: T030/T031/T032; T020/T021; T052/T053; T042 con cualquier tarea de US1.
- El front puede avanzar en paralelo usando mocks MSW generados desde `openapi.yaml`, sin esperar al back.
