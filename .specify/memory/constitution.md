<!--
Sync Impact Report
- Version change: (template) → 1.0.0-draft
- Principios agregados: I SOLID, II Spec antes que código, III Contrato de API, IV Precisión monetaria, V Tests obligatorios, VI Simplicidad, VII Arquitectura backend en capas
- Secciones agregadas: Restricciones técnicas, Flujo de desarrollo, Governance
- Templates: .specify/templates/plan-template.md ✅ (el Constitution Check usa estos principios) · tasks-template.md ✅ (los tests NO son opcionales en este proyecto)
- TODO diferidos: TODO(RATIFICATION_DATE): pendiente de la aprobación de Juan
-->
# Mottor AI Backend Constitution (mottor-ai-back)

> Proyecto de prueba: *Mottor.ai Senior Full-Stack Challenge*. Backend Laravel. Esta constitución comparte su base (principios I a VI) con `mottor-ai-front`.

## Core Principles

### I. SOLID como diseño central (NON-NEGOTIABLE)
- **SRP**: cada clase tiene una sola razón de cambio. Los controllers y los componentes de página solo orquestan; la lógica vive en servicios, acciones y hooks.
- **OCP / Strategy**: el motor de reglas comerciales se extiende agregando una nueva estrategia (`CommercialRule`) registrada en el contenedor. Agregar un tipo de regla NO DEBE requerir modificar el motor (`RuleEngine`) ni las reglas existentes.
- **LSP**: toda implementación de una interfaz es intercambiable sin condicionales por tipo (`instanceof`, `switch` sobre `type` fuera de la factory o el registry).
- **ISP**: interfaces chicas y enfocadas (por ejemplo `CommercialRule`, `ExchangeRateProvider`, `ImportRowValidator`), sin interfaces "dios".
- **DIP**: las capas altas dependen de abstracciones. Las dependencias se inyectan (contenedor de Laravel en back; props, context y hooks en front); nada de `new` de servicios con efectos ni facades dentro del dominio.
- Toda violación se justifica en la tabla *Complexity Tracking* del `plan.md`.

### II. Spec antes que código (NON-NEGOTIABLE)
- Flujo obligatorio: `constitution → spec → clarify → plan → tasks → implement`.
- Ninguna tarea de implementación arranca sin una `spec.md` con estado **Approved** firmada por Juan. Mientras una spec esté en **Draft** solo se permite trabajo exploratorio descartable (spikes), nunca en `main`.
- Cada PR referencia su spec y sus IDs de tarea (`specs/NNN-feature`, `T0xx`).
- Las ambigüedades se marcan con `[NEEDS CLARIFICATION: ...]` y no se resuelven inventando requisitos: se preguntan.

### III. El contrato de API es la fuente de verdad
- `specs/NNN-feature/contracts/openapi.yaml` (OpenAPI 3.1) es el acuerdo entre back y front y es **idéntico byte a byte** en ambos repos.
- Un cambio de contrato es un cambio de spec: se versiona, se aprueba y se replica en ambos repos en el mismo ciclo.
- El back valida sus respuestas contra el contrato (contract tests). El front tipa su cliente HTTP a partir del contrato (tipos generados) y sus mocks (MSW) se basan en él.
- Convenciones: JSON en `camelCase`, fechas ISO 8601, errores de validación con formato Laravel (`422 { message, errors }`).

### IV. Precisión monetaria: sin float
- Todo monto viaja en el API como **decimal string** (`"1234.50"`) y las tasas de cambio como decimal string de 6 decimales.
- El back calcula con `bcmath` (o enteros en unidades menores) a través de un value object `Money`. Está prohibido usar `float` o `double` en cálculos monetarios y en columnas de DB (se usa `DECIMAL`).
- El front **no recalcula** montos de negocio: muestra lo que devuelve el back y formatea con `Intl.NumberFormat`. La única aritmética permitida en el front es la verificación de QA (Σ deltas = total), sin float (por ejemplo, enteros en centavos con `BigInt`).
- El modo y el momento del redondeo se documentan en la spec. [NEEDS CLARIFICATION: modo de redondeo (HALF_UP vs HALF_EVEN) y si se redondea por regla o solo al final]

