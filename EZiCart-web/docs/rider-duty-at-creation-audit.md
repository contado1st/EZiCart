# Rider duty at account creation

Date: 16 November 2026 (Asia/Manila). Scope: Laravel rider account creation, activation and duty identification. Existing rider roles, duty restrictions, mobile response fields and approval/capacity workflows are retained.

## Finding and implementation

The application already used `users.hub_transfer_only` to distinguish dedicated hub transport from local parcel work. Hub drivers were already excluded from local dispatch, and the courier dashboard already redirected them to assigned hub transfers. However, the sorting-center account-creation form did not ask for duty and its writer omitted the field, allowing the database default to silently create local parcel riders.

The sorting center must now explicitly select one of the following before creating the account:

| Duty | Existing stored value | Work destination |
| --- | --- | --- |
| Parcel pickup and delivery courier | `hub_transfer_only = false` | Local dispatch board, seller pickup and buyer delivery |
| Hub-to-hub transport courier | `hub_transfer_only = true` | Assigned hub transfers |

The form begins with an unselected, required duty control and an explanation of both choices. Missing, empty or invalid values are rejected on the server before files, account records or activation tokens are saved. The form reopens after validation errors. The server sets `logistics_owner_id` from the authenticated approved sorting center; a posted alternate owner is ignored. Creation records an administrative audit containing duty, home hub, account role and activation state.

A shared `User::RIDER_DUTIES` label map and `riderDutyLabel()` method keep wording consistent. The activation email and valid activation page identify the duty and home hub. The roster, courier workspace, rider profile, hub-transfer workspace and administrator staff ID show the assigned duty. The later administrator duty selector uses the same labels.

Activation changes the password, activation status and email-verification timestamp only. Posted duty/owner overrides cannot change the assignment. Invalid and expired activation links expose neither duty nor home-hub details. Password policy, token expiry, single-use activation and private document storage remain in place.

## Operational boundaries and compatibility

- The `courier` role and existing `hub_transfer_only` Boolean remain unchanged. No migration, new role, API request requirement for public/mobile registration, or Flutter change is introduced. The existing mobile user serializer still exposes its existing duty field.
- Rider creation remains an approved sorting-center action. Public rider self-registration remains unavailable.
- Home hub is determined at account creation. Specific transfer destinations and local service-area coverage remain separate operational assignments.
- Selecting duty does not verify cargo capacity, approve a licence, choose availability, create a parcel assignment or authorize a payout. Those existing checks remain applicable.
- Duty changes remain with logistics administration through the existing audited capacity/duty endpoint. Existing reserved-load protections remain unchanged; riders cannot use activation to change duty.
- Existing accounts retain their current stored duty and are not rewritten or falsely marked as having made a new creation-time selection.
- No real rider account was created, activated, reassigned or emailed during verification. Feature tests use isolated databases, fake storage and fake notifications.

## Verification

81 relevant tests with 746 assertions passed across `RiderActivationTest`, `HubTransferFlowTest`, `CourierWorkPresentationTest`, `WebOperationsTest`, `UsabilityIntegrationTest` and `RegistrationFlowTest`.

The expanded activation tests exercise both creation choices, private documents, mail content, creation audits, rejected missing/invalid choices, forged owner/duty inputs, single-use activation, expired/invalid-link privacy, local/hub workspace routing and rider-profile labels. Existing hub-flow tests verify local-duty exclusion and audited duty changes blocked by reserved loads. Related tests retain staff-profile access, document privacy and mobile/web serialization checks.

Pint, Blade compilation, the production frontend build and whitespace checks passed. The build retains the pre-existing optional font-fallback optimization notice; no dependency was added. Live browser visual review was blocked because the selected browser window was minimized; account-creation and workspace output were verified through rendered feature-test responses. No hardware camera, live email delivery or manual activation flow is claimed as verified.

The [web operations guide](web-operations-user-guide.md#assigned-rider-duty-at-account-creation--16-november-2026) documents the new creation procedure and the separate eligibility checks.
