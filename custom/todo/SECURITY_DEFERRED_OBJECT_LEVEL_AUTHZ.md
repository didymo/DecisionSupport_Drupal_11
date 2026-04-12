# Deferred Security Investigation: Object-Level Authorization (Drupal)

**Status:** Deferred — do not implement without completing the investigation
**Identified:** 2026-04-10 (code review)
**Documented:** 2026-04-11
**Severity:** High

Coordination-level document (cross-project context and decision log):
`/extra/Agent_Coordination/decision-support-coordination/SECURITY_DEFERRED_OBJECT_LEVEL_AUTHZ.md`

Angular-side document:
`/extra/WebstormProjects/DecisionSupport_angular/SECURITY_DEFERRED_OBJECT_LEVEL_AUTHZ.md`

---

## The gap

Every REST resource performs a broad `hasPermission()` check, then delegates to the service
layer. The service layer loads entities by ID with no owner or relationship check. Any
authenticated `dsd_editor` can reach any entity of the relevant type if they know the ID.

This applies to all read, update, archive, and file-attachment operations across the three
custom modules.

---

## Affected files — exact locations

### `decision_support` module

**REST resources** — permission-only gate, no owner check after:

| File | Method | Route | Permission checked |
|---|---|---|---|
| `decision_support/src/Plugin/rest/resource/GetDecisionSupportResource.php:77` | GET | `/rest/support/get/{decisionSupportId}` | `view decision_support_entity` |
| `decision_support/src/Plugin/rest/resource/GetDecisionSupportListResource.php:80` | GET | `/rest/support/list` | `view decision_support_entity` |
| `decision_support/src/Plugin/rest/resource/GetDecisionSupportReportResource.php:77` | GET | `/rest/support/report/{decisionSupportId}` | `view decision_support_entity` |
| `decision_support/src/Plugin/rest/resource/GetDecisionSupportReportListResource.php:80` | GET | `/rest/support/reportlist` | `view decision_support_entity` |
| `decision_support/src/Plugin/rest/resource/PostDecisionSupportResource.php:83` | POST | `/rest/support/post` | `create decision_support_entity` |
| `decision_support/src/Plugin/rest/resource/PatchDecisionSupportResource.php:90` | PATCH | `/rest/support/update/{decisionSupportId}` | `edit decision_support_entity` |
| `decision_support/src/Plugin/rest/resource/ArchiveDecisionSupportResource.php:87` | DELETE | `/rest/support/archive/{decisionSupportId}` | `delete decision_support_entity` |

**Service layer** — bare entity load, no owner check:

| File | Method | Line | Issue |
|---|---|---|---|
| `decision_support/src/Services/DecisionSupport/DecisionSupportService.php` | `getDecisionSupport()` | 172 | `->load($decisionSupportId)` — no owner check |
| `decision_support/src/Services/DecisionSupport/DecisionSupportService.php` | `updateDecisionSupport()` | 228 | `->load($decisionSupportId)` — no owner check |
| `decision_support/src/Services/DecisionSupport/DecisionSupportService.php` | `archiveDecisionSupport()` | (archive method) | `->load($decisionSupportId)` — no owner check |

**Access control handler** — permission-only, no entity-to-account relationship:

- `decision_support/src/DecisionSupportAccessControlHandler.php:29`
  The `match` block maps operations to permissions only. No `$entity->getOwnerId()`
  comparison. No `cachePerUser()` on results.

---

### `process` module

**REST resources:**

| File | Method | Route | Permission checked |
|---|---|---|---|
| `process/src/Plugin/rest/resource/GetProcessResource.php:77` | GET | `/rest/process/get/{processId}` | `view process` |
| `process/src/Plugin/rest/resource/GetProcessListResource.php:80` | GET | `/rest/process/list` | `view process` |
| `process/src/Plugin/rest/resource/PostProcessResource.php` | POST | `/rest/process/post` | `create process` |
| `process/src/Plugin/rest/resource/PatchProcessResource.php` | PATCH | `/rest/process/patch/{processId}` | `edit process` |
| `process/src/Plugin/rest/resource/UpdateProcessResource.php:90` | PATCH | `/rest/process/update/{processId}` | `edit process` |
| `process/src/Plugin/rest/resource/DuplicateProcessResource.php` | POST | `/rest/process/duplicate` | `create process` |
| `process/src/Plugin/rest/resource/DeleteProcessResource.php:87` | PATCH | `/rest/process/delete/{processId}` | `delete process` |

