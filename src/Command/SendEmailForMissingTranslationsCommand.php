<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Services\Translation\MissingTranslationRecord;
use Condoedge\Utils\Services\Translation\MissingTranslationsStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEmailForMissingTranslationsCommand extends Command
{
    protected $signature = 'app:send-missing-translations-email';

    protected $description = 'Sends an email report of missing translations to the configured translator email address.';

    public function __construct(private readonly MissingTranslationsStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $unresolved = $this->store->unresolved();

        if ($unresolved->isEmpty()) {
            $this->info('No missing translations found.');
            return Command::SUCCESS;
        }

        $message = "Missing Translations Report\n\n";
        $message .= "Total: " . $unresolved->count() . " missing translation(s)\n\n";

        foreach ($unresolved as $row) {
            /** @var MissingTranslationRecord $row */
            $message .= "- {$row->translation_key}\n";
            if ($row->package) {
                $message .= "  Seen in file: {$row->package}\n";
            }
            $message .= "\n";
        }

        Mail::raw($message, function ($mail) use ($unresolved) {
            $mail->to(config('kompo-utils.translator-email'))
                ->subject('Missing Translations Report - ' . $unresolved->count() . ' keys');
        });

        $this->info('Email sent to ' . config('kompo-utils.translator-email'));
        return Command::SUCCESS;
    }
}
