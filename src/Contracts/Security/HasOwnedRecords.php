<?php

namespace Condoedge\Utils\Contracts\Security;

/**
 * Model-side contract for "this record can be owned by a user".
 *
 * Lives in utils so models that don't depend on kompo-auth (e.g. utils'
 * Phone/Email/Address/File) can implement it. The kompo-auth subclass
 * `Kompo\Auth\Contracts\Security\HasOwnedRecords` extends this one for
 * back-compat with auth-side consumers (resolver, registry, etc.).
 *
 * Implementations MUST be safe to call from inside a bypass context — the
 * resolver toggles bypass around them to avoid recursion.
 */
interface HasOwnedRecords
{
    /**
     * @return array<int|string> Primary key values of records owned by $userId.
     */
    public function ownedRecordIdsForUser(int $userId): array;
}
