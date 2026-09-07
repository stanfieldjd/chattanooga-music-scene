# Workbench Protocol

## 1. Intake

Before material work:

1. Read the exact governing operating rules.
2. Read `workbench/STATUS.md`, `workbench/TASK_QUEUE.md`, and `workbench/state/current.json` from `workbench/mars`.
3. Inspect the current target branch, target files, and relevant live state.
4. Resolve the exact objective, target set, exclusions, authorization class, and acceptance state.

## 2. Task record

Create or update one task record under `workbench/tasks/` using `workbench/templates/task.md`.

The task record must contain:

- objective;
- target set;
- exclusion set;
- evidence;
- planned mutation set;
- risks;
- rollback point;
- acceptance tests;
- source branch and observed commit;
- production state.

## 3. Source branch

- Start feature work from the verified intended base, normally the current `main` head unless the task explicitly continues another branch.
- Do not use `workbench/mars` as a production source branch.
- Preserve unrelated changes.
- Do not rewrite a target file from memory when current source can be fetched.

Recommended branch form: `work/<task-id>` or the existing task-specific branch when continuation is required.

## 4. Mutation

Use the selected task branch for source changes. After every material source mutation:

1. fetch the written state;
2. run applicable CI/static validation;
3. inspect failures before retrying;
4. update the task record if the actual position materially differs from the planned position.

## 5. Validation

Source validation may include:

- syntax/lint checks;
- schema validation;
- packaging validation;
- unit/integration tests;
- generated artifact inspection;
- diff review;
- compatibility checks.

A passing source test does not establish live deployment.

## 6. Promotion

Promotion to `main` or production is a separate action from source creation.

Before promotion:

1. confirm the exact commit to promote;
2. confirm rollback remains available;
3. confirm all source acceptance tests pass;
4. confirm deployment authorization exists for the target environment.

## 7. Live verification

After deployment, independently verify the actual Chattanooga Music Scene state. For a WordPress plugin this includes, as applicable:

- installed version;
- activation state;
- registered abilities/endpoints;
- health check;
- backup creation and checksum verification;
- ability execution against non-destructive test targets;
- rollback behavior before high-risk maintenance operations.

## 8. Workbench commit state

After a material result, update:

- `STATUS.md`;
- `state/current.json`;
- the relevant task record;
- `JOURNAL.md`.

The workbench state must identify unresolved failures or blockers rather than collapsing them into a progress statement.
