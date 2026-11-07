# EZiCart Project Guide

## Scope and precedence

EZiCart is a Laravel e-commerce application. This file records shared project conventions for any coding agent. Platform and safety instructions remain in force; the user's current request defines the task and its scope. Apply the rules here where they fit that request. Technology skills add focused workflows and must not silently replace the architecture or product behavior described here.

Inspect the affected code and its neighbors before editing. Prefer the smallest change that satisfies the request, reuse established services and components, and preserve behavior outside the requested scope. Do not create or change dependencies without explicit user approval. Do not change schemas or business workflows as a side effect of a UI or configuration task.

## Application facts

- Check `composer.json` and `composer.lock` for PHP and package constraints; check `package.json` and the lockfile for JavaScript versions. The Composer PHP constraint is `^8.3`.
- The frontend uses Laravel Vite and Tailwind CSS v4 through `@tailwindcss/vite`. Vite entrypoints are listed in `vite.config.js`.
- The application uses controllers, Eloquent models, enums, and constructor-injected classes under `app/Services`. Follow those boundaries; do not add repositories or another architectural layer without a concrete need.
- `App\Enums\OrderStatus` defines order status values. `App\Services\OrderTransitionService` enforces order transitions and associated invariants. Use these when changing order status; do not invent state strings or bypass the transition service.
- The operational sorting-center role is `sorting_center` in routes, auth, and seed data. The initial users migration contains the legacy value `logistics`; treat it as a schema inconsistency, not as the current role contract. Do not edit historical migrations or change stored role values without an explicit migration plan.

## Backend and security

- Validate untrusted input on the server and authorize every protected operation on the server. Never rely on UI visibility or client-supplied roles, ownership, prices, statuses, or permissions.
- Keep business rules and state changes in the existing application services. Use database transactions and row locks where existing multi-step workflows require them; preserve audit/tracking events and notification behavior.
- Protect file uploads and sensitive documents, prevent IDOR and unsafe redirects, and avoid exposing credentials or private data.
- Follow the existing Laravel patterns in the relevant controller, service, model, request, policy, and test. Check installed package versions before relying on version-specific APIs.

## UI and styling

- Preserve the design system of the surface being changed. The repository contains multiple established surface styles: pink/rose and slate storefront/admin screens, lavender/wine logistics screens, and a separate cream/navy editorial welcome page. Typography also varies by surface, including Instrument Sans, Plus Jakarta Sans, and Inter/Playfair Display.
- Use the affected page's existing tokens, fonts, components, and stylesheet entrypoints. Do not impose one palette or font across unrelated surfaces, and do not restyle modules outside the task.
- Keep layouts responsive and accessible: semantic HTML, associated form labels, keyboard focus, sufficient contrast, and reduced-motion support where relevant. Prefer existing Blade components and assigned CSS files; avoid new inline CSS when the project has an appropriate stylesheet.
- Follow Tailwind CSS v4 conventions when using Tailwind utilities. Check existing CSS and Blade components before adding another styling pattern.

## Testing and reporting

- Add or run tests when the user requests testing or verification, or when the task's applicable instructions explicitly require it. Choose the narrowest relevant checks and follow `phpunit.xml` and existing PHPUnit conventions.
- Report checks accurately as passed, failed, blocked, unavailable, or not run. Inspection alone is not a test. Do not claim a build, route check, browser review, or test passed unless it was actually run.
- For UI work, inspect affected screens and responsive states when an authenticated browser and working build are available. State clearly when visual review is blocked.

## Skills and agent-specific workflows

Shared project rules live here. Keep reusable platform workflows in their respective locations; do not copy every skill into this file or assume the two platforms load the same skill directory.

- Codex skills: `.agents/skills/`. Use `laravel-best-practices` for Laravel code, `tailwindcss-development` for Tailwind/Blade utility work, `testing-best-practices` for test design, `impeccable` for frontend design work, and `deploying-to-cloud` for Laravel Cloud work. Use `infer-conventions` only when explicitly requested.
- Claude Code skills and subagents: `.claude/skills/` and `.claude/agents/`. These may include platform-specific workflows and tools; apply this file as the shared project baseline.
- Impeccable skill instructions describe design workflows. `.impeccable/config.local.json` is separate local configuration and is not a substitute for project rules.

When instructions appear to conflict, preserve platform requirements, the user's explicit task and scope, and established application behavior. Ask only when a material choice cannot be resolved from the request or repository; otherwise make the smallest consistent decision and report it.
