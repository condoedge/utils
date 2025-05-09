<?php

namespace Condoedge\Utils\Mail;

use Illuminate\Mail\Mailable;

class ExportReady extends Mailable
{
    public $url;
    public $filename;

    public function __construct($url, $filename)
    {
        $this->url = $url;
        $this->filename = $filename;
    }

    public function build()
    {
        return $this->markdown('kompo-utils::emails.export-ready')
            ->subject(__('translate.utils.export-ready'))
            ->with([
                'downloadUrl' => $this->url,
                'filename' => $this->filename,
            ]);
    }
}