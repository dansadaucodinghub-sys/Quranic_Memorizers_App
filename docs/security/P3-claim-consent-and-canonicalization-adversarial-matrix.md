# P3 Claim, Consent and Canonicalization Adversarial Matrix

| Abuse case | Control | Evidence |
| --- | --- | --- |
| Selector or secret alone used to claim | exact Account binding and HMAC comparison | identity-resolution architecture tests |
| Wrong pairing code enumeration | generic unavailable response and dummy compare | identity-resolution architecture tests |
| Unauthorized/minor claimant | active Account, adult eligibility and SELF-link checks | B05/MySQL integration tests |
| Consent bypass or reviewer conflict | explicit authority requirements and duplicate consent preflight | identity-resolution integration tests |
| Alias cycle/chain or Person deletion | source retirement, immutable alias constraints and no delete path | constraint integration tests |
| Partial canonicalization | caller-owned participants inside one transaction | identity-resolution architecture and worker tests |
