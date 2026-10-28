# Data Model: Quotes MVP (backend)

Convenciones: tablas en `snake_case`, montos `DECIMAL(18,4)` (nunca float), tasas `DECIMAL(18,6)`, timestamps estándar de Laravel. El API serializa en camelCase (ver `contracts/openapi.yaml`).

## Tablas

### currencies
| Columna | Tipo | Notas |
|---|---|---|
| code | CHAR(3) PK | ISO 4217 |
| name | VARCHAR(64) | |
| symbol | VARCHAR(8) | |
| decimals | TINYINT | 0 a 4 |

Seed: USD (default), EUR, ARS. [NEEDS CLARIFICATION: lista definitiva (Q1)]

### exchange_rates
| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| base_currency | CHAR(3) FK currencies | |
| quote_currency | CHAR(3) FK currencies | |
| rate | DECIMAL(18,6) | 1 base = rate quote |
| rate_date | DATE | |
| | UNIQUE(base_currency, quote_currency, rate_date) | |

### clients
| id BIGINT PK | name VARCHAR(255) UNIQUE, indexado para la búsqueda |

### quotes
| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| code | VARCHAR(32) UNIQUE | p. ej. `Q-000123` |
| date | DATE | índice (orden desc) |
| client_id | FK clients | índice compuesto (client_id, date) |
| event_description | TEXT | multilínea |
| currency | CHAR(3) FK currencies | moneda original |
| subtotal | DECIMAL(18,4) | Σ line_total |
| total | DECIMAL(18,4) | último running_total o subtotal |
| ticket_header | TEXT NULL | [NEEDS CLARIFICATION: alcance del ticket (Q11)] |
| ticket_footer | TEXT NULL | |
| import_id | FK imports NULL | trazabilidad |

[NEEDS CLARIFICATION: ¿status de la quote? (Q4)]

### quote_items
| id | quote_id FK (cascade) | description VARCHAR(255) | quantity DECIMAL(12,4) | unit_price DECIMAL(18,4) | line_total DECIMAL(18,4) | position SMALLINT |

### commercial_rules (configuración)
| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| code | VARCHAR(64) UNIQUE | |
| name | VARCHAR(255) | |
| type | VARCHAR(32) | resuelve la estrategia: `percentage_discount`, `fixed_discount`, `percentage_surcharge`, `fixed_fee`, `tax` |
| value | DECIMAL(18,6) | porcentaje o monto según el tipo |
| priority | INT | orden de aplicación (asc) |
| conditions | JSON NULL | [NEEDS CLARIFICATION: condiciones de aplicación (Q10)] |
| active | BOOLEAN | |

### quote_applied_rules (snapshot del cálculo)
| id | quote_id FK | order SMALLINT | rule_code | rule_name | rule_type | base DECIMAL(18,4) | delta DECIMAL(18,4) | running_total DECIMAL(18,4) |

Decisión: se persiste un snapshot al crear o importar la quote, para que cambios posteriores en `commercial_rules` no alteren quotes históricas. [NEEDS CLARIFICATION: ¿snapshot o recálculo en cada lectura?]

### imports
| Columna | Tipo | Notas |
|---|---|---|
| id | BIGINT PK | |
| status | VARCHAR(32) | `queued` · `processing` · `completed` · `completed_with_errors` · `failed` · `cancelled` |
| file_path | VARCHAR | storage local |
| batch_id | VARCHAR NULL | id de `Bus::batch` |
| batch_size | INT | 500 |
| total_rows, processed_rows, imported_rows, failed_rows | INT | contadores (incrementos atómicos) |
| total_batches, processed_batches | INT | propuesto (`x-proposed`) |
| failure_reason | TEXT NULL | |
| started_at, finished_at | TIMESTAMP NULL | |

### import_errors
| id | import_id FK (cascade) | row INT | batch INT | field VARCHAR NULL | message VARCHAR | value TEXT NULL | índice (import_id, row) |

## Transiciones de estado de Import

```text
queued ──► processing ──► completed
                │      └─► completed_with_errors (failed_rows > 0)
                ├──► failed     (archivo corrupto / schema inválido / excepción no recuperable)
                └──► cancelled  (POST /cancel; sin rollback)
queued ──► cancelled
```

## Reglas de validación (fila de dataset)
- `code`: requerido, ≤ 32, único. [NEEDS CLARIFICATION: política de duplicados]
- `date`: fecha ISO válida.
- `client`: requerido, ≤ 255 (si no existe, se crea).
- `currency`: existe en `currencies`.
- `items`: al menos 1; `quantity` decimal > 0; `unitPrice` decimal ≥ 0.
- Cada error genera `{ row, batch, field, message, value }`, por ejemplo `items[2].unitPrice`.

## Invariantes de dominio
- `line_total = quantity × unit_price` (bcmath).
- `subtotal = Σ line_total`.
- `running_total[n] = running_total[n-1] + delta[n]`, con `running_total[0] = subtotal`.
- `total = running_total[last]` (o `subtotal` si no hay reglas).
