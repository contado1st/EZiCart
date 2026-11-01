# Registration Fields Checklist Audit

**Date:** 2026-10-19  
**Scope:** Buyer, Seller, Logistics/Sorting Center registration, and the Logistics-managed Rider creation/activation flow.  
**Sources:** `Blackbox_Test_Cases_Account_Creation-1.xlsx`, `EZiCart Registration Audit.md`, `EZiCart_Registration_Login_Overhaul_Audit_Driven.md`, the current Laravel controllers, Blade forms, and registration JavaScript.

## Result

**The identified browser constraint gaps have now been added to the forms.** The backend remains authoritative, and no claim is made that all 150 cases pass. The workbook also contains expectations that were later explicitly changed by the user.

This is a **static source audit**. It did not submit the 150 workbook scenarios, inspect live rendered pages, or change the workbook. In the checklist below every runtime case remains unchecked until it is executed with the workbook test data and expected-result steps.

## Findings to resolve or account for

| Priority | Area / test IDs | Checklist finding | Current behavior |
|---|---|---|---|
| Resolved | Password length: `TC-BUY-017`, `TC-SEL-015`, `TC-LOG-015`, `TC-RID-021` | [x] Add browser limits matching the backend | Password and confirmation inputs now have `minlength="8"` and `maxlength="128"` on buyer, seller, logistics, and rider activation forms. |
| Resolved | Name length: `TC-BUY-006`, `TC-SEL-006`, `TC-LOG-006`, `TC-RID-005` | [x] Add browser length feedback without truncating overlong entries | Required first/last names now require 2–50 characters. Optional middle names and suffixes also have bounded patterns. Maximum-length attributes were removed so a 51-character entry remains invalid and shows a length message instead of being silently shortened. |
| Source-aligned | Uppercase duplicate email: `TC-BUY-011`, `TC-SEL-009`, `TC-LOG-009`, `TC-RID-008` | [x] Normalize before duplicate checks | Registration and rider creation trim/lowercase email before validation and compare the canonical email across roles. An uppercase spelling of an existing address is therefore blocked as a duplicate. Runtime workbook execution is still pending. |
| Resolved | Buyer age: `TC-BUY-007`, `TC-BUY-008`; rider age: `TC-RID-006` | [x] Add date input `max` constraints | Buyer and rider date fields now cap the date at the current 18-year eligibility cutoff. Backend checks remain in place. |
| Resolved | Rider contact/license: `TC-RID-009`, `TC-RID-010`, `TC-RID-012`, `TC-RID-014` | [x] Add matching client-side format/limit hints and constraints | Rider mobile and license inputs now have browser patterns and lengths; license expiry now has a minimum of today. Backend checks remain in place. |
| Resolved | Mobile format, length, duplicate, and +63 cases: `TC-BUY-013`–`016`, `TC-SEL-011`–`014`, `TC-LOG-011`–`014`, `TC-RID-009`–`011` | [x] Reject malformed/landline values, require 11 digits, and detect canonical duplicates | Browser patterns and server validation require a Philippine mobile number in `09XXXXXXXXX` or equivalent `+63` format. Invalid letters, punctuation, prefixes, and lengths are not sanitized into valid values. Server normalization treats `09...` and `+639...` as one number for duplicate checks. Runtime workbook execution is still pending. |
| Resolved | Seller business identifiers: `TC-SEL-025`; logistics identifiers `TC-LOG-022`, `TC-LOG-025` | [x] Add browser patterns/input limits | Seller/logistics TIN and logistics SEC fields now expose patterns and lengths matching server rules. Logistics company name and contact-position length limits are also in the form. |
| Resolved | Rider email and deduplication: `TC-RID-007`, `TC-RID-008` | [x] Add browser email length | Rider email now has `maxlength="255"`. Duplicate checks remain server-side and still require execution against the workbook data. |
| Medium | Buyer optional address: `TC-BUY-002`, `TC-BUY-003` | [ ] Confirm the workbook expects the buyer address to remain optional | The address step is explicitly optional in the current form and server validation. A blank delivery address is accepted. |
| Low | Extra buyer fields vs original field list | [ ] Confirm extras are acceptable in the acceptance checklist | Buyer page also asks for optional middle name, suffix, and optional delivery address. They do not block submission when blank. The earlier registration audit called out these additions relative to the original Test Data field list. |
| Spec change | `TC-BUY-004`, `TC-SEL-004`, `TC-LOG-004` | [x] Record name-rule discrepancy | The workbook's buyer case says names allow only letters, while the overhaul audit explicitly allows real-name punctuation (spaces, apostrophes, periods, hyphens). Current backend and page follow the overhaul audit. |
| User override | `TC-SEL-023`, `TC-SEL-024` | [x] Apply latest seller document instruction | Workbook/audit expected seller-type-dependent documents. Your later instruction requires **Business Permit and Valid ID for every seller type**. Current seller UI and backend require those two files and no type-specific alternatives. These two workbook expectations are intentionally superseded. |
| User override | `TC-BUY-029`, `TC-SEL-040` | [x] Apply earlier buyer/seller login OTP instruction | Workbook expects direct dashboard access after verification/approval. Your earlier instruction requires emailed OTP on buyer and seller logins, so this flow differs intentionally. |
| Workbook inconsistency | `TC-RID-012` | [x] Record driver-license number ambiguity | Workbook prose and examples disagree on digit count. Current rule follows the explicit example `N01-23-456789` (one letter plus ten digits). |

