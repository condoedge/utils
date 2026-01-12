<?php

namespace Condoedge\Utils\Models\Files;

use Condoedge\Utils\Kompo\Files\AudioPreview;
use Condoedge\Utils\Kompo\Files\ImagePreview;
use Condoedge\Utils\Kompo\Files\PdfPreview;
use Condoedge\Utils\Kompo\Files\RawDocumentPreview;
use Condoedge\Utils\Kompo\Files\VideoPreview;

enum FileTypeEnum: int
{
    use \Condoedge\Utils\Models\Traits\EnumKompo;
    
    case IMAGE = 1;
    case PDF = 2;
    case COMPRESSED = 3;
    case DOCUMENT = 4;
    case SPREADSHEET = 5;
    case AUDIO = 6;
    case VIDEO = 7;

    case RAW_DOCUMENT = 8;

    case UNKNOWN = 10;

    public function label()
    {
        return match ($this) {
            self::IMAGE => __('file-type-image'),
            self::PDF => __('file-type-pdf'),
            self::COMPRESSED => __('file-type-compressed'),
            self::DOCUMENT => __('file-type-document'),
            self::SPREADSHEET => __('file-type-spreadsheet'),
            self::AUDIO => __('file-type-audio'),
            self::VIDEO => __('file-type-video'),
            self::RAW_DOCUMENT => __('translate.file-type-raw-document'),
            default => __('file-type-unknown'),
        };
    }

    public function mimeTypes()
    {
        return match ($this) {
            self::IMAGE => imageMimeTypes(),
            self::PDF => pdfMimeTypes(),
            self::COMPRESSED => ['application/x-rar-compressed', 'application/zip', 'application/x-gzip', 'application/gzip', 'application/vnd.rar', 'application/x-7z-compressed'],
            self::DOCUMENT => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            self::SPREADSHEET => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            self::AUDIO => ['audio/basic', 'audio/aiff', 'audio/mpeg', 'audio/midi', 'audio/wave', 'audio/ogg'],
            self::VIDEO => videoMimeTypes(),
            self::RAW_DOCUMENT => ['text/plain', 'application/vnd.oasis.opendocument.text', 'application/rtf'],
            default => [],
        };
    }

    public function icon()
    {
        return match ($this) {
            self::IMAGE => 'coolecto-image',
            self::PDF => 'coolecto-pdf',
            self::COMPRESSED => 'coolecto-archive',
            self::DOCUMENT => 'coolecto-word',
            self::SPREADSHEET => 'coolecto-excel',
            self::AUDIO => 'coolecto-audio',
            self::VIDEO => 'coolecto-video',
            self::RAW_DOCUMENT => 'coolecto-word',
            default => 'coolecto-archive',
        };
    }

    public function isPreviewable()
    {
        return in_array($this, [self::IMAGE, self::PDF, self::AUDIO, self::VIDEO, self::RAW_DOCUMENT]);
    }

    public function getPreviewComponent($model)
    {
        $modelParams = [
            'type' => $model->getMorphClass(),
            'id' => $model->id,
        ];
        
        return match ($this) {
            self::IMAGE => new ImagePreview(null, $modelParams),
            self::PDF => new PdfPreview(null, $modelParams),
            self::AUDIO => new AudioPreview(null, $modelParams),
            self::VIDEO => new VideoPreview(null, $modelParams),
            self::RAW_DOCUMENT => new RawDocumentPreview(null, $modelParams),
            default => null,
        };
    }

    public function getPreviewButton($komponent, $model)
    {
        return match ($this) {
            self::IMAGE => $komponent->get('image.preview', ['id' => $model->id, 'type' => $model->getMorphClass()])->inModal(),
            self::PDF => $komponent->href(fileRoute($model->getMorphClass(), $model->id))->inNewTab(),
            self::AUDIO => $komponent->get('audio.preview', ['id' => $model->id, 'type' => $model->getMorphClass()])->inModal(),
            self::VIDEO => $komponent->get('video.preview', ['id' => $model->id, 'type' => $model->getMorphClass()])->inModal(),
            self::RAW_DOCUMENT => $komponent->get('raw_document.preview', ['id' => $model->id, 'type' => $model->getMorphClass()])->inModal(),
            default => null,
        };
    }

    public function componentFromColumn($type, $id, $column, $index = null)
    {
        $route = route('preview-files', ['type' => $type, 'id' => $id, 'column' => $column, 'index' => $index]);

        return match ($this) {
            self::IMAGE => _Img($route)->class('w-full h-auto bg-cover bg-center'),
            self::PDF => _Html('<embed src="' . $route . '" frameborder="0" width="100%" height="100%">'),
            self::AUDIO => _Audio($route),
            self::VIDEO => _Video($route),
            self::RAW_DOCUMENT => _Html('<embed src="' . $route . '" frameborder="0" width="100%" height="100%">'),
            default => null,
        };
    }

    public static function fromMimeType($mimeType)
    {
        foreach (self::cases() as $case) {
            if (in_array($mimeType, $case->mimeTypes())) {
                return $case;
            }
        }

        return self::UNKNOWN;
    }
}