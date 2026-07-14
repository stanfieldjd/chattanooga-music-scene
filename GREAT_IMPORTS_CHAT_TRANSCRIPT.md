[object Object]

## User

Under saved reoccurring sources need to have a forced locations drop down box when enabled 

## Assistant

Chess-game plan before touching source:

- proposed move: update and sync `GREAT_IMPORTS_CHAT_TRANSCRIPT.md` first with your exact instruction and this plan, then inspect the current GitHub source for saved recurring sources and Events Manager location handling before patching.
- why: you explicitly requested a forced location dropdown under Saved Recurring Sources, and this affects saved recurring source behavior, not just display.
- exact changes: add a saved-recurring setting that can be enabled per source, render an Events Manager location dropdown only/when that force-location setting is enabled, store the selected EM location ID with the saved source, and apply it when that saved source creates or updates candidates if the existing importer has a safe location-selection path.
- what will not change: no ZIP, no parser rewrite, no ticket handling changes, no report/danger-zone changes, no unrelated candidate table movement, no Events Manager event creation from recurring runs.
- risks: “forced location” could mean force the candidate’s matching dropdown only, or force the final Events Manager import location. I will inspect the existing source before deciding the safest exact hook point, and I will not claim it affects imports unless the code proves it.
- success checks: transcript commit first; GitHub source shows stored forced-location fields, UI dropdown under Saved Recurring Sources, nonce-protected save handling, version/readme bump, and remote verification after publish.
- rollback point: current Great Imports `0.2.72` commit `f7fd40c898ea84832b99c1f3b9f21fca45a7b50d`. 