## Rules observed in source

- Buyer, seller, and logistics contact fields share server validation for names, normalized email, and normalized PH mobile number. Required names are 2–50 characters; middle name is optional; suffix is optional.
- Email addresses are trimmed and lowercased before registration uniqueness checks. The canonical email is unique across roles, including when a duplicate is submitted in uppercase. Rider creation applies the same normalization and duplicate check.
- Buyer DOB server validation rejects future dates and requires age 18+. Rider creation uses the same adult/future-date server constraints.
- Buyer delivery address is optional. Seller and logistics use linked PSGC selections, with required location and four-digit postal code. Logistics must select at least one server-approved service area.
- Seller shop name is 3–30 characters and unique after normalization. Category and seller type are dropdowns validated against fixed server values. TIN is validated in `000-000-000-000` format and uniqueness is canonicalized.
- Logistics company name, SEC registration, TIN, service areas, and four company documents are required and server-validated. Rejected applicants can retain existing documents during resubmission.
- Seller uploads are exactly Valid ID and Business Permit per the latest user instruction. Both are required for new applications, accept JPG/PNG/PDF, and are capped at 5 MB each.
- Buyer, seller, logistics, and rider activation password validation enforces 8–128 characters, mixed case, a number, a symbol, and confirmation. Terms acceptance is required on buyer/seller/logistics registration.
- Rider creation is restricted server-side to an approved logistics operator. Rider creation requires identity/contact data, adult DOB, license number and expiry, vehicle type, plate number, license and OR/CR documents. A rider receives a one-use activation link and sets their own password.
- All of these are source-level observations only; they do not establish that the live email provider, rendered forms, or every expected-result message works in the running app.

## Browser constraint changes

- Added 8–128 length attributes to password and confirmation controls on buyer, seller, logistics, and rider activation forms.
- Added the adult-date cutoff to buyer and rider birth-date inputs, and today as the rider license-expiry minimum.
- Added rider mobile formats for `09XXXXXXXXX` and `+639XXXXXXXXX` (with the displayed grouped spacing variants), and the example-based driver-license format/length.
- Buyer, seller, logistics, and rider mobile fields now explain the exact 11-digit rule. The shared input script preserves letters and punctuation for validation, blocks typing or pasting beyond 11 national digits (12 with `+63`), and does not convert overlong values into valid numbers. Server normalization recognizes equivalent `09...` and `+63...` values for duplicate checks and reports when a number is already in use.
- Added max length to rider email; name patterns and lengths to rider first/last name; and the plate number minimum length.
- Added explicit first/last-name minimum length and bounded patterns across buyer, seller, logistics, and rider forms. Removed name maximum-length attributes so overlong input can be rejected with a format/length message instead of being truncated.
- Added seller/logistics TIN formats, logistics SEC format, company-name length, and contact-position maximum. TIN accepts digits, inserts hyphens automatically, and rejects letters and manually entered or pasted symbols.
- Seller and logistics TIN require exactly 12 digits in the `000-000-000-000` format. The form blocks entry or paste beyond 12 digits and caps the formatted value at 15 characters. Values with fewer than 12 digits remain invalid with the required format guidance. Workbook execution is still pending.

## 150-case execution checklist

Check a row only after running that case with the workbook's input data and comparing the observed result with its expected result. `Not run` means no browser/manual execution is claimed by this audit.

