# Organization Memberships, Staff, Leadership, and Person Affiliations Standard

| Control | Value |
| --- | --- |
| Batch | QMDB-P3-B04 — Organization Memberships, Staff, Leadership, and Person Affiliations |
| Status | Implemented production standard |
| Data ownership | Workspace-scoped Organization aggregate; global Person references are private and never form a directory |
| Effective date | 2026-09-02 |

## Boundary

An Organization affiliation is a private, consent-based relationship between one existing global Person and one active Organization in a selected Workspace. It is not a Workspace membership, authorization-role assignment, public profile, contact directory, or a claim that a Person is legally verified. A registry code may be entered only by an already-authorized Organization operator; it is not a discovery API and no response reveals whether a Person exists, has an account, or has declined.

## Lifecycle and consent

```text
PENDING_ACCEPTANCE → ACTIVE → SUSPENDED → ACTIVE
                    ↘ DECLINED
                    ↘ WITHDRAWN
                    ↘ EXPIRED
ACTIVE or SUSPENDED → ENDED
```

- An authorized Organization operator creates a pending request with an opaque public ID, a generated `QMA-` code, proposed assignments and a bounded expiry.
- Only the Person's active self-linked account or a currently authorized Guardian with `PROFILE_MANAGEMENT` authority can accept or decline. A Guardian response records Guardian authority and retains its guardianship reference.
- Self or Guardian leave, Organization withdrawal, suspension, resume and end each use optimistic version checks; the step-up policy is enforced where the operation changes the risk profile.
- The scheduler expires pending requests in a bounded batch. Expiry has its own security-notification type and must never be represented as a voluntary decline.
- `organization_affiliation_status_events` is insert-only: database triggers reject update and delete operations.

## Assignments and leadership

The governed active role catalog is exactly: `MEMBER`, `STUDENT`, `MEMORIZER`, `RECITER`, `TEACHER`, `QURAN_TEACHER`, `IMAM`, `MUADHDHIN`, `STAFF`, `VOLUNTEER`, `LEADER`, and `REPRESENTATIVE`.

- An affiliation has at least one role and exactly one open primary role.
- It may have multiple active Units, with at most one open primary Unit. Role Unit scope must reference an included active Unit in the same Organization and Workspace.
- Role definitions requiring a Person role are checked before request, acceptance and assignment replacement.
- Leadership-affecting request and assignment changes require the dedicated leadership permission and phishing-resistant step-up. Ordinary affiliation management cannot bypass that check.
- Assignment replacement preserves history: old active assignments are removed, not rewritten.

## Authorization, tenancy, and privacy

- Every repository query constrains records by the composite Workspace and Organization ownership keys. Composite foreign keys reject cross-Workspace child references at the MySQL boundary.
- Workspace roster/view operations require `workspace.organization_affiliations.view`; request and lifecycle management require `workspace.organization_affiliations.manage`; assignment changes require `workspace.organization_affiliation_assignments.manage`; leadership changes require `workspace.organization_leadership.manage` plus the applicable step-up action.
- Account inventory routes return only affiliations for the authenticated Person or an active, authorized Guardian relationship. There is no public Person, account, Guardian, Organization-affiliation, or registry-code search.
- Pending, declined, withdrawn and expired roster entries suppress Person names and public Person identifiers.
- Private responses use no-store, no-referrer and noindex controls. Mutations are POST-only, CSRF-protected, idempotency-backed, rate-limited, audited and notification-backed.

## Presentation and accessibility

All workflow routes render a complete server HTML form first. When JavaScript is available, the same forms are loaded in controlled dialogs, submit through the approved fragment protocol, and redirect only through the validated `X-QMDB-Navigate` response. Roster filtering remains an ordinary GET form without JavaScript and refreshes only the private roster fragment when enhanced. Dialogs retain an explicit fallback link, keyboard-close control, focus return, live region and no unsafe fragment content.

## Data governance and operations

Tables `organization_affiliations`, `organization_affiliation_status_events`, `organization_affiliation_unit_assignments`, and `organization_affiliation_role_assignments` contain confidential personal/relationship data. They are retained with immutable lifecycle and assignment history, are excluded from public projections, and are accessible only through tenant-aware, permission-checked application paths. Custom role titles are bounded to 160 bytes, output-escaped, and never treated as authorization input.

The `organizations:affiliations:verify` command validates the role catalog and lifecycle integrity. The scheduled maintenance task performs bounded pending-request expiry. Organization and Unit retirement are rejected while an open affiliation or open Unit assignment remains.

## Verification minimum

Release evidence must include schema install/migrate/seed/verify; catalog and route verification; unit, architecture, frontend and real MySQL tests; a tenant-isolation negative assertion; database-enforced open-request uniqueness; immutable-history mutation rejection; and two-worker optimistic-concurrency evidence. The P2 freeze verifier must remain clean after the B04 controlled extension is committed.

## P3-B05 canonicalization participant extension

Organization affiliations remain tenant-owned and retain their existing consent and authorization semantics. They do
not authorize a profile claim or a duplicate resolution. During a successful, consent-gated Person canonicalization,
the registered Organization-affiliation participant may reassign only the duplicate Person reference inside the single
controlled canonicalization transaction. It first reports an Organization-affiliation conflict where reassignment
would violate the bounded contract; it never creates an affiliation, grants a role, changes Workspace membership, or
commits independently. A rejected or blocked canonicalization leaves affiliation rows unchanged.
