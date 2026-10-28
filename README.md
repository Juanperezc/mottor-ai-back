# mottor-ai-back · Spec-Driven Development

Backend Laravel del *Mottor.ai Senior Full-Stack Challenge* (proyecto de prueba). Se trabaja con **Spec-Driven Development** según [GitHub Spec Kit](https://github.com/github/spec-kit): primero la especificación y después el código.

## Flujo SDD

| Paso | Comando Spec Kit | Resultado | Gate |
|---|---|---|---|
| 1. Constitution | `/speckit-constitution` | `.specify/memory/constitution.md` | Aprobada por Juan |
| 2. Spec | `/speckit-specify` | `specs/NNN-feature/spec.md` (Draft) | — |
| 3. Clarify | `/speckit-clarify` | Se resuelven los `[NEEDS CLARIFICATION]` | spec **Approved** por Juan |
| 4. Plan | `/speckit-plan` | `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md` | Constitution Check ✅ |
| 5. Tasks | `/speckit-tasks` (+ `/speckit-analyze`) | `tasks.md` | — |
| 6. Implement | `/speckit-implement` | Código + tests, una rama por feature (`NNN-feature`) | CI verde + review |

**Regla de oro**: ninguna tarea se implementa sin una spec aprobada por Juan.

## Dónde está cada cosa

```text
.specify/
├── memory/constitution.md   # principios no negociables (SOLID, contrato, sin float, tests...)
└── templates/               # plantillas oficiales de Spec Kit (spec, plan, tasks, checklist, constitution)
specs/
└── 001-quotes-mvp/          # Feature 1: Dashboard, Import, Quote Detail (estado: Draft)
    ├── spec.md              # user stories + requisitos (compartida con mottor-ai-front)
    ├── plan.md              # stack y arquitectura de este repo
    ├── research.md          # decisiones técnicas y alternativas
    ├── data-model.md        # modelo de datos de este repo
    ├── quickstart.md        # cómo validar end-to-end
    ├── contracts/openapi.yaml  # contrato de API (idéntico en mottor-ai-front)
    └── tasks.md             # tareas numeradas con dependencias y [P]
```

## Convenciones

- `spec.md` y `contracts/openapi.yaml` son **copias idénticas** en `mottor-ai-back` y `mottor-ai-front`. Si se cambian, se cambian en los dos repos en el mismo PR o ciclo.
- `[NEEDS CLARIFICATION: ...]` marca supuestos pendientes. Todavía no tenemos el enunciado oficial del challenge.
- Documentación en español; código, identificadores y OpenAPI en inglés.
- En `tasks.md`, `[P]` significa paralelizable y `[USn]` es la user story.

Para usar los comandos `/speckit-*` con un agente: `uv tool install specify-cli` y después `specify init --here` (conservando los archivos existentes).