**Service layer:**

| File | Method | Line | Issue |
|---|---|---|---|
| `process/src/Services/ProcessService/ProcessService.php` | `getProcess()` | 77 | `->load($processId)` — no owner check (status check exists but not owner) |
| `process/src/Services/ProcessService/ProcessService.php` | `updateProcess()` | 176 | `->load($processId)` — no owner check |
| `process/src/Services/ProcessService/ProcessService.php` | `deleteProcess()` | (archive method) | `->load($processId)` — no owner check |

**Access control handler:**

- `process/src/ProcessAccessControlHandler.php:29`
  Same pattern as `DecisionSupportAccessControlHandler` — permission match only.

---

### `decision_support_file` module

**REST resources:**

| File | Method | Route | Permission checked |
|---|---|---|---|
| `decision_support_file/src/Plugin/rest/resource/GetDecisionSupportFileResource.php` | GET | `/rest/support/file/get/{decisionSupportId}` | `view decision_support_file` |
| `decision_support_file/src/Plugin/rest/resource/PostDecisionSupportFileResource.php:81` | POST | `/rest/support/file/post` | `create decision_support_file` |
| `decision_support_file/src/Plugin/rest/resource/ArchiveDecisionSupportFileResource.php:79` | PATCH | `/rest/support/file/archive/{fileId}` | `delete decision_support_file` |

**Service layer — specific additional gap:**

`decision_support_file/src/Services/DecisionSupportFile/DecisionSupportFileService.php:95`

`createDecisionSupportFile()` validates file entity access (`$file_entity->access('view', ...)`)
but never loads the `decisionSupportId` entity or checks whether the caller has update
access to that decision support record. A user with `create decision_support_file` can
attach a file to any decision support ID they know, without owning that record.

**Access control handler:**

- `decision_support_file/src/DecisionSupportFileAccessControlHandler.php:29`
  Same permission-only pattern.

---

## What the fix looks like (do not implement until investigation complete)

### Service layer — owner check pattern

```php
// After loading the entity:
if ((int) $entity->getOwnerId() !== (int) $this->currentUser->id()
    && !$this->currentUser->hasPermission('administer decision_support_entity')) {
  throw new AccessDeniedHttpException();
}
```

Apply to: `getDecisionSupport()`, `updateDecisionSupport()`, `archiveDecisionSupport()`,
`getProcess()`, `updateProcess()`, `deleteProcess()`, `deleteDecisionSupportFile()`.

### File attachment — parent record check

```php
// In createDecisionSupportFile(), after validating the file entity:
$decision_support = $this->entityTypeManager
  ->getStorage('decision_support_entity')
  ->load($data['decisionSupportId']);
if (!$decision_support) {
  throw new NotFoundHttpException('Decision support record not found.');
}
if (!$decision_support->access('update', $this->currentUser)) {
  throw new AccessDeniedHttpException('Access denied to the specified decision support record.');
}
```

### Access control handlers — add owner condition

```php
'view' => AccessResult::allowedIf(
    $account->hasPermission('view decision_support_entity')
    && ($entity->getOwnerId() == $account->id()
        || $account->hasPermission('administer decision_support_entity'))
)->cachePerUser()->addCacheableDependency($entity),
```

---

## Before implementing: questions that must be answered

See the coordination document for the full investigation checklist. Critical questions:

1. Are decision support records personal (owner-only) or shared across the organisation?
2. Are process records authored by admins and read by all editors (no owner filter needed
   on reads), or per-user?
3. Is the `uid` field populated correctly on all production entities?
4. Do any admin workflows depend on cross-user access that would be broken by owner checks?

Run the ownership audit SQL from the coordination document against the production DB
before writing any code.

---

## Testing requirement

No fix should be merged without kernel or functional tests covering:

- Owner can read/update/archive their own record
- Non-owner with the same role receives 403
- Admin bypasses owner check
- File attachment to a record the user does not own returns 403

---

## Decision log

| Date | Decision | Reason |
|---|---|---|
| 2026-04-11 | Deferred | Codebase is on production; ownership data and intended access model must be verified first |
