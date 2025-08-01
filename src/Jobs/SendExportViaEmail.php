<?php

namespace Condoedge\Utils\Jobs;

use Condoedge\Utils\Facades\UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request as HttpRequest;

use function PHPUnit\Framework\throwException;

class SendExportViaEmail implements ShouldQueue
{
    use Queueable;

    protected $exportableInstance;
    protected $email;
    protected $filename;
    protected $userId;

    protected $requestData = [];
    protected $routeName;
    
    protected $signedUrl;

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
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app()->instance('bootFlag', true);
        
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

        \Mail::to($this->email)->send(new \Condoedge\Utils\Mail\ExportReady($this->signedUrl, $this->filename));
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
