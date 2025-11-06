<?php

namespace Condoedge\Utils\Kompo\Translations;

use Condoedge\Utils\Kompo\Common\WhiteTable;
use Condoedge\Utils\Models\MissingTranslation;

class MissingTranslationsTable extends WhiteTable
{
    public function query()
    {
        return MissingTranslation::query()
            ->when(!request('include_ignored_ones'), fn($q) => $q->whereNull('ignored_at'))
            ->whereNull('fixed_at');
    }

    public function top()
    {
        return _FlexBetween(
            _Html('translate.missing-translations')->class('text-2xl font-semibold'),
            _Toggle('translate.include-ignored-ones')->name('include_ignored_ones', false)->filter(),
        );
    }

    public function headers()
    {
        return [
            _Th('translate.translation-key'),
            _Th('translate.ignore')->class('text-right w-6'),
            _Th('translate.fixed')->class('text-right w-6'),
        ];
    }

    public function render($missingTranslation)
    {
        return _TableRow(
            _Html($missingTranslation->translation_key),

            _Checkbox()->name('ignored_at')->value($missingTranslation->ignored_at ? 1 : 0)
                ->class('!mb-0 mx-auto')
                ->selfPost('markAsIgnored', ['id' => $missingTranslation->id])->browse(),

            _Checkbox()->name('fixed_at')->value($missingTranslation->fixed_at ? 1 : 0)
                ->class('!mb-0 mx-auto')
                ->selfPost('markAsFixed', ['id' => $missingTranslation->id])->browse(),
        );
    }

    public function markAsIgnored($id)
    {
        $missingTranslation = MissingTranslation::findOrFail($id);
        $missingTranslation->ignored_at = now();
        $missingTranslation->save();
    }

    public function markAsFixed($id)
    {
        $missingTranslation = MissingTranslation::findOrFail($id);
        $missingTranslation->fixed_at = now();
        $missingTranslation->save();
    }
}