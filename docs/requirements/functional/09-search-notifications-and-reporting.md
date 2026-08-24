# Search, Notifications, and Reporting Functional Requirements

**Document ID:** QMDB-P0-B02-FR-09  
**Version:** 1.0.0  
**Status:** Approved batch baseline  
**Baseline:** QMDB-BL-001  
**Owning phase:** P0; implementation phases P11 and P12

## Purpose and scope

This specification defines privacy-aware discovery, reliable multichannel notification, and scope-safe reporting and export. Search, notifications, dashboards, and exports are projections or deliveries; they never replace their authoritative source records.

## Common controls

- Public and administrative search use different projection policies and both re-evaluate visibility.
- Reporting access is constrained by workspace and administrative geography; national oversight does not imply unrestricted editing.
- Every asynchronous output identifies its source, generation time, competition-local/UTC interpretation, and material freshness.
- Notification and analytics failure remains isolated from the source transaction and authoritative scoring capacity.

### QMDB-FR-SRH-001 — Search public and authorized records

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SRH-001 | Title | Search public and authorized records |
| Requirement Statement | The platform shall return only records and fields visible to the requester under public, tenant, administrative-scope, minor-safety, and record-status policy. | Rationale | Prevents discovery from becoming an authorization bypass. |
| Priority | Critical | Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Primary Actors | Public visitor or authorized user | Supporting Actors | Search service; privacy service |
| Owning Module | Search and Discovery | Related Modules | Competition Records; Organizations; Certificates; Media |
| Preconditions | A supported search surface and visibility policy are available. | Trigger | Requester submits a search. |
| Inputs | Query, filters, sort, cursor, locale, requester context. | Validation Rules | Normalize safely; validate supported geography, level, category, year, Riwāyah, passage, provenance, status, and media filters; cap page size. |
| Authorization and Scope | Authorization is applied before result projection; administrative search is tenant/scope bound; public search never exposes restricted minor data. | Normal Functional Behavior | Return paginated, ranked, visibility-filtered summaries and an authoritative-record link where permitted. |
| Alternative Behavior | Return empty-state guidance, spelling/Arabic normalization variants, or verification-specific search without broad disclosure. | Failure Behavior | Fail closed on policy or index uncertainty; do not confirm restricted record existence. |
| Records Read | Search index/read models, current visibility, authoritative status. | Records Created | Search security telemetry only when risk policy requires. |
| Records Updated | No authoritative record; query analytics are minimized. | Records Versioned or Superseded | Superseded search documents are retired; source record versions remain authoritative. |
| Audit Requirements | Scope decision, filters, result class, denial/anomaly, correlation without sensitive raw query where avoidable. | Domain Events | QMDB-EVT-041 |
| Notifications | None for normal search; security operations for detected abuse. | Privacy and Data Classification | Public summaries Public; administrative results inherit source classification; queries may be Personal. |
| Accessibility and Interaction Requirements | Keyboard filter controls, Arabic/RTL support, announced result counts, clear empty and restricted states. | Postconditions | Only authorized summaries are returned with freshness information when material. |
| Related Business Invariants | INV-001, INV-005, INV-017, INV-022 | Related P0-B01 Requirements | QMDB-SEC-003, QMDB-PRIV-003, QMDB-UX-001 |
| Verification Method | Tenant isolation, minor privacy, status, filter, pagination, Arabic-search, and leakage tests. | Acceptance Criteria | Changing workspace or record identifiers cannot expose a restricted or cross-tenant record. |

