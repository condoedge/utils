<?php

namespace Condoedge\Utils\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendExportViaEmail implements ShouldQueue
{
    use Queueable;

    protected $exportableInstance;
    protected $email;
    protected $filename;

    /**
     * Create a new job instance.
     */
    public function __construct($exportableInstance, $email, $filename = null)
    {
        $this->exportableInstance = $exportableInstance;
        $this->email = $email;
        $this->filename = $filename ?? 'export-' . uniqid() . '.xlsx';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $exportableInstance = $this->exportableInstance;
        $component = getPrivateProperty($exportableInstance, 'component');

        if ($component && method_exists($component, 'bootForAction')) {
            $component->bootForAction();
        }

        \Maatwebsite\Excel\Facades\Excel::store(
            $exportableInstance,
            $this->filename,
        );

        $url = \URL::signedRoute('report.download', ['filename' => $this->filename]);

        \Mail::to($this->email)->send(new \Condoedge\Utils\Mail\ExportReady($url, $this->filename));
    }
}
