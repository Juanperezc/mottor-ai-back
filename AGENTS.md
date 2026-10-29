# AGENTS.md

Reglas obligatorias para cualquier agente de IA o persona que trabaje en `mottor-ai-back`. Deben cumplirse en todas las tareas.

## Fuente de verdad: mottor-ai-specs

1. **Leer las specs antes de cada tarea.** Abrir [mottor-ai-specs](https://github.com/Juanperezc/mottor-ai-specs) y leer:
   - la constitución: `.specify/memory/constitution.md`;
   - la spec de la feature en `specs/<feature>/`: `spec.md`, `plan.md` y `tasks.md`.
2. **Sin spec no hay código.** No escribir código si no existe un requisito de la spec y una tarea de `tasks.md` que lo respalden.
3. **La spec prevalece.** Si el código y la spec difieren, la spec es correcta. Detenerse y proponer el cambio como PR a `mottor-ai-specs`. Cambiar el código solo después de actualizar la spec.
4. **Fixtures del back.** Los archivos de fixture del backend viven y se mantienen en `tests/Fixtures/`. No leer fixtures desde el repositorio de specs ni crear una carpeta `tests/fixtures/` con distinto casing.
5. **Este repositorio es dueño del contrato de la API.** El OpenAPI se genera desde el código con Scramble (Swagger UI en `/docs/api`) y se exporta a `docs/api/openapi.json`. Cada cambio de endpoint actualiza el código **y** regenera el export (`./vendor/bin/sail artisan scramble:export`) en el mismo PR. El comportamiento del endpoint sigue viniendo de la spec: nunca agregar, renombrar ni eliminar campos sin un requisito.

## Flujo de Git

6. Crear ramas desde `staging`: `feat/`, `fix/`, `refactor/`, `test/`, `docs/`, `chore/`.
7. Usar [Conventional Commits](https://www.conventionalcommits.org/): `<type>(<scope>): <imperative summary>`.
8. Abrir el PR hacia `staging`. Liberar mediante un PR de `staging` a `main`. Nunca hacer push directo a `staging` ni a `main`.
9. El cuerpo de cada PR referencia la spec y las tareas que implementa:

   ```text
   Spec: 001-quotes-mvp
   Tasks: BT-21, BT-23
   ```

   Para documentación o mantenimiento sin spec, usar `Spec: N/A (docs)` y `Tasks: N/A`.

## Idioma

10. El código, los identificadores, los comentarios y los commits de este repositorio se escriben en **inglés**. Los commits siguen Conventional Commits.
11. El README y la documentación de este repositorio se escriben en **español**. Las specs de `mottor-ai-specs` también se escriben en **español**.

## Calidad

12. Mantener los tests en verde. Agregar o actualizar tests con cada cambio de comportamiento usando las fixtures de la spec como casos de prueba.
13. No reducir la cobertura. Ejecutar el comando de cobertura del README antes de abrir un PR.
14. Ante una duda, detenerse y preguntar. No inventar requisitos.

## Reglas específicas del backend

15. Mantener el dominio (motor de reglas, dinero y ticket) en PHP puro. No usar clases de Laravel ni Eloquent en el dominio.
16. Usar Eloquent únicamente en los adaptadores de persistencia.
17. Nunca usar `float` para dinero. Usar `brick/math` con el modo de redondeo configurado.
18. Ejecutar todo mediante Sail: `./vendor/bin/sail artisan test --coverage`.
