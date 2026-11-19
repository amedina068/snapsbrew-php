<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 409: the instance refused the write. `errorCode()` tells you why.
 *
 * - `idempotency_key_in_progress`: another request with the same key is still
 *   running. A repeat with the same key is safe, and the SDK retries it.
 * - `idempotency_key_belongs_to_snap`: a single snap owns the key, so a batch
 *   cannot use it. Send a new key.
 * - `idempotency_key_belongs_to_batch`: a batch owns the key, so a single snap
 *   cannot use it. Send a new key.
 * - `legacy_edit_rejected_for_v2`: the template uses the v2 editor document,
 *   so `templates()->update()` cannot change it. Use `documents()`.
 * - `editor_document_conflict`: the revision of the editor document moved, and
 *   the instance saved nothing. `currentRevision()` and `currentDocument()`
 *   give the stored values. Apply your change to them, then write again.
 */
final class ConflictException extends ApiException
{
    public const IDEMPOTENCY_KEY_IN_PROGRESS = 'idempotency_key_in_progress';

    public const IDEMPOTENCY_KEY_BELONGS_TO_SNAP = 'idempotency_key_belongs_to_snap';

    public const IDEMPOTENCY_KEY_BELONGS_TO_BATCH = 'idempotency_key_belongs_to_batch';

    public const LEGACY_EDIT_REJECTED_FOR_V2 = 'legacy_edit_rejected_for_v2';

    public const EDITOR_DOCUMENT_CONFLICT = 'editor_document_conflict';

    /**
     * True when another request with the same idempotency key is still
     * running. A repeat with the same key is safe.
     */
    public function inProgress(): bool
    {
        return $this->errorCode() === self::IDEMPOTENCY_KEY_IN_PROGRESS;
    }

    /**
     * The stored revision of the editor document, after an
     * `editor_document_conflict`. Null for the other conflicts.
     */
    public function currentRevision(): ?int
    {
        $revision = $this->body()['current_revision'] ?? null;

        return is_int($revision) ? $revision : null;
    }

    /**
     * The stored editor document, after an `editor_document_conflict`. Null
     * for the other conflicts, and for a template with no draft.
     *
     * @return array<array-key, mixed>|null
     */
    public function currentDocument(): ?array
    {
        $document = $this->body()['current_document'] ?? null;

        return is_array($document) ? $document : null;
    }
}
