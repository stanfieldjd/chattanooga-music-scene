# Task: Venue / Location Data Quality

Status: LIVE_DEFECT_INVENTORY_VERIFIED — FIRST-PARTY ADDRESS RESEARCH PARTIAL / COORDINATES AND LIVE WRITE AUTHORIZATION PENDING

## Objective

Repair materially incorrect or incomplete Events Manager venue/location metadata on Chattanooga Music Scene using authoritative location evidence and the bounded Chattanooga CMS Admin exact-state update path.

## Target set

Current inspection targets are live Events Manager location records. The first exact suspect set is:

- `333` / post `3667` — 1885 Grill (Ooltewah)
- `307` / post `3558` — Artistic Civic Theatre
- `277` / post `3505` — Bessie Smith Cultural Center
- `84` / post `1052` — Ross’s Landing
- `437` / post `8331` — Baby Hughy’s Pizza and Burgers Rock Spring
- `444` / post `8345` — Farm to Fork

Control record intentionally excluded from automatic correction:

- `331` / post `3658` — Multiple Chattanooga Venues

## Exclusion set

- No live location mutation without separate target-specific authorization.
- No coordinate, address, postcode, region, state, or name value may be guessed or derived from a generic geocoder without authoritative evidence.
- Do not normalize or geocode `Multiple Chattanooga Venues` merely because it has `0,0`; its current content explicitly defines it as a citywide/multi-venue logical location rather than one physical point.
- No location deletion, event mutation, taxonomy mutation, plugin/theme/source change, member change, navigation change, or unrelated setting change.
- Do not replace known-good existing coordinates unless independent evidence proves they are wrong.

## Evidence

Fresh live `chattanooga-cms-admin__list-locations` inspection on 2026-09-08 exposed several distinct defect classes. Exact `get-location` reads then reconfirmed the highest-confidence records and their state tokens.

### Definite impossible/default coordinates

These records have physical addresses in Tennessee or Georgia but coordinates exactly `47.4, 1.6`, which are materially inconsistent with the stated locality and therefore cannot represent the listed venue:

1. Location `333` — 1885 Grill (Ooltewah)
   - address: `9469 Bradmore Lane`
   - town/state/postcode: `Ooltewah`, `tn`, `37363`
   - current coordinates: `47.4, 1.6`
   - current state token: `0c6a06c9d68920021fffe847ac311c79db209e50e182e2b437eb29df2ee20807`
   - separate normalization issue: state is lowercase `tn` while comparable records use uppercase postal abbreviations.

2. Location `307` — Artistic Civic Theatre
   - address: `905 Gaston St`
   - town/state/postcode: `Dalton`, `GA`, `30720`
   - current coordinates: `47.4, 1.6`
   - current state token: `428a96a970fe440c1d219d58302f04812cbbb6c4e4e77820840dbe08a69e17f6`

3. Location `277` — Bessie Smith Cultural Center
   - address: `200 E MLK Blvd`
   - town/state/postcode: `Chattanooga`, `TN`, `37403`
   - current coordinates: `47.4, 1.6`
   - current state token: `45b4335a8ddc27cb9ad8f4176acd1a614f95ce0baa7867ca15e5f3ab0bc3c535`

The classification “wrong coordinates” is execution-verified from internal record inconsistency. The correct replacement coordinates are not yet established and remain `UNKNOWN` pending authoritative geospatial research.

### Definite field-semantic contamination

Location `84` — Ross’s Landing:

- address: `201 Riverfront Parkway`, Chattanooga, TN `37402`
- existing coordinates: `35.05608, -85.314753`
- `region` currently contains `https://www.riverfrontnights.com/`
- current state token: `baab3eea7f9e11513d7fd37e2e026f622835280ddf729ea8db30b5c1fb9be24e`

A URL in the geographic `region` field is a type/semantic mismatch. The correct region value, including whether it should be empty, is not yet established and must be determined from the Events Manager field contract plus authoritative location evidence before any write.

### Definite incomplete postal metadata

1. Location `437` — Baby Hughy’s Pizza and Burgers Rock Spring
   - address: `8047 N. Hwy 27`, Rock Spring, GA
   - postcode: empty
   - coordinates: `0,0`
   - current state token: `936b68adf640871d2d9e54c5a7fa316558735f96ede48c8793339c6fde7cd9c5`

2. Location `444` — Farm to Fork
   - address: `120 General Lee Street`, Ringgold, GA
   - postcode: empty
   - coordinates: `0,0`
   - current state token: `5e256531bcf5d06cf36a53f9310205e438b5e07145900976fb896d2a9757ce79`

The missing postcode/zero-coordinate state is verified.

### Zero-coordinate class requiring discrimination

Many other published physical venue records currently store `0,0` despite having street/locality fields. This indicates missing geospatial metadata but does not by itself establish the correct replacement point. These records must be researched individually or in evidence-backed batches before mutation.

Location `331` — Multiple Chattanooga Venues — is an important control case:

- content explicitly says it is for citywide or multi-venue events rather than one single venue;
- address is simply `Chattanooga`;
- coordinates are `0,0`;
- state token: `d376111f52d89e0bbc4c5552bef9dc24b89a1d379580b07cb414930aecd6d402`.

Its `0,0` state must not be classified as a physical-geocode defect without additional design evidence. It is excluded from automatic geocoding/correction.

## Research state

### Library of Congress precedence examination

Research question: establish current 2026 operational street address, postcode, administrative location metadata, and physical coordinates for the six definite suspect venues.

