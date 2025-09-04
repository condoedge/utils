<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Illuminate\Support\Facades\Storage;

class FileLabelCast extends AbstractLabelCast
{
    public function convert($value, $column)
    {
        if (!$value) return null;

        return _Link($value['name'] ?? 'File')->get('preview-files-modal', [
            'id' => $this->model->getKey(),
            'mime' => str_replace('/', '-', $value['mime_type'] ?? ''),
            'type' => $this->model->getMorphClass(),
            'column' => $column,
            'index' => (($this->options['index'] + 1) ?? null),
        ])->inModal();
    }
}