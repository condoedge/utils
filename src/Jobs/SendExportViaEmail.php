<?php

namespace Condoedge\Utils\Jobs;

use Condoedge\Utils\Facades\UserModel;
use Condoedge\Utils\Services\Exports\ExcelExportSplitter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request as HttpRequest;

class SendExportViaEmail implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $exportableInstance;
    protected $email;
    protected $filename;
    protected $userId;

    protected $requestData = [];
    protected $routeName;
    
    protected $signedUrl;
    protected $zipSignedUrl; // When the export is split into a zip (ExcelExportSplitter)

    public $timeout = 0; // Disable timeout for this job to allow long-running exports

    /**
     * Create a new job instance.
     */
    public function __construct($exportableInstance, $email, $filename = null, $userId = null)
    {
        $this->exportableInstance = $exportableInstance;
        $this->email = $email;
        $this->userId = auth()->id();
        $this->filename = $filename ?? 'export-' . uniqid() . '.xlsx';

        $this->requestData = request()->all();

        $this->routeName = request('from_route') ?? request()->route()?->getName();

        $this->signedUrl = \URL::signedRoute('report.download', ['filename' => $this->filename]);
        $this->zipSignedUrl = \URL::signedRoute('report.download', ['filename' => ExcelExportSplitter::zipName($this->filename)]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app()->instance('bootFlag', true);

        // A sheet is built whole in memory (opt-in, e.g. '2G'). This process only: a queue worker stops after a job that
        // leaves it above its --memory. Left as is when unlimited (-1).
        if (($memoryLimit = config('kompo-utils.export-memory-limit')) && ini_get('memory_limit') !== '-1') {
            ini_set('memory_limit', $memoryLimit);
        }

        // Rebuilding the request context just if running through queue worker.
        if (app()->runningInConsole()) {
            $this->setOriginalRequest();

            if ($this->userId && $user = UserModel::find($this->userId)) {
                auth()->login($user);
            }
        }

        $exportableInstance = $this->exportableInstance;

        $export = function () use ($exportableInstance) {
            $component = getPrivateProperty($exportableInstance, 'component');

            if ($component && method_exists($component, 'bootForAction')) {
                $component->bootForAction();
            }

            // Excel::store(), or a zip of parts when the export is too big (opt-in).
            return app(ExcelExportSplitter::class)->store($exportableInstance, $this->filename);
        };

        // The user's export: in the worker too it reads with their security, not the console bypass (kompo/auth).
        $storedFilename = function_exists('executeWithoutConsoleBypass') ? executeWithoutConsoleBypass($export) : $export();

        $zipped = $storedFilename === ExcelExportSplitter::zipName($this->filename);

        \Mail::to($this->email)->send(new \Condoedge\Utils\Mail\ExportReady($zipped ? $this->zipSignedUrl : $this->signedUrl, $storedFilename));
    }

    protected function setOriginalRequest()
    {
        $route = app('router')->getRoutes()->getByName($this->routeName);

        if (!$route) {
            request()->merge($this->requestData);
            return;
        }

        $req = HttpRequest::create(
            $route->uri(),
            $route->methods()[0] ?? 'GET',
            $this->requestData
        );

        $boundRoute = $route->bind($req);
        $req->setRouteResolver(fn() => $boundRoute);

        app()->instance('request', $req);
    }
}
