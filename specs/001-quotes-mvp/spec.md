# Feature Specification: Quotes MVP (Dashboard, Dataset Import, Quote Detail)

**Feature Branch**: `001-quotes-mvp`

**Created**: 2026-10-28

**Status**: Draft

**Input**: Brief de Juan para el *Mottor.ai Senior Full-Stack Challenge* (Laravel + React/TypeScript) con 3 pantallas: Quotes Dashboard, Dataset Import y Quote Detail. Fuentes: `brief-uiux.md`, `docs/design.md` (MAI - UI/UX Designer) y `docs/api-contract-draft.md` (borrador v0 de MAI - Architect).

> ⚠️ **Todavía no tenemos el enunciado oficial del challenge.** Esta spec se arma desde el brief interno y los supuestos de diseño y arquitectura. Todo lo que no sale del brief está marcado con `[NEEDS CLARIFICATION: ...]` y **no** debe tomarse como requisito del enunciado. La spec pasa a *Approved* solo cuando Juan la valide contra el enunciado (`/speckit-clarify`).
>
> Documento compartido: copia idéntica en `mottor-ai-back` y `mottor-ai-front`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Explorar quotes en el dashboard (Priority: P1)

Como usuario, quiero ver la lista paginada de quotes, filtrarla por cliente y ver los montos en la moneda que elija, para encontrar rápido una cotización y entender su valor.

**Why this priority**: es la pantalla de entrada (lo pide el brief) y la que da valor visible aun sin import ni detalle. Junto con datos sembrados (seed) ya es un MVP demostrable.

**Independent Test**: con quotes sembradas en DB, abrir `/quotes`, paginar, filtrar por un cliente y cambiar la moneda; los resultados y montos cambian según el contrato.

**Acceptance Scenarios**:

1. **Given** existen más quotes que el tamaño de página, **When** abro el dashboard, **Then** veo la primera página ordenada por fecha descendente, el total de resultados y controles de paginación. [NEEDS CLARIFICATION: orden por defecto y si hay ordenamiento por columna (Q13)]
2. **Given** el dashboard cargado, **When** elijo un cliente en el filtro (búsqueda server-side), **Then** solo veo quotes de ese cliente y la paginación vuelve a la página 1. [NEEDS CLARIFICATION: ¿single-select o multi-select? Se asume single-select (Q12)]
3. **Given** el dashboard en USD, **When** cambio la moneda a EUR, **Then** subtotal y total se muestran convertidos por el backend y el header de columna indica la moneda. [NEEDS CLARIFICATION: ¿el selector convierte montos o filtra por moneda original? Se asume que convierte con tasas del backend (Q2)]
4. **Given** una quote con descripción de evento de varias líneas, **When** se muestra en la tabla, **Then** se respetan los saltos de línea, se trunca a 3 líneas y hay un control accesible "Ver más / Ver menos". [NEEDS CLARIFICATION: ¿la primera línea es el título del evento o hay un campo de título separado? (Q4)]
5. **Given** no hay quotes, o el filtro no tiene resultados, o falla el API, **When** se carga la pantalla, **Then** veo el estado vacío, sin resultados o de error correspondiente, con su acción (importar, limpiar filtros, reintentar).

---

### User Story 2 - Importar un dataset de quotes (Priority: P2)

Como usuario, quiero subir un archivo con quotes y ver el progreso por batches y los errores por fila, para cargar datos masivos y saber exactamente qué filas fallaron y por qué.

**Why this priority**: alimenta al dashboard y al detalle con datos reales. Es el punto técnico más exigente (colas, batches, validación), pero el dashboard puede demostrarse antes con seeds.

**Independent Test**: subir un archivo válido con algunas filas inválidas; ver avanzar el progreso hasta "completado con errores" y la tabla paginada de errores por fila; las filas válidas aparecen en el dashboard.

**Acceptance Scenarios**:

1. **Given** estoy en `/import`, **When** selecciono un archivo con formato o tamaño inválido, **Then** veo un error inline antes de subirlo. [NEEDS CLARIFICATION: formatos aceptados (¿solo JSON o también CSV?) y tamaño máximo; se asume JSON de hasta 20 MB (Q7)]
2. **Given** un archivo válido, **When** inicio el import, **Then** recibo un id de import y veo el progreso (filas procesadas/total y batch actual) actualizado por polling. [NEEDS CLARIFICATION: ¿el procesamiento en batches lo hace un job del backend (supuesto) o el front partiendo el archivo? ¿Tamaño de batch? Se asume 500 (Q8)]
3. **Given** un import en curso, **When** lo cancelo, **Then** se detienen los batches pendientes y veo cuántas filas quedaron importadas. [NEEDS CLARIFICATION: ¿cancelar es requisito? ¿Las filas ya importadas se revierten? Se asume que no hay rollback (Q8)]
4. **Given** un import terminado con filas inválidas, **When** veo el resultado, **Then** veo los totales (total, importadas, fallidas) y una tabla paginada de errores con fila, campo, mensaje y valor.
5. **Given** un import terminado, **When** elijo "Ver quotes", **Then** las quotes importadas aparecen en el dashboard.
6. **Given** un archivo con una quote cuyo `code` ya existe, **When** se procesa, **Then** el resultado sigue la política de duplicados definida. [NEEDS CLARIFICATION: ¿los duplicados se rechazan como error de fila, se actualizan (upsert) o se ignoran?]

