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
            _Html('utils.missing-translations')->class('text-2xl font-semibold'),
            _Toggle('utils.include-ignored-ones')->name('include_ignored_ones', false)->filter(),
        );
    }

    public function headers()
    {
        return [
            _Th('utils.translation-key'),
            _Th('utils.locale')->class('w-12'),
            _Th('utils.hits')->class('w-12 text-right'),
            _Th('utils.package'),
            _Th('utils.file'),
            _Th('utils.ignore')->class('text-right w-6'),
            _Th('utils.fixed')->class('text-right w-6'),
        ];
    }

    public function render($missingTranslation)
    {
        return _TableRow(
            _Html($missingTranslation->translation_key),

            _Html($missingTranslation->locale ?: '-'),

            _Html((string) ($missingTranslation->hit_count ?? 0))->class('text-right'),

            _Html(substr($missingTranslation->package ?? '', -75) ?: '-'),

            $this->fileLink($missingTranslation->file_path),

            _Checkbox()->name('ignored_at')->value($missingTranslation->ignored_at ? 1 : 0)
                ->class('!mb-0 mx-auto')
                ->selfPost('markAsIgnored', ['id' => $missingTranslation->id])->browse(),

            _Checkbox()->name('fixed_at')->value($missingTranslation->fixed_at ? 1 : 0)
                ->class('!mb-0 mx-auto')
                ->selfPost('markAsFixed', ['id' => $missingTranslation->id])->browse(),
        );
    }

    protected function fileLink(?string $filePath)
    {
        if (!$filePath) {
            return _Html('-');
        }

        $scheme = config('kompo-utils.editor-scheme', 'vscode');
        $absolute = base_path($filePath);
        $href = $scheme . '://file/' . str_replace('\\', '/', $absolute);
        $short = strlen($filePath) > 60 ? '…' . substr($filePath, -60) : $filePath;

        return _Link($short)->href($href)->inNewTab()->class('text-info underline');
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