Direct Library of Congress systems were examined first on 2026-09-08. The LOC homepage and LC Catalog scope were inspected; the catalog describes holdings centered on books, serials, manuscripts, maps, music, recordings, images, and electronic resources. The direct `loc.gov/search/` endpoint returned HTTP 403 in the retrieval environment and the current `search.catalog.loc.gov` interface required client-side JavaScript. For the exact proposition being researched — current operational venue address/postcode/coordinate metadata — the result is recorded as `LOC_NOT_APPLICABLE`: historical or cultural LOC holdings may intersect some venue identities, but they are not competent current operational-location sources for the fields being repaired. General web discovery was used only after this examination.

### First-party / governmental address evidence

The following current evidence was then inspected directly from venue/operator or government pages:

1. 1885 Grill (Ooltewah)
   - First-party 1885 Grill contact page states `9469 Bradmore Lane Suite 101, Ooltewah, TN 37363`.
   - This independently confirms the current town/postcode, establishes uppercase `TN`, and shows that the live location address omits `Suite 101`.
   - Evidence-supported non-coordinate candidate fields: address `9469 Bradmore Lane Suite 101`; state `TN`; postcode remains `37363`.
   - Replacement coordinates remain unresolved.

2. Artistic Civic Theatre
   - First-party Artistic Civic Theatre contact page states the theater address is `907 Gaston St., Dalton GA 30720`.
   - The live record stores `905 Gaston St`; this is now a separately verified street-number defect, not merely a coordinate defect.
   - Evidence-supported non-coordinate candidate field: address `907 Gaston St`.
   - Replacement coordinates remain unresolved.

3. Bessie Smith Cultural Center
   - First-party Bessie Smith Cultural Center visitor page states it is located at `200 East M.L. King Boulevard` in Chattanooga.
   - The live `200 E MLK Blvd` is semantically consistent with that first-party address; no street-address repair is currently justified from this evidence.
   - The incorrect `47.4,1.6` coordinates remain the outstanding defect.

4. Ross’s Landing
   - City of Chattanooga Parks currently lists `101 Riverfront Pkwy`.
   - The U.S. National Park Service currently lists `201 Riverfront Pkwy, Chattanooga, Tennessee 37402` and identifies the site as managed by the City of Chattanooga.
   - This is a material authoritative-source conflict. The live record currently uses `201 Riverfront Parkway`, matching NPS, so no address change is justified while the conflict remains unresolved.
   - The `region` URL contamination remains definite; the correct replacement region value remains unresolved.

5. Baby Hughy’s Pizza and Burgers Rock Spring
   - First-party Baby Hughy’s site lists the Rock Spring location at `8047 US-27, Rock Spring, GA 30739`.
   - Evidence-supported postcode candidate: `30739`.
   - The live street text `8047 N. Hwy 27` differs from the operator’s `8047 US-27`; the address-format/canonicalization question remains separate from the missing postcode and should not be changed merely for stylistic consistency.
   - Replacement coordinates remain unresolved.

6. Farm to Fork
   - First-party Farm to Fork site lists `120 General Lee Street, Ringgold, GA 30736`.
   - Evidence-supported postcode candidate: `30736`.
   - Current street/town/state already match that source.
   - Replacement coordinates remain unresolved.

### Current evidence decision

The research has established several exact non-coordinate corrections, but it has not established authoritative replacement coordinates for the physical venues with invalid/missing geospatial values. Ross’s Landing also has an unresolved 101-vs-201 Riverfront Parkway source conflict. Those unresolved values remain `UNKNOWN` and cannot support a live write.

## Planned mutation set

None under the current authorization state.

If later authorized after all values for a specific record are established, each record must be handled as its own exact-state transaction:

1. refresh `get-location` immediately before the write;
2. verify the identity and state token still match;
3. change only evidence-supported fields;
4. preserve content, publication state, unrelated address fields, and all unrelated locations;
5. execute `chattanooga-cms-admin__update-location` with the exact current state token;
6. verify readback;
7. rely on the candidate rollback path if verification fails.

## Risks

- Incorrect coordinates can misroute visitors and corrupt map/distance behavior.
- Blindly replacing all `0,0` values would damage intentionally nonphysical logical locations such as `Multiple Chattanooga Venues`.
- Address or postcode sources may disagree or may reflect mailing versus physical venue addresses.
- Ross’s Landing currently demonstrates an actual authoritative-source address conflict; choosing one source without resolving the discrepancy would be assumptive.
- Venue names/ownership may have changed; a current address must be verified against the exact current venue identity.
- Batch writes would increase blast radius and weaken per-record attribution; use exact independent transactions.

## Rollback point

No live venue/location mutation has been performed. Current production reads are the inspection checkpoint. Chattanooga CMS Admin’s tested location update contract supplies exact-state conflict checking, readback, and rollback on verification failure for any later authorized update.

## Acceptance tests

Inspection/research phase:

- Suspect records are identified from current live evidence.
- Exact `get-location` reads confirm the highest-confidence defect set.
- Intentional logical/nonphysical locations are separated from physical geocode defects.
- LOC precedence examination is recorded before general web discovery.
- First-party or governmental address/postcode evidence is captured for the definite suspect set.
- Material source conflicts remain explicit rather than silently resolved.
- No live location write occurs.

Future repair phase for each authorized record:

- Exact replacement values are supported by authoritative evidence.
- Fresh identity/state token matches the intended record.
- Only intended fields change.
- Post-write readback exactly matches the authorized target values.
- Event relationships and unrelated location records remain unchanged.
- Failed verification restores the previous state.

## Recalculated next position

Continue read-only geospatial research for 1885 Grill (Ooltewah), Artistic Civic Theatre, Bessie Smith Cultural Center, Baby Hughy’s Rock Spring, and Farm to Fork, and resolve the Ross’s Landing address/region evidence conflict if a source competent to do so is located. Do not perform `update-location` until the exact replacement values for a specific target and target-specific live-write authorization both exist.