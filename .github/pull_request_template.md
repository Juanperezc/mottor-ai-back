<!-- Título en Conventional Commits: <type>(<scope>): <descripción>, p. ej. feat(quotes): add dashboard filters -->

## Spec

Spec: <!-- p. ej. 001-quotes-mvp → https://github.com/Juanperezc/mottor-ai-specs/tree/main/specs/001-quotes-mvp -->
Tasks: <!-- p. ej. T012, T013 -->

## Qué cambia y por qué

<!-- Resumen breve. Si corresponde, FR/SC de la spec que cubre (p. ej. FR-004, SC-002). -->

## Tipo de cambio

- [ ] `feat`: funcionalidad nueva
- [ ] `fix`: corrección de bug
- [ ] `refactor`: sin cambio de comportamiento
- [ ] `test`: solo tests
- [ ] `docs`: documentación
- [ ] `perf`: performance
- [ ] `build` / `ci` / `chore`: build, CI o mantenimiento
- [ ] Breaking change (`!` o `BREAKING CHANGE:`)

## Checklist

- [ ] La rama sale de `staging` y el PR apunta a `staging` (o es un release `staging` → `main`).
- [ ] Commits en Conventional Commits.
- [ ] `Spec:` y `Tasks:` completos arriba (la spec está **Approved**).
- [ ] Tests agregados o actualizados y CI en verde (lint, tipos, tests).
- [ ] Contrato respetado: contract tests (back) / `gen:api` sin diff (front) contra `mottor-ai-specs@main`.
- [ ] Si cambia comportamiento, la spec ya está actualizada en `mottor-ai-specs` (link al commit/PR): <!-- link -->
- [ ] Montos sin float (decimal string / bcmath / BigInt).
- [ ] Front: sin violaciones de `vitest-axe` y navegable por teclado (WCAG 2.2 AA).
- [ ] Tareas marcadas `[x]` en `mottor-ai-specs` al mergear.

## Cómo probarlo

<!-- Pasos, capturas o requests de ejemplo. -->
