<?php

namespace Condoedge\Utils\Command;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class OpenTranslatorCommand extends Command
{
    protected $signature = 'app:translator
                            {json? : Optional path to an existing analyzer JSON export (skips a fresh scan)}
                            {--python= : Python executable to use. Defaults to "pythonw" on Windows (GUI, no console) and "python3" on Unix.}
                            {--foreground : Run the GUI in the foreground (block artisan until closed). Useful for debugging.}';

    protected $description = 'Open the SISC Translation Helper desktop GUI (Python/Tkinter).';

    public function handle(): int
    {
        $script = realpath(__DIR__ . '/../../tools/translator/translator.py');
        if (!$script || !is_file($script)) {
            $this->error('Translator script not found at ' . $script);
            return Command::FAILURE;
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $python = (string) ($this->option('python') ?: ($isWindows ? 'pythonw' : 'python3'));
        $jsonArg = (string) ($this->argument('json') ?? '');

        $cmd = [$python, $script];
        if ($jsonArg !== '') {
            $cmd[] = $jsonArg;
        }

        $this->info('Launching translator GUI …');
        $this->line('  ' . implode(' ', array_map('escapeshellarg', $cmd)));

        if ($this->option('foreground')) {
            // Foreground: pipe stdout/stderr so the user can see errors.
            $process = new Process($cmd, base_path());
            $process->setTimeout(null);
            $process->run(function ($type, $buffer) {
                $this->getOutput()->write($buffer);
            });
            return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
        }

        // Background (default): fully detach so the GUI survives artisan's exit.
        $this->spawnDetached($cmd, $isWindows);
        return Command::SUCCESS;
    }

    /**
     * Launch the process fully detached from the current PHP invocation.
     *
     * Symfony\Process::start() kills the child on PHP shutdown, which on Windows
     * makes the Tkinter window disappear immediately. We use platform-native
     * detachment instead.
     */
    private function spawnDetached(array $cmd, bool $isWindows): void
    {
        if ($isWindows) {
            // `start "" /B "<python>" "<script>" [arg]` — no new console, fully detaches.
            $quoted = array_map(
                fn($arg) => '"' . str_replace('"', '""', $arg) . '"',
                $cmd
            );
            $full = 'start "" /B ' . implode(' ', $quoted);
            pclose(popen($full, 'r'));
            return;
        }

        // Unix: nohup + background via shell_exec.
        $quoted = implode(' ', array_map('escapeshellarg', $cmd));
        shell_exec("nohup {$quoted} > /dev/null 2>&1 &");
    }
}
