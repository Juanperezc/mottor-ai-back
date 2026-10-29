# Fixtures: 001-quotes-mvp

Copiada de `mottor-ai-specs@3ef1401a69e29d831d43ba67364c10858c6a2865`. Desde ese cambio, las fixtures pertenecen a este repositorio y se mantienen en `tests/Fixtures/`.

Entradas y salidas **exactas** que usan los tests del back y del front (principio VIII). Los esperados salen del enunciado y de un cálculo de referencia independiente, nunca de la salida del código que se testea.

| Archivo | Qué es | Lo usan |
|---|---|---|
| `official-case.input.json` | Presupuesto del enunciado (`PRE-2026-0418`, Agencia Norte), tal como se manda a `POST /quotes` | A01, A05, A15, A19, A32 |
| `official-case.expected.json` | Detalle esperado (igual al JSON del enunciado): subtotal 4878500.00, total 4722388.00, seis pasos | A01, A30, A32, A33 |
| `official-case.list-usd.expected.json` | Fila del listado en USD: original ARS 4722388.00 → total 3256.82 | A25 |
| `second-case.input.json` | Segundo caso: ítems en ARS, USD y EUR, las 6 reglas y el tope después del IVA | A02, A03, A04, A06, A07, A08, A10 |
| `second-case.expected.json` | Subtotal 7927225.00, total 7832871.49 | A02, A10, A15 |
| `ticket-cases.json` | 17 casos de `lines` (28×4, code points, `…`): los 3 del enunciado y los bordes | A27, A28, A29 |

## Reglas

- El campo `id` de los `*.expected.json` se ignora al comparar: lo asigna la base de datos.
- Los montos están como números JSON con 2 decimales. En el back se compara el **cuerpo crudo** de la respuesta, no el JSON decodificado (A32).
- `tests/Fixtures/` es la única ubicación de fixtures para los tests del back. Se conservan los mismos IDs BT de `specs/001-quotes-mvp/tasks.md`.
- Para cambiar una fixture, se actualiza acá con el requisito que lo justifica y su test asociado.
