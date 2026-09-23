# Lead Control project guidance

These are project-level instructions for Codex and other coding agents working from `/var/www/lead-control`.

## Start with the map

Read [`docs/PROJECT-MAP.md`](docs/PROJECT-MAP.md) when a task touches an unfamiliar module, request flow, route, migration, integration, authorization rule, deployment path, or data relationship. The map is an orientation aid; verify current behavior in the referenced source files before changing code.

## Project rules

- Keep the existing `.cursor/rules/levelion-general.mdc` and `.cursor/rules/levelion-design-system.mdc` constraints. For Blade or CSS work, also read `docs/DESIGN_SYSTEM.md`.
- Treat `Order`, `Person`, and `User` identifiers as `order_id`, `person_id`, and `user_id` unless the code proves otherwise. `Order` uses its own order timestamps and has `$timestamps = false`.
- Keep business logic in `app/Services/`; controllers coordinate requests and responses. Reuse Blade components from `resources/views/components/ui/`.
- Preserve authorization and city scoping. Check `role` and `city` middleware plus the model/service policy before widening access.
- Treat MySQL as the data source of truth for deployed data. Do not print or commit values from `.env` or other secret-bearing files.
- Do not run production writes, migrations, FTP uploads, or deployment scripts unless the user explicitly asks for that operation. A read-only inspection is the default.
- This directory is a deployed copy without `.git` metadata. Do not assume a GitHub remote or that the checked-out files match the intended upstream; verify with `git rev-parse` and the README when repository provenance matters.
- Use the project commands and runtime documented in the map. On the production host, the existing project rule specifies `php83`; local development uses port `8010`.
- For a completed change, update `docs/CHANGELOG.md` as required by the existing project rule.

## Working style

Explain the purpose and scope before a large or production-facing operation. Keep changes narrow, preserve existing Russian product terminology, and add focused verification appropriate to the risk.
