# UI/UX modernization prompt: research and compatibility audit

**Date:** 15 November 2026, Asia/Manila. **Scope:** research, existing-rule compatibility, and preparation of an implementation-agent prompt. This is an audit of the planning artifact and relevant source rules; it is not a new visual acceptance audit of the whole website.

## Delivered artifacts and source treatment

- [Project-specific master prompt](ui_ux_design_modernization_master_prompt.md): covers the five 20-item collections through A01–E20, with definitions, use conditions, application examples and safeguards.
- This report: compatibility assessment, research corrections, delivery checks and remaining observation requirements.
- User-supplied reference: `C:/Users/Admin/Downloads/ui_ux_design_modernization_master_prompt.md`. Its useful plan/audit structure, functional protections, accessibility guidance and conditional treatment of advanced techniques informed the revision. Its executable wording was treated as draft content, not as a command to redesign the application during this task. The original Downloads file was not overwritten.

The revised prompt is stored in the repository's docs directory so it travels with the project. Only these two documentation files were created in this task. Existing application changes in the working tree were preserved. No package, schema, visual token, application workflow or source stylesheet was changed for prompt preparation.

## Goal 1: research and conceptual accuracy

**Completed:** the raw lists were mapped into 100 stable entries. Repeated subjects—tabular numbers, safe areas, interruptibility and optimistic updates—reference shared implementation decisions. They do not become duplicate components or separate features merely because they occur in several lists.

The supplied draft already contains sensible restrictions: preserve functionality, avoid indiscriminate techniques, keep server-confirmed sensitive operations, and treat animation values as starting points. These strengths are retained. The main additions are exact EZiCart boundaries, verifiable acceptance contracts and a clear distinction between standards, design heuristics, browser features and optional library APIs.