| Run | Test case | Scenario | Result |
|---|---|---|---|
| [ ] | TC-BUY-001 | Register with all valid data | Not run in this static audit |
| [ ] | TC-BUY-002 | Submit a completely empty form | Not run in this static audit |
| [ ] | TC-BUY-003 | Leave one required field blank at a time | Not run in this static audit |
| [ ] | TC-BUY-004 | Name with numbers or symbols | Not run in this static audit |
| [ ] | TC-BUY-005 | Unicode name with allowed punctuation | Not run in this static audit |
| [ ] | TC-BUY-006 | Name length at the limits | Not run in this static audit |
| [ ] | TC-BUY-007 | Age at the 18-year limit | Not run in this static audit |
| [ ] | TC-BUY-008 | Date of birth in the future | Not run in this static audit |
| [ ] | TC-BUY-009 | Email in an invalid format | Not run in this static audit |
| [ ] | TC-BUY-010 | Email that is already registered | Not run in this static audit |
| [ ] | TC-BUY-011 | Registered email typed in capital letters | Not run in this static audit |
| [ ] | TC-BUY-012 | Email with spaces before and after | Not run in this static audit |
| [ ] | TC-BUY-013 | Mobile number in an invalid format | Not run in this static audit |
| [ ] | TC-BUY-014 | Mobile number length at the limits | Not run in this static audit |
| [ ] | TC-BUY-015 | Mobile number that is already registered | Not run in this static audit |
| [ ] | TC-BUY-016 | Registered number entered in +63 format | Not run in this static audit |
| [ ] | TC-BUY-017 | Password length at the limit | Not run in this static audit |
| [ ] | TC-BUY-018 | Password missing a required character type | Not run in this static audit |
| [ ] | TC-BUY-019 | Confirm Password does not match | Not run in this static audit |
| [ ] | TC-BUY-020 | Password is hidden while typing | Not run in this static audit |
| [ ] | TC-BUY-021 | Register without accepting the terms | Not run in this static audit |
| [ ] | TC-BUY-022 | Click Register several times quickly | Not run in this static audit |
| [ ] | TC-BUY-023 | Verify with the correct OTP | Not run in this static audit |
| [ ] | TC-BUY-024 | Verify with an incorrect OTP | Not run in this static audit |
| [ ] | TC-BUY-025 | OTP in the wrong format | Not run in this static audit |
| [ ] | TC-BUY-026 | Verify with an expired OTP | Not run in this static audit |
| [ ] | TC-BUY-027 | Resend OTP | Not run in this static audit |
| [ ] | TC-BUY-028 | Log in before verifying the email | Not run in this static audit |
| [ ] | TC-BUY-029 | Log in with the new verified account | Not run in this static audit |
| [ ] | TC-BUY-030 | Open another role's pages | Not run in this static audit |
| [ ] | TC-SEL-001 | Register with all valid data | Not run in this static audit |
| [ ] | TC-SEL-002 | Submit a completely empty form | Not run in this static audit |
| [ ] | TC-SEL-003 | Leave one required field blank at a time | Not run in this static audit |
| [ ] | TC-SEL-004 | Name with numbers or symbols | Not run in this static audit |
| [ ] | TC-SEL-005 | Unicode name with allowed punctuation | Not run in this static audit |
| [ ] | TC-SEL-006 | Name length at the limits | Not run in this static audit |
| [ ] | TC-SEL-007 | Email in an invalid format | Not run in this static audit |
| [ ] | TC-SEL-008 | Email that is already registered | Not run in this static audit |
| [ ] | TC-SEL-009 | Registered email typed in capital letters | Not run in this static audit |
| [ ] | TC-SEL-010 | Email with spaces before and after | Not run in this static audit |
| [ ] | TC-SEL-011 | Mobile number in an invalid format | Not run in this static audit |
| [ ] | TC-SEL-012 | Mobile number length at the limits | Not run in this static audit |
| [ ] | TC-SEL-013 | Mobile number that is already registered | Not run in this static audit |
| [ ] | TC-SEL-014 | Registered number entered in +63 format | Not run in this static audit |
| [ ] | TC-SEL-015 | Password length at the limit | Not run in this static audit |
| [ ] | TC-SEL-016 | Password missing a required character type | Not run in this static audit |
| [ ] | TC-SEL-017 | Confirm Password does not match | Not run in this static audit |
| [ ] | TC-SEL-018 | Password is hidden while typing | Not run in this static audit |
| [ ] | TC-SEL-019 | Register without accepting the terms | Not run in this static audit |
| [ ] | TC-SEL-020 | Click Register several times quickly | Not run in this static audit |
| [ ] | TC-SEL-021 | Shop name already used by another seller | Not run in this static audit |
| [ ] | TC-SEL-022 | Shop name length at the limits | Not run in this static audit |
| [ ] | TC-SEL-023 | Required documents change with the seller type | Not run in this static audit |
| [ ] | TC-SEL-024 | Corporation registers without the SEC certificate | Not run in this static audit |
| [ ] | TC-SEL-025 | TIN in an invalid format or length | Not run in this static audit |
| [ ] | TC-SEL-026 | TIN that is already registered | Not run in this static audit |
| [ ] | TC-SEL-027 | Address dropdowns depend on each other | Not run in this static audit |
| [ ] | TC-SEL-028 | Postal code length and format | Not run in this static audit |
| [ ] | TC-SEL-029 | Upload documents in each accepted file type | Not run in this static audit |
| [ ] | TC-SEL-030 | Upload a file type that is not accepted | Not run in this static audit |
| [ ] | TC-SEL-031 | File size at the limit | Not run in this static audit |
| [ ] | TC-SEL-032 | Verify with the correct OTP | Not run in this static audit |
| [ ] | TC-SEL-033 | Verify with an incorrect OTP | Not run in this static audit |
| [ ] | TC-SEL-034 | OTP in the wrong format | Not run in this static audit |
| [ ] | TC-SEL-035 | Verify with an expired OTP | Not run in this static audit |
| [ ] | TC-SEL-036 | Resend OTP | Not run in this static audit |
| [ ] | TC-SEL-037 | Log in before verifying the email | Not run in this static audit |
| [ ] | TC-SEL-038 | Pending seller logs in | Not run in this static audit |
| [ ] | TC-SEL-039 | New application appears in the admin's pending list | Not run in this static audit |
| [ ] | TC-SEL-040 | Admin approves the application | Not run in this static audit |
| [ ] | TC-SEL-041 | Admin rejects the application with a reason | Not run in this static audit |
| [ ] | TC-SEL-042 | Rejected seller corrects and resubmits | Not run in this static audit |
| [ ] | TC-SEL-043 | Non-admin opens the approval page | Not run in this static audit |
| [ ] | TC-SEL-044 | Open another role's pages | Not run in this static audit |
| [ ] | TC-LOG-001 | Register with all valid data | Not run in this static audit |
| [ ] | TC-LOG-002 | Submit a completely empty form | Not run in this static audit |
| [ ] | TC-LOG-003 | Leave one required field blank at a time | Not run in this static audit |
| [ ] | TC-LOG-004 | Name with numbers or symbols | Not run in this static audit |
| [ ] | TC-LOG-005 | Unicode name with allowed punctuation | Not run in this static audit |
| [ ] | TC-LOG-006 | Name length at the limits | Not run in this static audit |
| [ ] | TC-LOG-007 | Email in an invalid format | Not run in this static audit |
| [ ] | TC-LOG-008 | Email that is already registered | Not run in this static audit |
| [ ] | TC-LOG-009 | Registered email typed in capital letters | Not run in this static audit |
| [ ] | TC-LOG-010 | Email with spaces before and after | Not run in this static audit |
| [ ] | TC-LOG-011 | Mobile number in an invalid format | Not run in this static audit |
| [ ] | TC-LOG-012 | Mobile number length at the limits | Not run in this static audit |
| [ ] | TC-LOG-013 | Mobile number that is already registered | Not run in this static audit |
| [ ] | TC-LOG-014 | Registered number entered in +63 format | Not run in this static audit |
| [ ] | TC-LOG-015 | Password length at the limit | Not run in this static audit |
| [ ] | TC-LOG-016 | Password missing a required character type | Not run in this static audit |
| [ ] | TC-LOG-017 | Confirm Password does not match | Not run in this static audit |
| [ ] | TC-LOG-018 | Password is hidden while typing | Not run in this static audit |
| [ ] | TC-LOG-019 | Register without accepting the terms | Not run in this static audit |
| [ ] | TC-LOG-020 | Click Register several times quickly | Not run in this static audit |
| [ ] | TC-LOG-021 | Company name already registered | Not run in this static audit |
| [ ] | TC-LOG-022 | SEC registration number in an invalid format | Not run in this static audit |
| [ ] | TC-LOG-023 | SEC registration number already registered | Not run in this static audit |
| [ ] | TC-LOG-024 | Select more than one service area | Not run in this static audit |
| [ ] | TC-LOG-025 | TIN in an invalid format or length | Not run in this static audit |
| [ ] | TC-LOG-026 | TIN that is already registered | Not run in this static audit |
| [ ] | TC-LOG-027 | Address dropdowns depend on each other | Not run in this static audit |
| [ ] | TC-LOG-028 | Postal code length and format | Not run in this static audit |
| [ ] | TC-LOG-029 | Upload documents in each accepted file type | Not run in this static audit |
| [ ] | TC-LOG-030 | Upload a file type that is not accepted | Not run in this static audit |
| [ ] | TC-LOG-031 | File size at the limit | Not run in this static audit |
| [ ] | TC-LOG-032 | Verify with the correct OTP | Not run in this static audit |
| [ ] | TC-LOG-033 | Verify with an incorrect OTP | Not run in this static audit |
| [ ] | TC-LOG-034 | OTP in the wrong format | Not run in this static audit |
| [ ] | TC-LOG-035 | Verify with an expired OTP | Not run in this static audit |
| [ ] | TC-LOG-036 | Resend OTP | Not run in this static audit |
| [ ] | TC-LOG-037 | Log in before verifying the email | Not run in this static audit |
| [ ] | TC-LOG-038 | Pending logistics logs in | Not run in this static audit |
| [ ] | TC-LOG-039 | New application appears in the admin's pending list | Not run in this static audit |
| [ ] | TC-LOG-040 | Admin approves the application | Not run in this static audit |
| [ ] | TC-LOG-041 | Admin rejects the application with a reason | Not run in this static audit |
| [ ] | TC-LOG-042 | Rejected logistics corrects and resubmits | Not run in this static audit |
| [ ] | TC-LOG-043 | Non-admin opens the approval page | Not run in this static audit |
| [ ] | TC-LOG-044 | Open another role's pages | Not run in this static audit |
| [ ] | TC-RID-001 | Create a rider with all valid data | Not run in this static audit |
| [ ] | TC-RID-002 | Submit a completely empty form | Not run in this static audit |
| [ ] | TC-RID-003 | Leave one required field blank at a time | Not run in this static audit |
| [ ] | TC-RID-004 | Name with numbers or symbols | Not run in this static audit |
| [ ] | TC-RID-005 | Name length at the limits | Not run in this static audit |
| [ ] | TC-RID-006 | Age at the 18-year limit | Not run in this static audit |
| [ ] | TC-RID-007 | Email in an invalid format | Not run in this static audit |
| [ ] | TC-RID-008 | Email already used by an account of another role | Not run in this static audit |
| [ ] | TC-RID-009 | Mobile number in an invalid format | Not run in this static audit |
| [ ] | TC-RID-010 | Mobile number length at the limits | Not run in this static audit |
| [ ] | TC-RID-011 | Mobile number that is already registered | Not run in this static audit |
| [ ] | TC-RID-012 | Driver's license number in an invalid format | Not run in this static audit |
| [ ] | TC-RID-013 | Driver's license number already registered | Not run in this static audit |
| [ ] | TC-RID-014 | License expiry date at the limit | Not run in this static audit |
| [ ] | TC-RID-015 | Plate number already registered | Not run in this static audit |
| [ ] | TC-RID-016 | Upload documents in each accepted file type | Not run in this static audit |
| [ ] | TC-RID-017 | Upload a file type that is not accepted | Not run in this static audit |
| [ ] | TC-RID-018 | File size at the limit | Not run in this static audit |
| [ ] | TC-RID-019 | Click Save several times quickly | Not run in this static audit |
| [ ] | TC-RID-020 | Rider activates the account with a valid password | Not run in this static audit |
| [ ] | TC-RID-021 | Set a password that breaks the password rule | Not run in this static audit |
| [ ] | TC-RID-022 | Confirm Password does not match | Not run in this static audit |
| [ ] | TC-RID-023 | Open an expired activation link | Not run in this static audit |
| [ ] | TC-RID-024 | Logistics resends the activation email | Not run in this static audit |
| [ ] | TC-RID-025 | Reuse the activation link after activating | Not run in this static audit |
| [ ] | TC-RID-026 | Rider logs in before activating | Not run in this static audit |
| [ ] | TC-RID-027 | Rider logs in after activating | Not run in this static audit |
| [ ] | TC-RID-028 | Rider is visible only to the company that created it | Not run in this static audit |
| [ ] | TC-RID-029 | No public rider registration | Not run in this static audit |
| [ ] | TC-RID-030 | Buyer or seller opens the Add Rider form | Not run in this static audit |
| [ ] | TC-RID-031 | Pending logistics account tries to add a rider | Not run in this static audit |
| [ ] | TC-RID-032 | Open another role's pages | Not run in this static audit |

