<?php

namespace Condoedge\Utils\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Finder\Finder;

class SendEmailForMissingTranslationsCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-missing-translations-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends an email report of missing translations to the configured translator email address.';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $missingTranslations = \Condoedge\Utils\Models\MissingTranslation::unresolved()->get();

        if ($missingTranslations->isEmpty()) {
            $this->info('No missing translations found.');
            return 0;
        }

        $message = "Missing Translations Report\n\n";
        $message .= "Total: " . $missingTranslations->count() . " missing translation(s)\n\n";
        
        foreach ($missingTranslations as $translation) {
            $message .= "- {$translation->translation_key}\n";
            if ($translation->created_at) {
                $message .= "  Seen in file: {$translation->package}\n";
            }
            $message .= "\n";
        }

        Mail::raw($message, function ($mail) use ($missingTranslations) {
            $mail->to(config('kompo-utils.translator-email'))
                ->subject('Missing Translations Report - ' . $missingTranslations->count() . ' keys');
        });

        $this->info('Email sent to ' . config('kompo-utils.translator-email'));
        
        return 0;
    }
}