### V. Tests obligatorios
- Back: **Pest** (sobre PHPUnit). Unit tests para cada estrategia de regla, el motor y `Money`; feature tests por endpoint; contract tests contra `openapi.yaml`.
- Front: **Vitest + Testing Library** (testear comportamiento, no implementación), `vitest-axe` para accesibilidad y MSW para el API.
- Para lógica de dominio se escribe primero el test (red → green → refactor).
- Un PR sin tests para la funcionalidad nueva no se mergea. El CI corre lint, tipos y tests en cada PR.

### VI. Simplicidad
- YAGNI: se implementa lo que pide la spec aprobada y nada más. Nada de abstracciones especulativas, salvo los puntos de extensión que exige el principio I (reglas, proveedores de tasa, parsers de import).
- Código, identificadores, commits y OpenAPI en inglés; documentación SDD en español.

### VII. Arquitectura backend en capas
- `Http` (controllers finos, FormRequests, API Resources) → `Application` (acciones y casos de uso) → `Domain` (entidades, value objects, `RuleEngine`, interfaces) ← `Infrastructure` (Eloquent, colas, parsers, proveedores de tasas).
- El dominio no depende de Laravel: Eloquent no entra al `RuleEngine`; las reglas operan sobre DTOs y `Money`.
- Los bindings interfaz → implementación se registran en Service Providers. Las estrategias de regla se registran con *tagged services*.

## Restricciones técnicas

- PHP 8.3+ y Laravel 12+. [NEEDS CLARIFICATION: versión exacta de PHP/Laravel exigida por el challenge]
- DB relacional con columnas `DECIMAL` para montos. [NEEDS CLARIFICATION: MySQL vs PostgreSQL; ¿se acepta SQLite para la entrega?]
- Colas con el driver `database` por defecto (sin dependencias extra para correr el challenge).
- Calidad: Laravel Pint (PSR-12), Larastan nivel 6 o más, Pest con cobertura de 80% o más en `Domain/` y `Application/`.

## Flujo de desarrollo

1. `/speckit-constitution`: este documento.
2. `/speckit-specify` → `specs/NNN-feature/spec.md` (Draft).
3. `/speckit-clarify`: resolver `[NEEDS CLARIFICATION]` con Juan → spec **Approved**.
4. `/speckit-plan` → `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md` (con el *Constitution Check*).
5. `/speckit-tasks` → `tasks.md`. Opcional: `/speckit-analyze` para chequear consistencia.
6. `/speckit-implement`: implementar tarea por tarea, una rama por feature (`NNN-feature`) y PRs chicos.
- Gates de PR: CI verde, spec y tarea referenciadas, contrato sin divergencias con el front y review de MAI - Architect.

## Governance

- Esta constitución está por encima de cualquier otra práctica del repo. Ante un conflicto, gana la constitución.
- **Enmiendas**: se proponen por PR que modifique este archivo, con justificación, impacto (Sync Impact Report) y plan de migración si corresponde. Requieren la aprobación explícita de Juan. Si la enmienda toca los principios I a VI, se replica en `mottor-ai-front` en el mismo ciclo.
- **Versionado semántico**: MAJOR si se elimina o redefine un principio de forma incompatible, MINOR si se agrega un principio o sección, PATCH si se aclara o corrige redacción.
- **Cumplimiento**: cada `plan.md` incluye un *Constitution Check* (gate antes de Phase 0 y después de Phase 1), y cada review de PR verifica el cumplimiento. Las excepciones se documentan en *Complexity Tracking*.

**Version**: 1.0.0-draft | **Ratified**: TODO(RATIFICATION_DATE): pendiente de la aprobación de Juan | **Last Amended**: 2026-10-28