### QMDB-FR-SRH-002 — Maintain privacy-aware search projections

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SRH-002 | Title | Maintain privacy-aware search projections |
| Requirement Statement | The platform shall build search documents from approved projection fields and remove or supersede them when source visibility or status changes. | Rationale | Keeps eventual-consistency indexes aligned with privacy and official state. |
| Priority | Critical | Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Primary Actors | Search indexer | Supporting Actors | Record owner module; privacy officer |
| Owning Module | Search and Discovery | Related Modules | Eventing; all searchable modules |
| Preconditions | A durable source event and approved search projection schema exist. | Trigger | A searchable source is created, corrected, restricted, revoked, superseded, or deleted. |
| Inputs | Source ID/version, workspace, visibility, projection fields, event position. | Validation Rules | Accept only allow-listed fields; reject stale events; use source version and event ID for idempotency; exclude precise minor location/contact. |
| Authorization and Scope | Indexer has read-only projection scope and cannot mutate sources. | Normal Functional Behavior | Upsert or retire the search document and record processed event position. |
| Alternative Behavior | Rebuild from authoritative sources; display material index lag; route privacy removals at higher priority. | Failure Behavior | On failure retain event for retry/dead letter; policy change can force public query suppression until rebuilt. |
| Records Read | Authoritative source, visibility policy, event/outbox. | Records Created | Search document and checkpoint. |
| Records Updated | Index freshness and health projection. | Records Versioned or Superseded | Documents are versioned/superseded by source version, never used as authoritative history. |
| Audit Requirements | Event ID, source/version, projection schema, outcome, lag, rebuild actor. | Domain Events | QMDB-EVT-044 |
| Notifications | Operations on sustained lag; privacy/security on removal failure. | Privacy and Data Classification | Index contains minimum approved projection only; restricted source fields remain absent. |
| Accessibility and Interaction Requirements | Search surfaces expose freshness notice without blocking assistive technology. | Postconditions | Projection converges idempotently or is safely suppressed. |
| Related Business Invariants | INV-005, INV-015, INV-017, INV-023, INV-026 | Related P0-B01 Requirements | QMDB-DATA-005, QMDB-SEC-005, QMDB-OPS-002 |
| Verification Method | Out-of-order, duplicate, rebuild, lag, and privacy-removal tests. | Acceptance Criteria | A superseded, revoked, or newly restricted record is not served as current public data. |

### QMDB-FR-SRH-003 — Represent record provenance and status in discovery

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-SRH-003 | Title | Represent record provenance and status in discovery |
| Requirement Statement | The platform shall label search results with current official status, provenance classification, correction or revocation state, and source freshness when those affect interpretation. | Rationale | Prevents projections from misleading users about record authority. |
| Priority | High | Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Primary Actors | Public visitor or authorized analyst | Supporting Actors | Records service; certificate service |
| Owning Module | Search and Discovery | Related Modules | Competition Records; Certificates; Legacy Imports |
| Preconditions | The requester may view the result and the source exposes an approved status projection. | Trigger | Search results are composed. |
| Inputs | Result projection, source status, provenance, supersession link, freshness. | Validation Rules | Do not label provisional, imported-unverified, superseded, revoked, or archived data as current final data. |
| Authorization and Scope | Public sees only approved labels; restricted correction reason remains protected. | Normal Functional Behavior | Show plain status and link to the current authorized record; distinguish legacy provenance. |
| Alternative Behavior | If source is unavailable, show unavailable/stale rather than inferring current authority. | Failure Behavior | Omit or suppress ambiguous records; never invent a status from index age. |
| Records Read | Search document and authoritative status endpoint. | Records Created | None. |
| Records Updated | None beyond non-authoritative projection refresh. | Records Versioned or Superseded | Former labels remain traceable through source history, not public index history. |
| Audit Requirements | Projection/version consulted and any mismatch detected. | Domain Events | QMDB-EVT-030, QMDB-EVT-033 |
| Notifications | None unless a verification or integrity anomaly occurs. | Privacy and Data Classification | Labels Public; reasons and evidence retain source classification. |
| Accessibility and Interaction Requirements | Status is textual and not color-only; abbreviations expanded; dates localized with timezone. | Postconditions | Users can distinguish current, provisional, superseded, revoked, and legacy records. |
| Related Business Invariants | INV-003, INV-014, INV-017, INV-026 | Related P0-B01 Requirements | QMDB-DATA-004, QMDB-CER-004, QMDB-UX-002 |
| Verification Method | Status-label, stale-projection, supersession and accessibility tests. | Acceptance Criteria | A cached provisional or revoked item cannot be presented as a current final or valid record. |