| Research conclusion | Prompt treatment and evidence |
| --- | --- |
| Contrast and target size are different accessibility criteria | Separates text/non-text requirements and distinguishes AA's 24 CSS-pixel target rule and exceptions from a larger touch usability target. It does not prescribe a universal 44-pixel AA requirement or certify existing tokens from code alone. [W3C contrast](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), [W3C targets](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html). |
| Motion values are proposals, not universal rules | 150–250 ms, 30 ms stagger, 0.97 press scale, 12% stretch and 20% shorter exits remain conditional experiments. Motion must be purposeful and cancellable, with alternatives for sensitivity. [Apple motion](https://developer.apple.com/design/human-interface-guidelines/motion). |
| Miller's memory result does not cap menu length | Preserves visible recognition and task grouping instead of deleting navigation items to satisfy seven. [NN/g chunking](https://www.nngroup.com/articles/chunking/). |
| Responsiveness is not a universal 400 ms completion guarantee | Requires immediate acknowledgement, accurate progress and measured latency; no fake wait or invented success. The older response-time heuristics are identified as guidance. [NN/g response time](https://www.nngroup.com/articles/response-times-3-important-limits/). |
| Predictive back is platform-specific | Native Android previews are distinguished from browser history behavior. No custom horizontal gesture is required on this Laravel site. [Android](https://developer.android.com/design/ui/mobile/guides/patterns/predictive-back). |
| `layoutId` depends on a specific library | React being present does not establish Motion availability. Native CSS view-transition identity is different, and full Blade navigation is different from an in-document update. [Motion](https://motion.dev/docs/react-layout-animations), [MDN view transitions](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API). |
| Placeholder and speculative-loading techniques have costs | BlurHash requires encoded metadata and decoding. SSR already exists through Blade. Speculative prerender can cost resources and needs route/security review; it is not a universal page-speed switch. [BlurHash](https://blurha.sh/), [MDN speculation rules](https://developer.mozilla.org/en-US/docs/Web/API/Speculation_Rules_API). |
| New CSS capabilities need progressive enhancement | Balanced wrapping, corner shapes, safe areas and numeric font features require the appropriate use case, support checks and usable fallbacks. Sources are linked at their relevant sections in the prompt. |

No source was treated as authority to install a framework or copy a brand. The requested animation lists are interpreted as design vocabulary; their classical animation metaphors do not require 3D business dashboards, rubber-band scrolling or deformed controls.

## Goal 2: compatibility with the current project

**Completed as source assessment.** Existing rules do exist. `AGENTS.md`, `PRODUCT.md`, `DESIGN.md`, local design guidance, loaded stylesheet entrypoints and reusable components provide the baseline. Where documents and current code differ, the prompt requires a recorded conflict rather than silently selecting a new visual world.

| Existing source / behavior | Compatibility | Required agent decision |
| --- | --- | --- |
| Laravel controllers/services, Blade, Tailwind v4, Vite | Compatible | Use existing boundaries and surface entrypoints. Do not propose a SPA conversion to enable modern effects. |
| Existing React decision-dialog island | Compatible with limits | Reuse the confirmation interaction where relevant; React does not imply Motion or a query/cache library is installed. |
| Commerce rose/slate and logistics wine/lavender | Compatible | Reuse role accents and semantic states. Do not force one new accent on all modules. |
| Separate cream/editorial landing page | Compatible with limits | Maintain its visual voice and assets; do not impose workspace typography/cards on the hero. |
| `DESIGN.md` describes neutral thin borders | Documentation conflict | Current `public/css/interaction-feedback.css` uses stronger outer pink frames and subtler inner borders from the user's earlier request. Do not remove those as generic decoration without a new decision. |
| Notification-page pink-border exception | User constraint to verify | Preserve the exception and inspect actual selectors/route context during implementation; this prompt audit does not establish rendered compliance. |
| Existing `workspace.css` compact controls and data typography | Compatible, needs rendered checks | Keep density and tabular figures; evaluate actual hit boxes, spacing, contrast and zoom rather than blindly enlarging or certifying everything. |
| Existing page-loading profiles and skeleton components | Reuse | Inspect missing states and layout matching. Do not add a competing full-page loader or artificial delay. |
| Existing tabs, pagination and responsive drawer | Reuse | Preserve server pagination, filter/query state, focus handling, active route and per-tab accounts. |
| Need Actions and Finance & Wallet | Reuse | Prioritize operational tasks and retain access to financial records. Do not remove a function while simplifying its navigation. |
| Shopicons component | Reuse | Keep the current icon family instead of adding emoji, arbitrary SVG styles or another icon package. |
| Order, wallet, dispatch and approval invariants | Conditional techniques only | Pending feedback is appropriate; optimistic success, fake undo and stale authorization are not. Retain capacity, proof, funding and independent review. |
| `.impeccable/design.json` drift previously reported | Separate documentation issue | Do not regenerate it or rewrite DESIGN.md during unrelated styling work. Record drift and scope any refresh explicitly. |

The conclusion is **compatible as a selective improvement framework, not as a literal all-techniques rollout**. Some requested concepts are definitions rather than features; several advanced techniques should remain deferred unless their actual UI problem is demonstrated.

## Goal 3: prompt and planning quality

**Completed:** the revised prompt makes the workflow explicit:

1. Inspect actual code, states, roles and available browser evidence.
2. Produce a baseline issue audit and compatibility matrix.
3. Assign every concept a disposition and evaluate alternatives.
4. Present a concrete phased plan before application edits.
5. Implement within authorization and preserve existing satisfactory features.
6. Audit after every major goal; stop affected rollout for functional, privacy or accessibility blockers.
7. Deliver a cumulative report with actual evidence and remaining limits.

The source draft's closing “plan first, audit second” shorthand could conflict with its earlier baseline-audit phase. The revision resolves this ordering: **inspect → baseline audit → compatibility → plan → authorized implementation → milestone audit**.

Default PLAN mode produces planning artifacts and stops before UI changes. An explicit instruction to plan and then implement can authorize execution within that plan; existing approval is not repeatedly requested. Dependencies, major policy changes and deployment keep their own applicable permission boundaries.

The final prompt includes M0–M6 milestones, an issue-register schema, a mutation-safety matrix, role-specific examples, progress/error/retry contracts, a bounded visual-check workflow and standard audit statuses. It requires evidence that distinguishes code inspection, automated checks, browser rendering and live-service acceptance.

## Validation performed for this task

- Checked the concept registry contains 100 unique IDs, with 20 in each A–E group.
- Reviewed project architecture/style rules and representative source components; identified the border-documentation conflict without modifying it.
- Researched primary documentation and original UX guidance linked in the prompt/report. Browser-support assumptions must be rechecked when an actual enhancement is scheduled.
- Checked Markdown structure, local artifact links and whitespace. No new UI code or application behavior was introduced, so application tests/build were not rerun for this documentation-only task.

This is not a claim that the website currently meets every design/accessibility criterion. No fresh screenshots, device study, contrast measurement, screen-reader test, performance benchmark or full functional suite was performed. Earlier build/test results elsewhere in the project are not evidence of a new UI modernization rollout.

## Handoff

Use [the project-specific master prompt](ui_ux_design_modernization_master_prompt.md) as the AI agent's brief. Its first output should be the concrete PLAN and disposition register, not a generic restyling diff. The future agent must audit after each major implemented goal and report skipped already-implemented behavior alongside changes.

The research, compatibility review and prompt preparation are complete. The next application milestone is M0's route/state baseline and actionable implementation plan; no UI implementation was started by this prompt-writing task.
