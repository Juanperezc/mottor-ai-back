# Uso de IA

## Recursos y fixtures

- Las respuestas de quotes, lotes e imports se serializan con API Resources en `app/Http/Resources/`; se preservan las claves, el orden y los literales decimales del contrato.
- Las fixtures del backend se mantienen en `tests/Fixtures/`, con los mismos IDs BT y sin dependencia de la carpeta de fixtures del repositorio de specs.
- Estado verificado antes de este cambio: **61 tests, 436 aserciones y 94,5 % de cobertura**. La cobertura de esta revisión se actualizará al ejecutar la suite completa.

Se usó IA para preparar y verificar este backend. Estos son ciclos de revisión concretos del trabajo, no ejemplos hipotéticos.

## 1. Bootstrap de Laravel

- **Pedido:** crear Laravel 13.39 sobre PHP 8.5 sin sobrescribir la documentación del repositorio.
- **Resultado inicial:** el skeleton de Laravel 13.11 cuyo resolvedor instaló `laravel/framework` 13.39.0, conservando al principio la restricción `php: ^8.3`.
- **Aceptado y corregido:** se ajustó el proyecto a PHP `^8.5` y Laravel `13.39.0`; el README, AGENTS, la plantilla de PR y los docs existentes se integraron en vez de reemplazarse sin revisión.

## 2. Validación HTTP y configuración del dominio

- **Pedido:** validar la entrada de presupuestos y pasar una configuración ordenada de reglas al motor en PHP puro.
- **Resultado inicial:** el primer `FormRequest` validaba solo `rules.*.type`. `validated()` eliminaba campos no declarados como `category`, `buy` y `percent`, por lo que el motor rechazaba el caso oficial.
- **Aceptado y corregido:** ahora se validan y conservan todos los campos admitidos. Los constructores de las estrategias siguen validando las invariantes del dominio, por lo que HTTP no se convierte en la fuente de las reglas de negocio.

## 3. Filtro literal por cliente

- **Pedido:** implementar un filtro de substring escapado e insensible a mayúsculas con una consulta de página y otra de conteo.
- **Resultado inicial:** la primera consulta usaba la barra invertida para `LIKE ... ESCAPE`, pero la expresión generada no tenía un único carácter de escape en la configuración inicial.
- **Aceptado y corregido:** `!` pasó a ser el carácter explícito y el input escapa `!`, `%` y `_`. El feature test comprueba sobre MySQL que `%` se interpreta literalmente.

## 4. Verificación aritmética

- **Pedido:** reproducir el desglose oficial, resolver el segundo caso y procesar el dataset real sin floats.
- **Resultado:** totales exactos de `4.722.388,00` y `7.832.871,49`, y luego `0,58` como mínimo de los 10.000 presupuestos.
- **Verificado:** PHPUnit compara los casos oficiales, el comportamiento impuesto-antes-del-tope y las 17 fixtures del formateador 28×4. El comando del dataset informó cero totales negativos y cero presupuestos inválidos.

## 5. Seguimiento sobre configuración y persistencia bulk

- **Pedido:** alinear labels, redondeo, CORS, fixtures y persistencia por lotes con la spec aprobada `mottor-ai-specs@3ef1401`.
- **Resultado inicial:** había labels en inglés, `HALF_UP` fijo en el listado, CORS abierto y escrituras fila por fila.
- **Aceptado y corregido:** cada estrategia genera labels es-AR, el modo de `config/money.php` se inyecta en dominio y listado, CORS usa `FRONTEND_URL`, y quotes/ítems/reglas/pasos se insertan por tabla en lotes dentro de una transacción. Tests sobre MySQL verifican los inserts bulk y el rollback ante una falla simulada.