### QMDB-FR-NTF-001 — Create and deliver policy-governed notifications

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-NTF-001 | Title | Create and deliver policy-governed notifications |
| Requirement Statement | The platform shall create deduplicated notification intents from durable events and deliver them through allowed channels according to mandatory policy, user preference, privacy classification, and retry rules. | Rationale | Makes notifications reliable without treating them as authoritative state. |
| Priority | High | Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Primary Actors | Affected user | Supporting Actors | Notification worker; producing module |
| Owning Module | Notifications | Related Modules | Eventing; Identity and Access; Privacy |
| Preconditions | A cataloged event identifies an eligible recipient and notification template. | Trigger | A durable event is consumed. |
| Inputs | Event ID, recipient, template/version, locale, permitted data, urgency. | Validation Rules | Validate recipient, channel, address verification, template, privacy, preference, dedupe key, and expiry. |
| Authorization and Scope | Workers receive minimum data; cross-tenant recipient resolution is forbidden; sensitive channels are policy-restricted. | Normal Functional Behavior | Create intent, select allowed channels, deliver idempotently, and record receipts without message secrets. |
| Alternative Behavior | Use fallback allowed channel, defer during quiet hours, or suppress optional notice by preference. | Failure Behavior | Retry boundedly; dead-letter terminal failure; mandatory security notice ignores ordinary opt-out but not channel safety. |
| Records Read | Event, recipient endpoints, preferences, templates. | Records Created | Notification intent, attempts, delivery receipt. |
| Records Updated | Delivery status and dead-letter state. | Records Versioned or Superseded | Template and intent history are immutable; new attempts append. |
| Audit Requirements | Event, recipient reference, channel, template version, outcome, provider correlation, no secret body. | Domain Events | QMDB-EVT-044 |
| Notifications | The notification itself; operations on terminal required-notice failure. | Privacy and Data Classification | Payload minimized; security and minor-related notices avoid sensitive lock-screen content. |
| Accessibility and Interaction Requirements | Accessible HTML/plain-text alternatives, meaningful subject, language/direction support. | Postconditions | One logical intent exists and delivery reaches a final or recoverable state. |
| Related Business Invariants | INV-005, INV-015, INV-023, INV-027 | Related P0-B01 Requirements | QMDB-SEC-005, QMDB-UX-001, QMDB-OPS-002 |
| Verification Method | Dedupe, preference, mandatory override, retry, dead-letter, privacy and accessibility tests. | Acceptance Criteria | Duplicate event delivery does not send duplicate logical notices and optional preferences cannot suppress mandatory security notice. |

### QMDB-FR-NTF-002 — Expose notification status and recover delivery failures

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-NTF-002 | Title | Expose notification status and recover delivery failures |
| Requirement Statement | The platform shall expose in-app read state and authorized delivery status while keeping channel failures recoverable and isolated from source transactions. | Rationale | Prevents messaging outages from rolling back official work. |
| Priority | High | Planned Implementation Phase | P12 — Quality, Accessibility, and Production Hardening |
| Primary Actors | Notification recipient | Supporting Actors | Support operator; notification worker |
| Owning Module | Notifications | Related Modules | Platform Operations; Audit |
| Preconditions | Notification intent exists independently of the source transaction. | Trigger | Recipient views/marks a notice or worker records a delivery outcome. |
| Inputs | Intent, recipient, delivery attempt, read action, device context. | Validation Rules | Verify recipient ownership; use idempotent read markers; redact provider details; enforce expiry. |
| Authorization and Scope | Recipients access own notifications; support sees restricted metadata only through temporary approved access. | Normal Functional Behavior | List paginated notices, mark read, show safe delivery state, and provide action link to authoritative source. |
| Alternative Behavior | Allow resend when policy permits; use an alternative verified channel; retain failed mandatory notice for review. | Failure Behavior | Show source action as completed even when notification failed; dead-letter after bounded retries. |
| Records Read | Notification intents/attempts, source links. | Records Created | Resend intent if allowed. |
| Records Updated | Read timestamp, attempt status. | Records Versioned or Superseded | Attempts append; expired intents remain auditable under retention policy. |
| Audit Requirements | Viewer, action, resend reason, support grant, provider outcome. | Domain Events | QMDB-EVT-044 |
| Notifications | Operations/security for repeated required-notice failure. | Privacy and Data Classification | Provider addresses and failure details Restricted. |
| Accessibility and Interaction Requirements | Keyboard-operable list, screen-reader announcements, no infinite-scroll-only access. | Postconditions | Notification state is usable and failures do not alter authoritative source records. |
| Related Business Invariants | INV-005, INV-015, INV-023 | Related P0-B01 Requirements | QMDB-SEC-005, QMDB-UX-002, QMDB-OPS-002 |
| Verification Method | Ownership, pagination, retry, support-access, and transaction-isolation tests. | Acceptance Criteria | A notification provider outage cannot reverse a registration, score, result, or consent decision. |

