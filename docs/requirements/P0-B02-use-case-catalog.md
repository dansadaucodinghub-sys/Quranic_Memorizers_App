# P0-B02 Use-Case Catalog

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Baseline | QMDB-BL-001 |
| Batch | QMDB-P0-B02 |
| Document version | 1.0.0 |
| Status | Complete use-case baseline |
| Last updated | 2026-08-24 |

## Purpose

This catalog translates the functional requirements into actor-centered transactions. Authority always derives from authenticated identity, current Workspace/Membership, administrative or competition assignment, resource state, time, risk, and explicit capability—not from a public identifier or job title.

## Common execution rules

1. Every command receives a correlation identifier and, for retryable writes, an idempotency key.
2. The server resolves authority and validates current versions before mutation.
3. Authoritative changes and their durable event/audit evidence commit atomically or fail without partial state.
4. Notifications and projections are asynchronous and never decide official truth.
5. Validation preserves safe user input, provides an error summary, and supports keyboard, screen reader, Arabic/RTL, high contrast, reduced motion, and low bandwidth.

## QMDB-UC-001 — Register an individual account

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-001 |
| Name | Register an individual account |
| Goal | Complete register an individual account with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Registered Individual |
| Supporting Actors | Identity service |
| Authority Scope | Personal |
| Related Platform Side | Public identity portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to register an individual account. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | User Account; verification challenge reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | User Account; verification challenge. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-001. |
| Notifications | QMDB-NTF-001; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-IAM-001. |
| Planned Implementation Phase | P2. |
## QMDB-UC-002 — Verify an account

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-002 |
| Name | Verify an account |
| Goal | Complete verify an account with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Registered Individual |
| Supporting Actors | Notification worker; identity service |
| Authority Scope | Personal account |
| Related Platform Side | Public identity portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to verify an account. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Verification challenge; User Account reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Verification challenge; User Account. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-002. |
| Notifications | QMDB-NTF-002; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-IAM-002. |
| Planned Implementation Phase | P2. |
## QMDB-UC-003 — Authenticate using password and MFA

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-003 |
| Name | Authenticate using password and MFA |
| Goal | Complete authenticate using password and mfa with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Registered Individual |
| Supporting Actors | Identity and risk services |
| Authority Scope | Requested Workspace and resource |
| Related Platform Side | Identity portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to authenticate using password and mfa. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Credential; MFA method; Session reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Credential; MFA method; Session. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-003. |
| Notifications | QMDB-NTF-003; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-IAM-003, QMDB-FR-IAM-004, QMDB-FR-SES-001. |
| Planned Implementation Phase | P2. |
## QMDB-UC-004 — Authenticate using a passkey

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-004 |
| Name | Authenticate using a passkey |
| Goal | Complete authenticate using a passkey with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Registered Individual |
| Supporting Actors | Identity and risk services |
| Authority Scope | Personal account and requested scope |
| Related Platform Side | Identity portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to authenticate using a passkey. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Passkey credential; Session reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Passkey credential; Session. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-003. |
| Notifications | QMDB-NTF-003; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-IAM-003, QMDB-FR-IAM-004. |
| Planned Implementation Phase | P2. |
## QMDB-UC-005 — Recover an account

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-005 |
| Name | Recover an account |
| Goal | Complete recover an account with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Registered Individual |
| Supporting Actors | Identity support; notification worker |
| Authority Scope | Personal account |
| Related Platform Side | Identity portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to recover an account. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Recovery challenge; credentials; Sessions reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Recovery challenge; credentials; Sessions. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-003. |
| Notifications | QMDB-NTF-004; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-IAM-005, QMDB-FR-IAM-006. |
| Planned Implementation Phase | P2. |
## QMDB-UC-006 — Review and terminate active sessions

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-006 |
| Name | Review and terminate active sessions |
| Goal | Complete review and terminate active sessions with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Registered Individual |
| Supporting Actors | Session service; security operator |
| Authority Scope | Own sessions; security operator by incident |
| Related Platform Side | Account security portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to review and terminate active sessions. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Session; device record reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Session; device record. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-004. |
| Notifications | QMDB-NTF-005; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SES-001, QMDB-FR-SES-002, QMDB-FR-SES-003. |
| Planned Implementation Phase | P2. |
## QMDB-UC-007 — Create a workspace

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-007 |
| Name | Create a workspace |
| Goal | Complete create a workspace with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Organization Administrator |
| Supporting Actors | Workspace service |
| Authority Scope | New Workspace under approved onboarding authority |
| Related Platform Side | Organization administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to create a workspace. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Workspace; owner Membership reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Workspace; owner Membership. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-005. |
| Notifications | QMDB-NTF-006; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-TEN-001. |
| Planned Implementation Phase | P2. |
## QMDB-UC-008 — Invite an organization member

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-008 |
| Name | Invite an organization member |
| Goal | Complete invite an organization member with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Organization Administrator |
| Supporting Actors | Notification worker; identity service |
| Authority Scope | Assigned Workspace and Organization |
| Related Platform Side | Organization administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to invite an organization member. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Invitation; Membership reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Invitation; Membership. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-005. |
| Notifications | QMDB-NTF-006; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-ORG-003, QMDB-FR-TEN-002. |
| Planned Implementation Phase | P3. |
## QMDB-UC-009 — Assign scoped authority

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-009 |
| Name | Assign scoped authority |
| Goal | Complete assign scoped authority with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Organization Administrator |
| Supporting Actors | Authorization service; approving role category |
| Authority Scope | Assigned Workspace, organization, geography, resource and time |
| Related Platform Side | Administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to assign scoped authority. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Role assignment; Permission; Administrative Scope reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Role assignment; Permission; Administrative Scope. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-005. |
| Notifications | QMDB-NTF-006; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-AUT-001, QMDB-FR-AUT-002. |
| Planned Implementation Phase | P2. |
## QMDB-UC-010 — Request organization verification

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-010 |
| Name | Request organization verification |
| Goal | Complete request organization verification with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Organization Administrator |
| Supporting Actors | Organization service |
| Authority Scope | Own Workspace Organization |
| Related Platform Side | Organization administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to request organization verification. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Organization; evidence; verification request reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Organization; evidence; verification request. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-010. |
| Notifications | QMDB-NTF-007; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-ORG-002. |
| Planned Implementation Phase | P3. |
## QMDB-UC-011 — Review organization verification

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-011 |
| Name | Review organization verification |
| Goal | Complete review organization verification with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | State or FCT Coordinator |
| Supporting Actors | Organization-governance reviewer; audit service |
| Authority Scope | Assigned administrative and Workspace scope |
| Related Platform Side | Governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to review organization verification. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Verification request; evidence; Organization status reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Verification request; evidence; Organization status. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-010. |
| Notifications | QMDB-NTF-007; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-ORG-002. |
| Planned Implementation Phase | P3. |
## QMDB-UC-012 — Create or link a person profile

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-012 |
| Name | Create or link a person profile |
| Goal | Complete create or link a person profile with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Registered Individual |
| Supporting Actors | People service; registrar where assigned |
| Authority Scope | Own Person or explicitly assigned competition scope |
| Related Platform Side | Individual or registrar portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to create or link a person profile. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Person; Profile; account-person link reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Person; Profile; account-person link. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-007. |
| Notifications | QMDB-NTF-008; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-PPL-001, QMDB-FR-PPL-002. |
| Planned Implementation Phase | P3. |
## QMDB-UC-013 — Merge duplicate person records

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-013 |
| Name | Merge duplicate person records |
| Goal | Complete merge duplicate person records with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Privacy Officer |
| Supporting Actors | Records custodian; approving role category |
| Authority Scope | Named duplicate set and affected scopes |
| Related Platform Side | Governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to merge duplicate person records. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Person records; merge proposal; alias/provenance reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Person records; merge proposal; alias/provenance. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-007. |
| Notifications | QMDB-NTF-008; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-PPL-003, QMDB-FR-PPL-004. |
| Planned Implementation Phase | P3. |
## QMDB-UC-014 — Establish a guardian relationship

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-014 |
| Name | Establish a guardian relationship |
| Goal | Complete establish a guardian relationship with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Guardian |
| Supporting Actors | Guardian verifier; Person subject |
| Authority Scope | Named Person and purpose |
| Related Platform Side | Individual portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to establish a guardian relationship. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Guardian relationship; authority evidence reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Guardian relationship; authority evidence. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-007. |
| Notifications | QMDB-NTF-009; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-GUA-001, QMDB-FR-GUA-002. |
| Planned Implementation Phase | P3. |
## QMDB-UC-015 — Grant competition consent

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-015 |
| Name | Grant competition consent |
| Goal | Complete grant competition consent with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Guardian |
| Supporting Actors | Competition registrar; consent service |
| Authority Scope | Named Minor, Edition, purpose and time |
| Related Platform Side | Individual portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to grant competition consent. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Consent Record; guardian authority reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Consent Record; guardian authority. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-008. |
| Notifications | QMDB-NTF-009; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-GUA-003. |
| Planned Implementation Phase | P3. |
## QMDB-UC-016 — Withdraw media-publication consent

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-016 |
| Name | Withdraw media-publication consent |
| Goal | Complete withdraw media-publication consent with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Guardian |
| Supporting Actors | Media service; privacy officer |
| Authority Scope | Named subject, media purpose and publication |
| Related Platform Side | Individual portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to withdraw media-publication consent. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Consent Record; Media visibility reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Consent Record; Media visibility. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-009. |
| Notifications | QMDB-NTF-009; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-GUA-003, QMDB-FR-MED-004, QMDB-FR-PRI-001. |
| Planned Implementation Phase | P3. |
## QMDB-UC-017 — Import a Qur’an Text Release

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-017 |
| Name | Import a Qur’an Text Release |
| Goal | Complete import a qur’an text release with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Qualified Qur’an Reviewer |
| Supporting Actors | Qur’an import service; security operator |
| Authority Scope | Assigned release-governance mandate |
| Related Platform Side | Qur’an governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to import a qur’an text release. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Source manifest; release candidate; checksum reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Source manifest; release candidate; checksum. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-011. |
| Notifications | QMDB-NTF-010; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-QRF-001, QMDB-FR-QRF-002. |
| Planned Implementation Phase | P4. |
## QMDB-UC-018 — Review and activate a Qur’an Text Release

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-018 |
| Name | Review and activate a Qur’an Text Release |
| Goal | Complete review and activate a qur’an text release with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Qualified Qur’an Reviewer |
| Supporting Actors | Independent qualified reviewer; approving role category |
| Authority Scope | Assigned release and mandate |
| Related Platform Side | Qur’an governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to review and activate a qur’an text release. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Release candidate; comparison evidence; approvals reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Release candidate; comparison evidence; approvals. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-011. |
| Notifications | QMDB-NTF-010; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-QRF-003, QMDB-FR-QRF-004, QMDB-FR-QRF-005. |
| Planned Implementation Phase | P4. |
## QMDB-UC-019 — Create a Competition Series

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-019 |
| Name | Create a Competition Series |
| Goal | Complete create a competition series with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Organization Administrator |
| Supporting Actors | Competition service |
| Authority Scope | Assigned Workspace and organizer organization |
| Related Platform Side | Competition administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to create a competition series. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Competition Series reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Competition Series. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-012. |
| Notifications | QMDB-NTF-011; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CMP-001. |
| Planned Implementation Phase | P5. |
## QMDB-UC-020 — Create a Competition Edition

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-020 |
| Name | Create a Competition Edition |
| Goal | Complete create a competition edition with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Organization Administrator; competition service |
| Authority Scope | Assigned Series and Workspace |
| Related Platform Side | Competition administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to create a competition edition. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Competition Edition; organizer/host/scope dimensions reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Competition Edition; organizer/host/scope dimensions. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-012. |
| Notifications | QMDB-NTF-011; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CMP-001, QMDB-FR-CMP-002. |
| Planned Implementation Phase | P5. |
## QMDB-UC-021 — Configure a category and division

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-021 |
| Name | Configure a category and division |
| Goal | Complete configure a category and division with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Competition-rules governance body |
| Authority Scope | Assigned draft Edition |
| Related Platform Side | Competition administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to configure a category and division. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Category; Division; level; geography dimensions reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Category; Division; level; geography dimensions. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-012. |
| Notifications | QMDB-NTF-011; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CMP-003. |
| Planned Implementation Phase | P5. |
## QMDB-UC-022 — Create and approve a Ruleset Version

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-022 |
| Name | Create and approve a Ruleset Version |
| Goal | Complete create and approve a ruleset version with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition-Rules Governance Body |
| Supporting Actors | Rules author; independent approver |
| Authority Scope | Named competition-rule mandate |
| Related Platform Side | Rules governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to create and approve a ruleset version. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Ruleset Version; schema; test vectors; approvals reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Ruleset Version; schema; test vectors; approvals. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-013. |
| Notifications | QMDB-NTF-012; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-RUL-001, QMDB-FR-RUL-002, QMDB-FR-RUL-003. |
| Planned Implementation Phase | P5. |
## QMDB-UC-023 — Open competition registration

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-023 |
| Name | Open competition registration |
| Goal | Complete open competition registration with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Registrar; notification worker |
| Authority Scope | Approved and published Edition |
| Related Platform Side | Competition administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to open competition registration. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Edition state; registration window reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Edition state; registration window. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-012. |
| Notifications | QMDB-NTF-011; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CMP-002, QMDB-FR-REG-001. |
| Planned Implementation Phase | P5. |
## QMDB-UC-024 — Submit an application

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-024 |
| Name | Submit an application |
| Goal | Complete submit an application with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competitor |
| Supporting Actors | Guardian where required; registrar |
| Authority Scope | Own Person and open Edition/category |
| Related Platform Side | Competition participant portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to submit an application. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Registration; eligibility evidence; consent references reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Registration; eligibility evidence; consent references. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-014. |
| Notifications | QMDB-NTF-013; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-REG-001, QMDB-FR-REG-002. |
| Planned Implementation Phase | P5. |
## QMDB-UC-025 — Nominate a competitor

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-025 |
| Name | Nominate a competitor |
| Goal | Complete nominate a competitor with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Coach |
| Supporting Actors | Competitor; guardian; registrar |
| Authority Scope | Assigned organization and open Edition/category |
| Related Platform Side | Organization competition portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to nominate a competitor. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Nomination; Registration draft; attestation reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Nomination; Registration draft; attestation. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-014. |
| Notifications | QMDB-NTF-013; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-REG-002. |
| Planned Implementation Phase | P5. |
## QMDB-UC-026 — Review eligibility

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-026 |
| Name | Review eligibility |
| Goal | Complete review eligibility with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Registrar |
| Supporting Actors | Competitor; evidence verifier |
| Authority Scope | Assigned Edition/category and Workspace |
| Related Platform Side | Registrar portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to review eligibility. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Registration; evidence; eligibility decision reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Registration; evidence; eligibility decision. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-015. |
| Notifications | QMDB-NTF-013; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-REG-003, QMDB-FR-REG-004. |
| Planned Implementation Phase | P5. |
## QMDB-UC-027 — Create a Participant Snapshot

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-027 |
| Name | Create a Participant Snapshot |
| Goal | Complete create a participant snapshot with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Registrar |
| Supporting Actors | People and records services |
| Authority Scope | Eligible Registration and Edition |
| Related Platform Side | Registrar portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to create a participant snapshot. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Participant Snapshot; source-version references reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Participant Snapshot; source-version references. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-015. |
| Notifications | QMDB-NTF-013; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-REG-005. |
| Planned Implementation Phase | P5. |
## QMDB-UC-028 — Schedule a competition session

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-028 |
| Name | Schedule a competition session |
| Goal | Complete schedule a competition session with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Venue operator; registrar; scheduling service |
| Authority Scope | Assigned Edition, category, venue and time |
| Related Platform Side | Competition operations portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to schedule a competition session. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Session; venue; timetable; assignments reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Session; venue; timetable; assignments. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-016. |
| Notifications | QMDB-NTF-014; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SCH-001, QMDB-FR-SCH-004. |
| Planned Implementation Phase | P6. |
## QMDB-UC-029 — Generate draw order

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-029 |
| Name | Generate draw order |
| Goal | Complete generate draw order with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Scheduling service; registrar |
| Authority Scope | Eligible roster and scheduled round |
| Related Platform Side | Competition operations portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to generate draw order. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Draw seed/configuration; ordered roster reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Draw seed/configuration; ordered roster. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-016. |
| Notifications | QMDB-NTF-014; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SCH-002. |
| Planned Implementation Phase | P6. |
## QMDB-UC-030 — Check in a competitor

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-030 |
| Name | Check in a competitor |
| Goal | Complete check in a competitor with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Registrar |
| Supporting Actors | Competitor; venue operator |
| Authority Scope | Assigned session and eligible Participant Snapshot |
| Related Platform Side | Venue operations portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to check in a competitor. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Check-In; attendance state reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Check-In; attendance state. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-016. |
| Notifications | QMDB-NTF-014; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SCH-003. |
| Planned Implementation Phase | P6. |
## QMDB-UC-031 — Invite and assign a judge

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-031 |
| Name | Invite and assign a judge |
| Goal | Complete invite and assign a judge with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Chief Judge; judge |
| Authority Scope | Assigned Edition/panel/session |
| Related Platform Side | Competition operations portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to invite and assign a judge. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Judge invitation; assignment; acceptance reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Judge invitation; assignment; acceptance. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-017. |
| Notifications | QMDB-NTF-015; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-JDG-001, QMDB-FR-JDG-003. |
| Planned Implementation Phase | P6. |
## QMDB-UC-032 — Declare and review a conflict of interest

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-032 |
| Name | Declare and review a conflict of interest |
| Goal | Complete declare and review a conflict of interest with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Judge |
| Supporting Actors | Chief Judge; independent reviewer |
| Authority Scope | Named assignment/performance relationship |
| Related Platform Side | Judging portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to declare and review a conflict of interest. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Conflict declaration; evidence; decision reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Conflict declaration; evidence; decision. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-018. |
| Notifications | QMDB-NTF-015; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-JDG-002. |
| Planned Implementation Phase | P6. |
## QMDB-UC-033 — Open a performance

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-033 |
| Name | Open a performance |
| Goal | Complete open a performance with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Chief Judge |
| Supporting Actors | Competition Director; assigned judges |
| Authority Scope | Scheduled, checked-in participant and valid panel |
| Related Platform Side | Judging console |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to open a performance. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Performance; state event; ruleset reference reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Performance; state event; ruleset reference. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-019. |
| Notifications | QMDB-NTF-016; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-JDG-004, QMDB-FR-SCR-001. |
| Planned Implementation Phase | P6. |
## QMDB-UC-034 — Assign a passage

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-034 |
| Name | Assign a passage |
| Goal | Complete assign a passage with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Chief Judge |
| Supporting Actors | Qur’an reference service; assigned judges |
| Authority Scope | Open Performance and approved Ruleset Version |
| Related Platform Side | Judging console |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to assign a passage. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Passage Assignment; release coordinates reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Passage Assignment; release coordinates. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-019. |
| Notifications | QMDB-NTF-016; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-JDG-004, QMDB-FR-QRF-006. |
| Planned Implementation Phase | P6. |
## QMDB-UC-035 — Draft a score sheet

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-035 |
| Name | Draft a score sheet |
| Goal | Complete draft a score sheet with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Judge |
| Supporting Actors | Scoring service |
| Authority Scope | Assigned conflict-cleared Performance |
| Related Platform Side | Judging console |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to draft a score sheet. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Score Sheet draft; criterion entries reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Score Sheet draft; criterion entries. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-020. |
| Notifications | QMDB-NTF-016; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SCR-001, QMDB-FR-SCR-002. |
| Planned Implementation Phase | P6. |
## QMDB-UC-036 — Submit and lock a score sheet

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-036 |
| Name | Submit and lock a score sheet |
| Goal | Complete submit and lock a score sheet with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Judge |
| Supporting Actors | Scoring service; audit service |
| Authority Scope | Own current draft for assigned Performance |
| Related Platform Side | Judging console |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to submit and lock a score sheet. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Score Sheet Version; server total; idempotency receipt reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Score Sheet Version; server total; idempotency receipt. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-021, QMDB-EVT-022. |
| Notifications | QMDB-NTF-016; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SCR-002, QMDB-FR-SCR-003, QMDB-FR-SCR-004. |
| Planned Implementation Phase | P6. |
## QMDB-UC-037 — Reopen a score sheet

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-037 |
| Name | Reopen a score sheet |
| Goal | Complete reopen a score sheet with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Chief Judge |
| Supporting Actors | Independent approving role category; original judge |
| Authority Scope | Assigned panel and eligible submitted sheet |
| Related Platform Side | Judging governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to reopen a score sheet. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Reopening request; reason; approval; new version reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Reopening request; reason; approval; new version. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-023, QMDB-EVT-024. |
| Notifications | QMDB-NTF-016; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SCR-005. |
| Planned Implementation Phase | P6. |
## QMDB-UC-038 — Aggregate panel scores

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-038 |
| Name | Aggregate panel scores |
| Goal | Complete aggregate panel scores with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Chief Judge |
| Supporting Actors | Scoring service; panel judges |
| Authority Scope | Complete eligible panel and locked sheets |
| Related Platform Side | Judging governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to aggregate panel scores. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Panel inputs; quorum result; aggregation reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Panel inputs; quorum result; aggregation. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-026. |
| Notifications | QMDB-NTF-016; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SCR-006, QMDB-FR-RSL-001. |
| Planned Implementation Phase | P6. |
## QMDB-UC-039 — Publish provisional results

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-039 |
| Name | Publish provisional results |
| Goal | Complete publish provisional results with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Chief Judge; result service |
| Authority Scope | Completed aggregation and approved publication state |
| Related Platform Side | Competition operations portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to publish provisional results. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Provisional Result Version; live projection reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Provisional Result Version; live projection. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-027. |
| Notifications | QMDB-NTF-017; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-RSL-002, QMDB-FR-LIV-001. |
| Planned Implementation Phase | P7. |
## QMDB-UC-040 — Submit an appeal

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-040 |
| Name | Submit an appeal |
| Goal | Complete submit an appeal with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competitor |
| Supporting Actors | Guardian where authorized; appeal service |
| Authority Scope | Eligible result and open appeal window |
| Related Platform Side | Participant portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to submit an appeal. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Appeal; grounds; evidence; receipt reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Appeal; grounds; evidence; receipt. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-028. |
| Notifications | QMDB-NTF-018; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-APL-001. |
| Planned Implementation Phase | P7. |
## QMDB-UC-041 — Decide an appeal

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-041 |
| Name | Decide an appeal |
| Goal | Complete decide an appeal with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Appeal Reviewer |
| Supporting Actors | Appeal panel; records custodian |
| Authority Scope | Assigned conflict-cleared Appeal |
| Related Platform Side | Appeals governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to decide an appeal. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Appeal Decision; remedy instruction reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Appeal Decision; remedy instruction. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-029. |
| Notifications | QMDB-NTF-018; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-APL-002, QMDB-FR-APL-003. |
| Planned Implementation Phase | P7. |
## QMDB-UC-042 — Finalize competition results

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-042 |
| Name | Finalize competition results |
| Goal | Complete finalize competition results with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Chief Judge; approving role category |
| Authority Scope | All scoring complete and no unresolved blocking appeal |
| Related Platform Side | Competition governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to finalize competition results. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Final Result Version; approval evidence reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Final Result Version; approval evidence. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-030. |
| Notifications | QMDB-NTF-017; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-RSL-003. |
| Planned Implementation Phase | P7. |
## QMDB-UC-043 — Correct a finalized result

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-043 |
| Name | Correct a finalized result |
| Goal | Complete correct a finalized result with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Records custodian |
| Supporting Actors | Independent approver; certificate officer |
| Authority Scope | Named Final Result and approved correction basis |
| Related Platform Side | Records governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to correct a finalized result. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Correction proposal; superseding Result Version reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Correction proposal; superseding Result Version. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-031. |
| Notifications | QMDB-NTF-017; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-RSL-004. |
| Planned Implementation Phase | P7. |
## QMDB-UC-044 — Issue a certificate

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-044 |
| Name | Issue a certificate |
| Goal | Complete issue a certificate with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Certificate Officer |
| Supporting Actors | Certificate service; key custodian |
| Authority Scope | Eligible current Final Result and recipient snapshot |
| Related Platform Side | Certificate administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to issue a certificate. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Certificate; signed representation; document hash reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Certificate; signed representation; document hash. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-032. |
| Notifications | QMDB-NTF-019; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CER-001, QMDB-FR-CER-002. |
| Planned Implementation Phase | P8. |
## QMDB-UC-045 — Verify a certificate

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-045 |
| Name | Verify a certificate |
| Goal | Complete verify a certificate with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | External Verification Consumer |
| Supporting Actors | Certificate verification service |
| Authority Scope | One certificate token or document payload |
| Related Platform Side | Public verification portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to verify a certificate. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Certificate status projection; signature/hash result reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Certificate status projection; signature/hash result. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-032. |
| Notifications | QMDB-NTF-019; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CER-003. |
| Planned Implementation Phase | P8. |
## QMDB-UC-046 — Revoke or supersede a certificate

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-046 |
| Name | Revoke or supersede a certificate |
| Goal | Complete revoke or supersede a certificate with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Certificate Officer |
| Supporting Actors | Independent approver; key custodian |
| Authority Scope | Issued Certificate and approved lifecycle reason |
| Related Platform Side | Certificate administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to revoke or supersede a certificate. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Revocation or supersession record; verification projection reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Revocation or supersession record; verification projection. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-033. |
| Notifications | QMDB-NTF-019; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CER-004. |
| Planned Implementation Phase | P8. |
## QMDB-UC-047 — Import legacy competition records

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-047 |
| Name | Import legacy competition records |
| Goal | Complete import legacy competition records with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Records custodian |
| Supporting Actors | Organization attestor; importer; reviewer |
| Authority Scope | Approved import source and target Workspace |
| Related Platform Side | Records administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to import legacy competition records. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Import Batch; item provenance; reconciliation report reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Import Batch; item provenance; reconciliation report. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-046. |
| Notifications | QMDB-NTF-020; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-IMP-001, QMDB-FR-REC-001. |
| Planned Implementation Phase | P8. |
## QMDB-UC-048 — Upload competition evidence media

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-048 |
| Name | Upload competition evidence media |
| Goal | Complete upload competition evidence media with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Authorized uploader |
| Supporting Actors | Media processing worker; records custodian |
| Authority Scope | Assigned competition record and Workspace |
| Related Platform Side | Media portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to upload competition evidence media. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Upload intent; Media Asset; Evidence Master reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Upload intent; Media Asset; Evidence Master. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-034, QMDB-EVT-035. |
| Notifications | QMDB-NTF-021; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-MED-001, QMDB-FR-MED-002. |
| Planned Implementation Phase | P9. |
## QMDB-UC-049 — Publish approved performance media

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-049 |
| Name | Publish approved performance media |
| Goal | Complete publish approved performance media with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Media publisher |
| Supporting Actors | Guardian; moderator; records custodian |
| Authority Scope | Approved derivative and valid ownership/consent |
| Related Platform Side | Media administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to publish approved performance media. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Publication decision; derivative; consent reference reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Publication decision; derivative; consent reference. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-038. |
| Notifications | QMDB-NTF-021; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-MED-003, QMDB-FR-MED-004. |
| Planned Implementation Phase | P9. |
## QMDB-UC-050 — Create a Recitation Clip

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-050 |
| Name | Create a Recitation Clip |
| Goal | Complete create a recitation clip with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Reciter |
| Supporting Actors | Media worker; Qur’an reference service |
| Authority Scope | Own eligible profile and safe processed media |
| Related Platform Side | Community portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to create a recitation clip. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Recitation Clip draft; tags; media link reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Recitation Clip draft; tags; media link. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-039. |
| Notifications | QMDB-NTF-022; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SOC-001. |
| Planned Implementation Phase | P10. |
## QMDB-UC-051 — Approve a minor’s Recitation Clip

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-051 |
| Name | Approve a minor’s Recitation Clip |
| Goal | Complete approve a minor’s recitation clip with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Guardian |
| Supporting Actors | Moderator; consent service |
| Authority Scope | Valid Guardian relationship and pending Minor Clip |
| Related Platform Side | Guardian portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to approve a minor’s recitation clip. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Guardian decision; Clip visibility; Consent reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Guardian decision; Clip visibility; Consent. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-039. |
| Notifications | QMDB-NTF-022; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SOC-001, QMDB-FR-SOC-002. |
| Planned Implementation Phase | P10. |
## QMDB-UC-052 — Report content

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-052 |
| Name | Report content |
| Goal | Complete report content with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Community member |
| Supporting Actors | Moderation service |
| Authority Scope | Visible reportable content |
| Related Platform Side | Community portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to report content. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Content Report; protected evidence reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Content Report; protected evidence. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-040. |
| Notifications | QMDB-NTF-023; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-MOD-001, QMDB-FR-SOC-003. |
| Planned Implementation Phase | P10. |
## QMDB-UC-053 — Review a moderation case

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-053 |
| Name | Review a moderation case |
| Goal | Complete review a moderation case with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Media Moderator |
| Supporting Actors | Community-Safety Officer; content owner |
| Authority Scope | Assigned conflict-cleared case |
| Related Platform Side | Moderation portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to review a moderation case. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Moderation Case; decision; action reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Moderation Case; decision; action. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-040. |
| Notifications | QMDB-NTF-023; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-MOD-001, QMDB-FR-MOD-002. |
| Planned Implementation Phase | P10. |
## QMDB-UC-054 — Appeal a moderation action

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-054 |
| Name | Appeal a moderation action |
| Goal | Complete appeal a moderation action with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Content owner |
| Supporting Actors | Independent appeal reviewer; moderator |
| Authority Scope | Eligible moderation decision in appeal window |
| Related Platform Side | Community safety portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to appeal a moderation action. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Content Appeal; review decision reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Content Appeal; review decision. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-040. |
| Notifications | QMDB-NTF-023; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-MOD-002. |
| Planned Implementation Phase | P10. |
## QMDB-UC-055 — Search public competition records

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-055 |
| Name | Search public competition records |
| Goal | Complete search public competition records with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Public Visitor |
| Supporting Actors | Search service |
| Authority Scope | Public approved records only |
| Related Platform Side | Public discovery portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to search public competition records. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Search query; result projection reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Search query; result projection. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-041. |
| Notifications | QMDB-NTF-024; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-SRH-001, QMDB-FR-SRH-003. |
| Planned Implementation Phase | P11. |
## QMDB-UC-056 — Generate an authorized administrative report

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-056 |
| Name | Generate an authorized administrative report |
| Goal | Complete generate an authorized administrative report with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | National Coordinator |
| Supporting Actors | Reporting service; privacy service |
| Authority Scope | Assigned administrative/Workspace scope |
| Related Platform Side | Reporting portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to generate an authorized administrative report. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Report request; freshness-labeled output reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Report request; freshness-labeled output. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-041. |
| Notifications | QMDB-NTF-024; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-RPT-001, QMDB-FR-RPT-003. |
| Planned Implementation Phase | P11. |
## QMDB-UC-057 — Export authorized records

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-057 |
| Name | Export authorized records |
| Goal | Complete export authorized records with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Authorized coordinator |
| Supporting Actors | Approving role category; reporting worker |
| Authority Scope | Current scoped export capability |
| Related Platform Side | Reporting portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to export authorized records. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Export request; approval; expiring artifact reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Export request; approval; expiring artifact. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-041. |
| Notifications | QMDB-NTF-024; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-RPT-002. |
| Planned Implementation Phase | P11. |
## QMDB-UC-058 — Submit a personal-data request

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-058 |
| Name | Submit a personal-data request |
| Goal | Complete submit a personal-data request with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Data-Protection or Privacy Officer |
| Supporting Actors | Data subject; identity verifier; records custodian |
| Authority Scope | Named verified subject and request case |
| Related Platform Side | Privacy portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to submit a personal-data request. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Privacy Request; tasks; delivery manifest reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Privacy Request; tasks; delivery manifest. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-041. |
| Notifications | QMDB-NTF-025; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-PRI-002, QMDB-FR-PRI-003. |
| Planned Implementation Phase | P12. |
## QMDB-UC-059 — Use temporary support access

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-059 |
| Name | Use temporary support access |
| Goal | Complete use temporary support access with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Support Officer |
| Supporting Actors | Approver; requesting user; audit service |
| Authority Scope | Named support case, resources and time |
| Related Platform Side | Support portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to use temporary support access. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Support Access Grant; access events reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Support Access Grant; access events. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-043. |
| Notifications | QMDB-NTF-026; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-OPS-001. |
| Planned Implementation Phase | P2. |
## QMDB-UC-060 — Activate break-glass access

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-060 |
| Name | Activate break-glass access |
| Goal | Complete activate break-glass access with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Break-Glass Administrator |
| Supporting Actors | Security reviewer; approving role category |
| Authority Scope | Declared incident and least emergency scope |
| Related Platform Side | Security operations portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to activate break-glass access. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Break-Glass Grant; continuous audit; review task reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Break-Glass Grant; continuous audit; review task. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-042. |
| Notifications | QMDB-NTF-027; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-OPS-002. |
| Planned Implementation Phase | P2. |
## QMDB-UC-061 — Synchronize offline score submissions

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-061 |
| Name | Synchronize offline score submissions |
| Goal | Complete synchronize offline score submissions with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Venue synchronization operator |
| Supporting Actors | Judge; Chief Judge; central scoring service |
| Authority Scope | Signed device package and restored connection |
| Related Platform Side | Venue Edge Node |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to synchronize offline score submissions. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Offline Submission Package; receipts; conflicts; report reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Offline Submission Package; receipts; conflicts; report. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-045. |
| Notifications | QMDB-NTF-028; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-OFF-001, QMDB-FR-OFF-002. |
| Planned Implementation Phase | P13. |
## QMDB-UC-062 — Recover live public updates after connection loss

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-062 |
| Name | Recover live public updates after connection loss |
| Goal | Complete recover live public updates after connection loss with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Platform operator |
| Supporting Actors | Live projection worker; venue operator |
| Authority Scope | Authoritative event stream and last public sequence |
| Related Platform Side | Live public portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to recover live public updates after connection loss. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Projection checkpoint; replay; static snapshot reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Projection checkpoint; replay; static snapshot. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-044. |
| Notifications | QMDB-NTF-029; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-LIV-002, QMDB-FR-OPS-003. |
| Planned Implementation Phase | P7. |
## QMDB-UC-063 — Suspend an organization

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-063 |
| Name | Suspend an organization |
| Goal | Complete suspend an organization with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Organization-governance reviewer |
| Supporting Actors | Security operator; affected competition directors |
| Authority Scope | Assigned governance scope and reasoned case |
| Related Platform Side | Governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to suspend an organization. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Organization state version; affected authority review reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Organization state version; affected authority review. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-010. |
| Notifications | QMDB-NTF-007; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-ORG-004, QMDB-FR-AUT-002. |
| Planned Implementation Phase | P3. |
## QMDB-UC-064 — Cancel a competition edition

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-064 |
| Name | Cancel a competition edition |
| Goal | Complete cancel a competition edition with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Competition Director |
| Supporting Actors | Approving role category; registrar; records custodian |
| Authority Scope | Assigned Edition and governed cancellation reason |
| Related Platform Side | Competition administration portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to cancel a competition edition. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Edition state; cancellation reason; dependent actions reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Edition state; cancellation reason; dependent actions. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-012. |
| Notifications | QMDB-NTF-011; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CMP-004. |
| Planned Implementation Phase | P5. |
## QMDB-UC-065 — Archive a completed competition edition

