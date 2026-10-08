<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Modal;
use Condoedge\Utils\Models\Files\FileTypeEnum;
use Illuminate\Support\Str;

class DisplayFileModal extends Modal
{
    public $_Title = 'utils.file-preview';

    public $noHeaderButtons = false;

    protected $mime;
    protected $type;
    protected $modelId;
    protected $column;
    protected $index;
    protected $fileTypeEnum;

    public function created()
    {
        $this->mime = $this->prop('mime');
        $this->type = $this->prop('type');
        $this->modelId = $this->prop('id');
        $this->column = $this->prop('column');
        $this->index = $this->prop('index') ?? 0;

        // Only the type/subtype separator was encoded, subtypes keep their own dashes.
        $this->fileTypeEnum = FileTypeEnum::fromMimeType(Str::replaceFirst('-', '/', $this->mime));

        // A Word page is wider than the default modal and would be shrunk to fit it.
        if ($this->fileTypeEnum === FileTypeEnum::DOCUMENT) {
            $this->removeClass('max-w-xl');
        }
    }

    public function body()
    {
        return _Rows(
            $this->fileTypeEnum->componentFromColumn($this->type, $this->modelId, $this->column, $this->index - 1),
        )->style('overflow-y: auto;')->class('px-8 py-6');
    }

    public function headerButtons()
    {
        return _LinkButton('files.download')->href('download-files', ['type' => $this->type, 'id' => $this->modelId, 'column' => $this->column, 'index' => $this->index - 1])
            ->attr(['download' => 'download']);
    }
}