---

### User Story 3 - Ver el detalle de una quote, sus reglas y su ticket (Priority: P3)

Como usuario, quiero ver una quote con sus items, el subtotal, el desglose ordenado de reglas comerciales y el total, y editar y previsualizar el ticket, para entender cómo se llegó al total y dejar el ticket listo.

**Why this priority**: es el núcleo de dominio (motor de reglas), pero depende de que existan quotes (US1/US2).

**Independent Test**: abrir `/quotes/{id}` de una quote sembrada con reglas conocidas; verificar items, subtotal, el desglose (orden, base, delta y acumulado) y que el último acumulado sea igual al total; editar el ticket, guardar y recargar.

**Acceptance Scenarios**:

1. **Given** una quote existente, **When** abro su detalle, **Then** veo código, cliente, fecha, descripción, items (descripción, cantidad, precio unitario, importe), subtotal y total en la moneda elegida.
2. **Given** una quote con reglas aplicables, **When** veo el desglose, **Then** las reglas aparecen en el orden de aplicación del backend, cada una con base, delta con signo y acumulado, y el último acumulado es igual al total. [NEEDS CLARIFICATION: catálogo real de reglas comerciales, su prioridad y condiciones; se asumen percentage_discount, fixed_discount, percentage_surcharge, fixed_fee y tax (Q10)]
3. **Given** una quote sin reglas aplicables, **When** veo el desglose, **Then** se indica "No se aplicaron reglas comerciales" y el total es igual al subtotal.
4. **Given** el editor de ticket, **When** modifico el texto, **Then** el preview (40 columnas, monoespaciado) se actualiza en vivo y se marca "Cambios sin guardar". [NEEDS CLARIFICATION: qué se edita del ticket (¿header/footer libres, plantilla con placeholders o líneas?), formato y ancho; se asume header/footer en texto plano y 40 columnas (Q11)]
5. **Given** cambios sin guardar en el ticket, **When** guardo, **Then** se persisten y al recargar veo el texto guardado y el `rendered` devuelto por el backend. [NEEDS CLARIFICATION: ¿el ticket se persiste en backend o es solo un preview local?]
6. **Given** un id inexistente, **When** abro el detalle, **Then** veo "Quote no encontrada" con acción para volver.

### Edge Cases

- Página fuera de rango (`page` mayor a `lastPage`): el front redirige a la última página válida.
- Moneda sin tasa de cambio para la fecha de la quote: [NEEDS CLARIFICATION: ¿error, usar la última tasa disponible o mostrar en la moneda original?]
- Montos con más decimales que los de la moneda, o redondeo de reglas porcentuales: [NEEDS CLARIFICATION: modo de redondeo (HALF_UP vs HALF_EVEN) y si se redondea por regla o al final]
- Reglas que llevarían el total a negativo: [NEEDS CLARIFICATION: ¿se acota el total a 0?]
- Archivo de import vacío, JSON mal formado o schema inválido: el import termina como `failed` con la causa.
- Import con todas las filas inválidas: `completed_with_errors` con `importedRows = 0`. [NEEDS CLARIFICATION: ¿o debería ser `failed`?]
- Dos imports concurrentes: [NEEDS CLARIFICATION: ¿se permiten o se bloquea con 409?]
- Descripción de evento vacía o muy larga, y ticket con líneas más largas que el ancho: el backend parte las líneas según `width` en `rendered`.

## Requirements *(mandatory)*

### Functional Requirements

**Dashboard (US1)**
- **FR-001**: El sistema DEBE listar quotes paginadas server-side con `page` y `perPage` (10/25/50/100, default 25) e informar el total de resultados.
- **FR-002**: El sistema DEBE permitir filtrar quotes por cliente con búsqueda de clientes server-side y paginada. [NEEDS CLARIFICATION: cantidad esperada de clientes y single vs multi-select (Q12)]
- **FR-003**: El sistema DEBE exponer la lista de monedas soportadas desde el API (no fija en el front), con USD por defecto. [NEEDS CLARIFICATION: monedas exigidas por el enunciado (Q1)]
- **FR-004**: El sistema DEBE devolver los montos convertidos a la moneda pedida (`currency`), junto con la moneda original, la tasa aplicada y la fecha de la tasa. [NEEDS CLARIFICATION: fuente de las tasas (tabla sembrada vs proveedor externo) y fecha de la tasa (de la quote vs actual) (Q2)]
- **FR-005**: El sistema DEBE preservar los saltos de línea de `eventDescription`, y la UI DEBE mostrarla multilínea con truncado accesible.
- **FR-006**: Cada fila DEBE mostrar código, fecha, cliente, descripción, cantidad de items, subtotal y total.