### QMDB-FR-RPT-001 — Produce scope-safe operational and aggregate reports

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RPT-001 | Title | Produce scope-safe operational and aggregate reports |
| Requirement Statement | The platform shall produce each report from an identified authoritative source or freshness-labeled read model using the requester's current scope and privacy policy. | Rationale | Enables national through competition reporting without cross-scope leakage. |
| Priority | Critical | Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Primary Actors | Authorized coordinator or organization officer | Supporting Actors | Reporting service; privacy service |
| Owning Module | Reporting and Analytics | Related Modules | All domain modules; Geography; Organizations |
| Preconditions | Actor has a report capability for a defined administrative or workspace scope. | Trigger | Actor requests dashboard/report. |
| Inputs | Report type, filters, administrative scope, workspace, time range, grouping. | Validation Rules | Validate scope containment, filters, small-group rules, source/freshness, and data-class entitlement. |
| Authorization and Scope | National oversight is read/report authority, not unrestricted editing; report rows are re-authorized at execution. | Normal Functional Behavior | Return paginated/aggregated results with source, timestamp, timezone, definitions, and freshness. |
| Alternative Behavior | Queue large reports; return a job reference; suppress small groups; use current source if read model stale and feasible. | Failure Behavior | Deny or safely redact on scope change; mark unavailable rather than return stale-as-current data. |
| Records Read | Authoritative sources/read models, capability/scope, privacy thresholds. | Records Created | Report job and output manifest for asynchronous work. |
| Records Updated | Job status and dashboard cache. | Records Versioned or Superseded | Regenerated outputs supersede expiring artifacts; report definitions are versioned. |
| Audit Requirements | Requester, effective scope, report type, filters, source version, row/aggregate class. | Domain Events | QMDB-EVT-041 |
| Notifications | Requester on asynchronous completion/failure; security on anomalous bulk access. | Privacy and Data Classification | Classification equals most sensitive included field; public statistics are disclosure-controlled. |
| Accessibility and Interaction Requirements | Accessible tables/summaries, keyboard filters, textual chart alternatives, RTL, timezone clarity. | Postconditions | Report is authorized, interpretable, freshness-labeled, and scope-safe. |
| Related Business Invariants | INV-001, INV-005, INV-011, INV-017, INV-022 | Related P0-B01 Requirements | QMDB-DATA-005, QMDB-SEC-003, QMDB-PRIV-003 |
| Verification Method | Scope, small-group, freshness, load, accessibility and national-oversight tests. | Acceptance Criteria | A coordinator receives only assigned-area aggregates and cannot turn report access into edit authority. |

