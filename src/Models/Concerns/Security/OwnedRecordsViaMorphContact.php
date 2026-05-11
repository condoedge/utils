<?php

namespace Condoedge\Utils\Models\Concerns\Security;

/**
 * Implements `HasOwnedRecords` for morph-attached contact rows
 * (Phone, Email, Address, File). The user owns a row when the row's morph
 * parent is in the user's owned-records set for that parent class.
 *
 * Consumers override `morphContactColumnName()` to point at their morph
 * column prefix (`phonable`, `emailable`, etc.).
 *
 * Cross-package boundary: the OwnedRecordsResolver lives in kompo-auth.
 * We resolve it through the container by FQN string so utils stays free of
 * a compile-time dependency on auth — if auth isn't installed, the method
 * returns `[]` (no ownership, safe default).
 */
trait OwnedRecordsViaMorphContact
{
    abstract protected function morphContactColumnName(): string;

    public function ownedRecordIdsForUser(int $userId): array
    {
        $morphables = config('kompo-utils.morphables-contact-associated-to-user', []);
        if (empty($morphables)) {
            return [];
        }

        $resolverClass = \Kompo\Auth\Teams\Security\Contracts\OwnedRecordsResolverInterface::class;
        if (!interface_exists($resolverClass)) {
            return [];
        }
        $resolver = app($resolverClass);

        $column = $this->morphContactColumnName();
        $query = static::query();
        $matched = false;

        $query->where(function ($outer) use ($morphables, $resolver, $userId, $column, &$matched) {
            foreach ($morphables as $morphableClass) {
                if (!class_exists($morphableClass)) {
                    continue;
                }

                $ownedIds = $resolver->forUser($userId, $morphableClass);
                if (empty($ownedIds)) {
                    continue;
                }

                $matched = true;
                $outer->orWhere(function ($q) use ($morphableClass, $ownedIds, $column) {
                    $q->where($column . '_type', (new $morphableClass)->getMorphClass())
                        ->whereIn($column . '_id', $ownedIds);
                });
            }
        });

        if (!$matched) {
            return [];
        }

        return $query->pluck($this->getKeyName())->all();
    }
}
