# Chattanooga CMS Admin v2 Engineering Harness

This harness is the active verification environment for the universal Chattanooga CMS Admin replacement on branch `work/chattanooga-universal-admin-v2`.

The prior `work/chattanooga-universal-admin` branch and its lab harness are historical evidence only. They are not modified by v2 engineering.

## Harness rules

- The production plugin identity remains `Chattanooga CMS Admin`; this harness does not create a third production plugin.
- The replacement source is the single candidate under `workbench/labs/chattanooga-universal-admin/candidate/` on this branch.
- Harness fixtures must be unrelated capability providers and must never be named or special-cased in candidate source.
- Provider functionality is admissible only through public WordPress Abilities API or registered REST contracts.
- Unsupported/private functionality must fail closed.
- Platform administration implemented by Chattanooga CMS Admin must use bounded WordPress core interfaces and preserve permissions, verification, rollback, and self-protection.
- No test may weaken a production acceptance rule merely to obtain a passing run.

## Baseline acceptance

1. Candidate has exactly one plugin entrypoint and the `Chattanooga CMS Admin` identity.
2. A previously unknown public Ability provider is discovered and executed through a generated facade.
3. A previously unknown REST-only provider is discovered and executed through a generated facade.
4. A provider exposing neither public contract is not represented as administrable.
5. Anonymous access is denied.
6. Candidate source contains none of the fixture provider identities.

Further platform lifecycle tests are added only after this clean baseline passes.