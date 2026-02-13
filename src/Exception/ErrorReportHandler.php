<?php

namespace Condoedge\Utils\Exception;

use Condoedge\Utils\Kompo\HttpExceptions\GenericErrorView;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ErrorReportHandler
{
    protected $error;
    protected $extraData = [];
    protected $extraDataCallback;

    public function __construct(Throwable $e)
    {
        $this->error = $e;
    }

    public function setExtraData($data) 
    {
        $this->extraData = $data;

        return $this;
    }

    public function setExtraDataCallback(callable $callback) 
    {
        $this->extraDataCallback = $callback;

        return $this;
    }

    public function handle()
    {
        $e = $this->error;
        $currentKomponent = app()->bound('currentKomponent') ? app()->make('currentKomponent') : 'No component';

        $contextData = [
            'current_komponent' => $currentKomponent,
            'team_id' => auth()->user()?->current_team_id,
            'team_name' => auth()->user()?->currentTeam?->name,
            'user_id' => auth()->user()?->id,
            'route' => request()->route()?->getName(),
            'parameters' => collect(request()->all())->except(['password', 'password_confirmation', 'current_password'])->toArray(),
            'previous_url' => url()->previous(),
            'url' => request()->url(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            ...$this->extraData,
        ];

        try {
            if ($this->extraDataCallback) {
                $callbackData = call_user_func($this->extraDataCallback, $e);
                if (is_array($callbackData)) {
                    $contextData = array_merge($contextData, $callbackData);
                }
            }
        } catch (\Exception $ex) {
            \Log::error('Error while injecting more context in error report for '.$currentKomponent.': '.$ex->getMessage(), [
                'context' => json_decode(json_encode($contextData), true),
            ]);
        }

        if (app()->runningInConsole()) {
            $input = new \Symfony\Component\Console\Input\ArgvInput();
            $commandName = $input->getFirstArgument();
            $arguments = $input->getArguments();
            $options = $input->getOptions();

            $contextData = array_merge($contextData, [
                'command' => $commandName,
                'arguments' => $arguments,
                'options' => $options,
            ]);
        }

        \Log::error('Upcoming error in '.$currentKomponent, [
            'context' => json_decode(json_encode($contextData), true),
        ]);
    }
}