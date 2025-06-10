<?php

namespace Condoedge\Utils\Jobs;

use Condoedge\Utils\Facades\UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request as HttpRequest;

class SendExportViaEmail implements ShouldQueue
{
    use Queueable;

    protected $exportableInstance;
    protected $email;
    protected $filename;
    protected $userId;

    protected $requestData;
    protected $routeName;
    protected $routeParameters = [];

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

        $route = request()->route();
        $this->routeName       = $route?->getName();
        $this->routeParameters = $route?->parameters() ?: [];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->setOriginalRequest();

        if ($this->userId && $user = UserModel::find($this->userId)) {
            auth()->login($user);
        }

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

    protected function setOriginalRequest()
    {
        $route = app('router')->getRoutes()->getByName($this->routeName);
        
        $req = HttpRequest::create(
            $route->uri(),
            $route->methods()[0] ?? 'GET',
            $this->requestData
        );

        $req->setRouteResolver(fn() => $route);

        app()->instance('request', $req);
    }
}
