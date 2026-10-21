<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Condoedge\Utils\Models\Contracts\HasLabelRow;

/**
 * Renders a has-many relation as a read-only table. Related models implement HasLabelRow.
 */
class RelationRowsLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        $rows = collect($value)->map(fn (HasLabelRow $item) => $item->labelRow())->filter()->values();

        if ($rows->isEmpty()) return null;

        $headers = collect(array_keys($rows->first()))
            ->map(fn ($header) => '<th class="text-left font-semibold text-gray-600 py-1 pr-4">' . e(__($header)) . '</th>')
            ->implode('');

        $body = $rows->map(fn ($row) => '<tr class="border-t border-gray-200">' . collect($row)
            ->map(fn ($cell) => '<td class="py-1 pr-4 align-top whitespace-pre-line">' . e($cell) . '</td>')
            ->implode('') . '</tr>')->implode('');

        return _Html('<table class="w-full"><thead><tr>' . $headers . '</tr></thead><tbody>' . $body . '</tbody></table>')
            ->class('overflow-x-auto w-full');
    }
}
