# EZiCart

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The confirmed dashboard audiences are buyers, sellers, couriers, sorting center staff, e-commerce administrators, and logistics administrators. A priority audience beyond these six roles is undecided.

## Product Purpose

EZiCart is a Laravel e-commerce application with shopping, selling, and logistics workspaces.

## Capabilities and Constraints

The dashboard redesign covers all six role workspaces. Existing data, actions, workflows, and role permissions must be preserved. Server authorization and established application services remain authoritative.

The operational sorting center role is `sorting_center`; logistics is also used in route and interface terminology. Do not infer new role values from those labels.

No dependency, schema, or business workflow change is authorized by the dashboard design work.

## Brand Commitments

Preserve the EZiCart name and existing logo asset. Dashboard visual guidance is scoped to authenticated workspaces; the welcome page, storefront, and authentication surfaces retain their own designs.

## Evidence on Hand

The existing Laravel implementation supplies the application data, routes, permissions, and actions. Shared dashboard presentation is implemented in `public/css/workspace.css`, `public/js/workspace.js`, `resources/views/components/workspace-*.blade.php`, and `resources/views/layouts/app.blade.php`.

## Open Decisions

Product positioning, audience priority, growth strategy, and commercial claims have not been established by the dashboard request. Do not invent them in future design work.
