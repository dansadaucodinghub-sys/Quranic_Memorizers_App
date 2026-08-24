# Standards and Regulatory Evidence

| Document control | Value |
| --- | --- |
| Project | Qur’an Memorizer DB |
| Project Code | QMDB |
| Baseline ID | QMDB-BL-001 |
| Batch ID | QMDB-P0-B03 |
| Document Title | Standards and Regulatory Evidence |
| Document Version | 1.0.0 |
| Document Status | Controlled draft |
| Document Owner Role | Security, Privacy, Accessibility and Compliance Governance |
| Last Updated | 2026-08-24 |
| Approval Status | Proposed for governance approval; no open value is approved by this document |
| Related Documents | [B03 NFR index](../requirements/P0-B03-non-functional-requirements-index.md); [Privacy impact screening](../privacy/privacy-impact-screening.md) |

## Purpose

Record primary external sources used to shape requirements and identify where qualified interpretation remains necessary.

## Scope

References are implementation guidance or evidence inputs, not automatic proof of conformance and not legal advice.

## Controlled reference register

| Reference ID | Source | Publisher | Version or Date | Official Source Verified | Date Accessed | Relevant Areas | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| REF-001 | [Application Security Verification Standard](https://owasp.org/www-project-application-security-verification-standard/) | OWASP Foundation | 5.0.0, released 2025-05-30 | Yes | 2026-08-24 | QMDB-NFR-SEC, IAM, TEN, API, MED, CRY; QMDB-CTL-001–QMDB-CTL-014 | Used as web-application security verification reference; exact ASVS requirement mapping is deferred to implementation verification. |
| REF-002 | [Digital Identity Guidelines](https://www.nist.gov/publications/nist-sp-800-63-4-digital-identity-guidelines) | US National Institute of Standards and Technology | NIST SP 800-63-4, 2025-08-01 | Yes | 2026-08-24 | QMDB-NFR-IAM-001; QMDB-NFR-IAM-002 | Risk and usability input; QMDB does not claim a federal assurance level in P0. |
| REF-003 | [Web Content Accessibility Guidelines 2.2](https://www.w3.org/TR/WCAG22/) | World Wide Web Consortium | W3C Recommendation, 2024-12-12 | Yes | 2026-08-24 | QMDB-NFR-ACC, L10 and UXR; accessibility matrix | Level AA is the locked target; conformance still requires manual and automated evidence. |
| REF-004 | [Cybersecurity Framework 2.0](https://www.nist.gov/cyberframework) | US National Institute of Standards and Technology | CSF 2.0, 2024-02-26 | Yes | 2026-08-24 | Governance, identify, protect, detect, respond and recover controls | Used as risk/governance organization, not certification claim. |
| REF-005 | [Secure Software Development Framework](https://csrc.nist.gov/pubs/sp/800/218/final) | US National Institute of Standards and Technology | NIST SP 800-218 v1.1, 2022-02 | Yes | 2026-08-24 | QMDB-NFR-MNT, TST, REL, INF and SUP | Used for secure-development and supply-chain control structure. |
| REF-006 | [MySQL 8.4 Reference Manual — Security](https://dev.mysql.com/doc/refman/8.4/en/security.html) | Oracle | MySQL 8.4 manual, generated 2026-08-20 | Yes | 2026-08-24 | QMDB-NFR-DAT-001; QMDB-CTL-008 | Supports least privilege, encrypted connection, audit visibility, secure client and tested recovery requirements; edition-specific features require implementation review. |
| REF-007 | [Redis Open Source Security](https://redis.io/docs/latest/operate/oss_and_stack/management/security/) | Redis | Current official documentation, version not stated | Yes | 2026-08-24 | QMDB-NFR-SEC-003; QMDB-CTL-009 | Supports private trusted-network placement, ACLs and TLS-capable design; deployment-specific support remains open. |
| REF-008 | [PHP Security Manual](https://www.php.net/manual/en/security.php) | PHP Documentation Group | Current official manual, version not stated | Yes | 2026-08-24 | QMDB-NFR-SEC-002; QMDB-NFR-MNT-001; QMDB-NFR-INF-001 | Used for PHP security and runtime configuration review; user-contributed notes are not treated as authoritative requirements. |
| REF-009 | [Nigeria Data Protection Act 2023](https://www.ndpc.gov.ng/ndp-act-2023/) | Nigeria Data Protection Commission | Act 2023 | Yes | 2026-08-24 | QMDB-NFR-PRI and CHD; privacy screening and retention decisions | Primary statutory source recorded; lawful basis, child, deadline, transfer, retention and notification interpretations require qualified Nigerian review. |

## Verification method

Use the publishing organization’s official page or official publication file; record title, version/date and access date; compare material revisions before release; and retain a review note linking affected requirements. Secondary summaries cannot silently replace a primary source.

## Version-change review

Changes to REF-001 through REF-008 trigger Security/Accessibility/Engineering review of related controls and acceptance tests. Changes or guidance affecting REF-009 trigger qualified Nigerian privacy/compliance review before policy or system behavior changes.

## Qualified interpretation boundary

This register does not determine lawful basis, statutory deadlines, child age/Guardian rules, data residency, cross-border transfer, retention, incident notification or public-authority powers. Those decisions remain open and require qualified professional and accountable organizational approval.

## Unavailable references

No planned primary reference was unavailable during execution. Applicable Nigerian child-protection interpretation beyond the recorded Act was deliberately not asserted because qualified scope and legal review remain open.

