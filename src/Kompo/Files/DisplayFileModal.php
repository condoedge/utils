<?php

namespace Condoedge\Utils\Kompo\Files;

use Condoedge\Utils\Kompo\Common\Modal;
use Condoedge\Utils\Models\Files\FileTypeEnum;

class DisplayFileModal extends Modal
{
    public $_Title = 'utils.file-preview';

    public $noHeaderButtons = true;

    protected $mime;
    protected $type;
    protected $modelId;
    protected $column;
    protected $index;

    public function created()
    {
        $this->mime = $this->prop('mime');
        $this->type = $this->prop('type');
        $this->modelId = $this->prop('id');
        $this->column = $this->prop('column');
        $this->index = $this->prop('index') ?? 0;
    }

    public function body()
    {
        $mime = str_replace('-', '/', $this->mime);

        return _Rows(
            FileTypeEnum::fromMimeType($mime)?->componentFromColumn($this->type, $this->modelId, $this->column, $this->index - 1)
             ?? _Html('utils.no-preview-available'),
        )->style('overflow-y: auto;')->class('px-8 py-6');
    }
}