| Field | Specification |
| --- | --- |
| Use Case ID | QMDB-UC-065 |
| Name | Archive a completed competition edition |
| Goal | Complete archive a completed competition edition with current authority, validated domain state, durable evidence, and no silent mutation. |
| Primary Actor | Records custodian |
| Supporting Actors | Competition Director; platform archive service |
| Authority Scope | Certified/finalized Edition and closed dependent workflows |
| Related Platform Side | Records governance portal |
| Preconditions | The actor identity or public context is established; required source records exist in an eligible version/state; dependencies and policy are available. |
| Trigger | The actor requests to archive a completed competition edition. |
| Main Success Flow | 1. The interface collects the minimum required input and client correlation. 2. The server resolves actor, Workspace/administrative/assignment scope and current record versions. 3. Domain validation and separation-of-duties checks pass. 4. The authoritative owner records the outcome and audit/outbox evidence in the defined transaction. 5. The response identifies the authoritative status; projections and notices follow idempotently. |
| Alternative Flows | A permitted draft, narrower scope, additional evidence, second approval, step-up authentication, queued processing, or low-bandwidth path is used without weakening the final gate. |
| Exception Flows | Invalid, stale, conflicted, expired, duplicate, cross-scope, unsafe, or unavailable requests fail closed; committed-but-unacknowledged writes are recovered by receipt/idempotency lookup rather than blind replay. |
| Postconditions | Edition archive state; archive manifest reflect one authorized outcome or remain unchanged; required follow-up work is explicit. |
| Records Affected | Edition archive state; archive manifest. |
| Audit Events | QMDB-AEV-001, QMDB-AEV-003 and the sensitive-action category applicable to the owning module. |
| Domain Events | QMDB-EVT-030. |
| Notifications | QMDB-NTF-020; delivery failure cannot reverse the source transaction. |
| Security Requirements | Deny by default; server-derived tenant/resource scope; current membership; least privilege; step-up and no self-approval for high-risk actions; rate/replay/idempotency controls. |
| Privacy Requirements | Collect and expose minimum purpose-bound fields; re-evaluate visibility; protect Minor, guardian, evidence, security, and reporter data; use non-enumerating denial. |
| Accessibility Requirements | Keyboard-complete, labeled and screen-reader announced, non-color state, RTL-aware, high-contrast/reduced-motion compatible, recoverable errors, and low-bandwidth alternative. |
| Related Functional Requirements | QMDB-FR-CMP-004, QMDB-FR-REC-001. |
| Planned Implementation Phase | P8. |

## Coverage and change control

The catalog contains exactly 65 use cases required by QMDB-P0-B02. Additional implementation-level variants must retain the parent identifier in traceability, must not combine independent authoritative commands into an ambiguous transaction, and must update related requirements, capabilities, workflows, events, edge cases, and acceptance evidence.

## Related documents

- [Functional requirements index](P0-B02-functional-requirements-index.md)
- [User journeys](P0-B02-user-journeys.md)
- [Permission and capability matrix](P0-B02-permission-capability-matrix.md)
- [Traceability matrix](P0-B02-traceability-matrix.md)