**Import (US2)**
- **FR-010**: El sistema DEBE aceptar un archivo de dataset, validar formato y tamaño, y encolar su procesamiento, respondiendo `202` con el id del import. [NEEDS CLARIFICATION: formato y schema del dataset (Q7)]
- **FR-011**: El sistema DEBE procesar el dataset en batches, cada uno en su propia transacción, y exponer el progreso (estado, filas totales, procesadas, importadas y fallidas, y batches). [NEEDS CLARIFICATION: tamaño de batch y si el front necesita el estado por batch (Q8)]
- **FR-012**: El sistema DEBE validar cada fila y registrar sus errores con número de fila, campo, mensaje y valor, consultables de forma paginada (Q9).
- **FR-013**: El sistema DEBERÍA permitir cancelar un import en curso sin revertir lo ya importado. [NEEDS CLARIFICATION: confirmar si cancelar es requisito (Q8)]
- **FR-014**: Al importar una quote, el sistema DEBE calcular y persistir su subtotal, el desglose de reglas y su total con el motor de reglas. [NEEDS CLARIFICATION: ¿el dataset trae las reglas aplicadas (o los totales) o el sistema los calcula?]

**Detalle, reglas y ticket (US3)**
- **FR-020**: El sistema DEBE devolver el detalle de una quote con sus items (descripción, cantidad, precio unitario e importe) y sus reglas aplicadas.
- **FR-021**: El sistema DEBE calcular el total aplicando reglas comerciales en un orden determinista y devolver por regla: orden, código, nombre, tipo, base, delta y acumulado. El último acumulado DEBE ser igual a `total`, y la UI NO DEBE recalcular.
- **FR-022**: El motor de reglas DEBE poder extenderse con un nuevo tipo de regla sin modificar el motor (Strategy/OCP, constitución principio I).
- **FR-023**: Todos los montos DEBEN calcularse sin float y transmitirse como decimal string (constitución principio IV).
- **FR-024**: El sistema DEBE permitir leer y editar el ticket de una quote (`header`, `footer`) y devolver su versión `rendered` de ancho fijo. [NEEDS CLARIFICATION: alcance del editor de ticket (Q11)]
- **FR-025**: La UI DEBE mostrar un preview en vivo del ticket mientras se edita, con indicador de cambios sin guardar y aviso al salir.

**Transversales**
- **FR-030**: Los errores de validación DEBEN seguir el formato `422 { message, errors }`, y los demás errores `{ message }` con 404, 409 o 500.
- **FR-031**: Las 3 pantallas DEBEN cumplir WCAG 2.2 AA y ser responsive (mobile, tablet y desktop) según `docs/design.md`.
- **FR-032**: Autenticación: [NEEDS CLARIFICATION: ¿el challenge exige auth? Se asume que no (Q14)]
- **FR-033**: Idioma de la UI y locale de formato: [NEEDS CLARIFICATION: es/en y es-AR vs en-US (Q5/Q6)]

### Key Entities

- **Client**: cliente al que se le cotiza (id, nombre).
- **Currency**: moneda soportada (código ISO 4217, nombre, símbolo, decimales).
- **ExchangeRate**: tasa entre dos monedas para una fecha.
- **Quote**: cotización (código legible, fecha, cliente, descripción de evento multilínea, moneda original, subtotal, total). [NEEDS CLARIFICATION: ¿tiene estado (draft/sent/accepted)? Se asume que no (Q4)]
- **QuoteItem**: línea de la quote (descripción, cantidad, precio unitario, importe).
- **CommercialRule / AppliedRule**: regla comercial configurable y su resultado aplicado a una quote (orden, base, delta, acumulado).
- **Ticket**: texto editable de la quote (header/footer) y su versión renderizada.
- **Import / ImportError**: proceso de importación de un dataset con su progreso y sus errores por fila.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El dashboard muestra la primera página en menos de 1 s (p95) con 10.000 quotes en DB. [NEEDS CLARIFICATION: volumen de datos esperado]
- **SC-002**: Un dataset de 5.000 quotes se importa en menos de 60 s en un entorno local, con el progreso actualizado al menos cada 1 s.
- **SC-003**: El 100% de las quotes cumple que el último acumulado del desglose es igual al total (verificado por tests y por el warning de QA de la UI).
- **SC-004**: Agregar un tipo de regla nuevo requiere solo una clase nueva y su registro, con 0 líneas modificadas en el motor (verificable en el diff).
- **SC-005**: 0 violaciones de axe en las 3 pantallas y en todos sus estados, y todos los flujos se pueden completar solo con teclado.
- **SC-006**: Todos los endpoints pasan los contract tests contra `contracts/openapi.yaml`.

## Assumptions

- La base del API es el borrador v0 de `docs/api-contract-draft.md`. Las preguntas Q1 a Q15 de `docs/design.md` §8 se responden ahí de forma provisional.
- No hay autenticación ni multi-tenant en v1 (Q14).
- Las tasas de cambio se siembran en DB (`exchange_rates`) por fecha. No se integra un proveedor externo en v1.
- El dataset de import es JSON con items anidados. CSV queda fuera de v1 (el design muestra `.json, .csv`; hay que alinearlo).
- El front no recalcula montos: solo formatea y verifica Σ deltas = total como chequeo de QA.
- Dark mode incluido (tokens listos), sin requisito explícito.