### QMDB-FR-RPT-002 — Generate controlled exports

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RPT-002 | Title | Generate controlled exports |
| Requirement Statement | The platform shall require current export authority, declared purpose, data minimization, step-up authentication for sensitive or high-volume exports, and an expiring protected artifact. | Rationale | Reduces exfiltration risk and makes bulk disclosure accountable. |
| Priority | Critical | Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Primary Actors | Authorized exporter | Supporting Actors | Approving role category; privacy or security officer |
| Owning Module | Reporting and Analytics | Related Modules | Audit and Integrity; Security Operations; Privacy |
| Preconditions | Report is authorized and export capability exists for its classification and volume. | Trigger | Actor requests export. |
| Inputs | Report/query reference, fields, format, purpose, volume estimate, expiry. | Validation Rules | Reauthorize before generation and download; exclude fields not needed; enforce thresholds and approval; scan output metadata. |
| Authorization and Scope | High-volume/audit exports require no self-approval and exact authority remains OD-034; step-up is mandatory; public actors denied. | Normal Functional Behavior | Create audited job, generate CSV/controlled spreadsheet, encrypt or protect as policy requires, and issue short-lived download. |
| Alternative Behavior | Narrow fields/rows, require second approval, or cancel if permission is revoked while pending. | Failure Behavior | Delete unclaimed artifact on expiry; deny download after scope revocation; retain audit metadata, not unnecessary output copies. |
| Records Read | Report definition, source rows, capability, approvals, current membership. | Records Created | Export request, approval, artifact manifest. |
| Records Updated | Job and expiry status. | Records Versioned or Superseded | Regeneration creates a new artifact; expired artifacts are destroyed while request audit remains. |
| Audit Requirements | Requester, approver, purpose, fields, filters, row count, hash, downloads, expiry. | Domain Events | QMDB-EVT-041 |
| Notifications | Approver/requester/security depending classification and outcome. | Privacy and Data Classification | Potentially Highly Restricted; output classification and handling notice are explicit. |
| Accessibility and Interaction Requirements | Accessible progress, downloadable CSV semantics, error summary, no color-only status. | Postconditions | Only a minimized, approved, expiring export is available to the still-authorized requester. |
| Related Business Invariants | INV-001, INV-005, INV-019, INV-023 | Related P0-B01 Requirements | QMDB-SEC-003, QMDB-SEC-005, QMDB-PRIV-003 |
| Verification Method | Authorization-race, approval, step-up, expiry, field-minimization and audit tests. | Acceptance Criteria | Revoked permission blocks a pending export and each generation/download is auditable. |

### QMDB-FR-RPT-003 — Protect core operations from reporting and analytics load

| Field | Value | Field | Value |
| --- | --- | --- | --- |
| Requirement ID | QMDB-FR-RPT-003 | Title | Protect core operations from reporting and analytics load |
| Requirement Statement | The platform shall isolate, bound, queue, and degrade reporting and social analytics workloads before they affect authoritative writes or live scoring. | Rationale | Preserves operational priority during national events and demand spikes. |
| Priority | Critical | Planned Implementation Phase | P11 — Search, Analytics, and National Reporting |
| Primary Actors | Platform operator | Supporting Actors | Report requester; database operator |
| Owning Module | Reporting and Analytics | Related Modules | Platform Operations; Scoring; Eventing |
| Preconditions | Service budgets, replica/read-model health, and queue metrics exist. | Trigger | Demand, replica lag, or resource threshold is exceeded. |
| Inputs | Workload class, priority, cost estimate, health, freshness, queue depth. | Validation Rules | Reject unbounded filters; cap synchronous cost; route to read models/replicas; queue large work; reserve core capacity. |
| Authorization and Scope | Operators may tune approved limits but cannot bypass row authorization or export controls. | Normal Functional Behavior | Serve freshness-labeled cached aggregates, queue work, pause nonessential analytics, and preserve scoring capacity. |
| Alternative Behavior | Return job reference, reduced dataset, static verified snapshot, or maintenance notice. | Failure Behavior | Cancel/throttle safely; never route analytics writes into authoritative score transactions. |
| Records Read | Queue, health, read-model checkpoint, service-budget configuration. | Records Created | Report job/dead-letter record where applicable. |
| Records Updated | Health, circuit, queue, freshness projections. | Records Versioned or Superseded | Operational configuration is versioned; intervention history is retained. |
| Audit Requirements | Threshold, decision, operator, job, dependency, recovery outcome. | Domain Events | QMDB-EVT-044 |
| Notifications | Operators/requester on material delay or failure. | Privacy and Data Classification | Telemetry and caches contain only approved projections. |
| Accessibility and Interaction Requirements | Low-bandwidth static view, accessible delay/status message, retry guidance. | Postconditions | Core official workflows remain available or recover first. |
| Related Business Invariants | INV-015, INV-016, INV-017, INV-027 | Related P0-B01 Requirements | QMDB-OPS-001, QMDB-OPS-002, QMDB-UX-002 |
| Verification Method | Load, replica-lag, queue, failure, and scoring-isolation tests. | Acceptance Criteria | Reporting or social-analytics overload cannot prevent accepted official score submissions. |

## Related documents

- [Functional requirements index](../P0-B02-functional-requirements-index.md)
- [Event and notification catalog](../P0-B02-event-and-notification-catalog.md)
- [Permission and capability matrix](../P0-B02-permission-capability-matrix.md)
- [P0-B01 baseline requirements](../P0-B01-requirements.md)
