# Research (Phase 0): Quotes MVP (backend)

Cada decisión sigue el formato de Spec Kit: Decision / Rationale / Alternatives considered.

## R1. Aritmética monetaria
- **Decision**: value object `Money` inmutable sobre `bcmath` (scale interna de 4 o más y redondeo explícito al serializar). Columnas `DECIMAL` casteadas como string.
- **Rationale**: es exacto, viene nativo en PHP y cumple el principio IV.
- **Alternatives**: `brick/money` (sólido, pero suma una dependencia; queda como opción si Juan la prefiere); enteros en centavos (complica las tasas de 6 decimales).
- **Pendiente**: [NEEDS CLARIFICATION: modo de redondeo y si se redondea por regla o al final]

## R2. Motor de reglas
- **Decision**: Strategy + registro por tags del contenedor; orden por `priority` persistida en `commercial_rules`.
- **Rationale**: OCP (una regla nueva no toca el motor) y testeable por estrategia.
- **Alternatives**: Chain of Responsibility (mezcla orden y aplicación) o un `switch` por tipo (viola OCP).
- **Pendiente**: [NEEDS CLARIFICATION: catálogo de reglas, condiciones (por cliente, monto, fecha) y si se configuran en DB o en código]

## R3. Import asíncrono
- **Decision**: `Bus::batch()` con jobs de 500 filas, cola `database`, polling cada 1 s desde el front.
- **Rationale**: permite cancelar, da progreso nativo (`processedJobs`) y no requiere Redis.
- **Alternatives**: SSE o WebSockets (más infraestructura); que el front parta el archivo (mueve validación al cliente y abre problemas de consistencia).

## R4. Parseo del dataset
- **Decision**: parser JSON en streaming (`halaxa/json-machine`) detrás de la interfaz `DatasetParser`.
- **Rationale**: un archivo de 20 MB no se carga entero en memoria, y la interfaz deja abierta la puerta a CSV (OCP).
- **Alternatives**: `json_decode` del archivo entero (más simple y aceptable hasta unos 20 MB; se puede usar si Juan prefiere menos dependencias).

## R5. Conversión de moneda
- **Decision**: tabla `exchange_rates` por fecha, y la conversión se hace al serializar.
- **Pendiente**: [NEEDS CLARIFICATION: fuente y fecha de la tasa; qué hacer si falta la tasa]

## R6. Contract testing
- **Decision**: validar cada response de los feature tests contra `contracts/openapi.yaml` con un validador PSR-7 o HttpFoundation.
- **Rationale**: principio III; detecta divergencias con el front.
