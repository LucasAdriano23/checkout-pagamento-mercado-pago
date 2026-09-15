<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Nomes das variáveis definidas no .env, para removê-las do ambiente do processo filho.
 *
 * Sem isso o "artisan test" filho herda DB_DATABASE=laravel deste processo (em $_SERVER,
 * o primeiro adaptador que o env() consulta), roda o RefreshDatabase contra o banco de
 * desenvolvimento e o apaga. É o mesmo que o TestCommand do Collision faz com clearEnv()
 * quando chamado de um shell limpo.
 */
$dotenvKeys = function (): array {
    $path = base_path('.env');

    if (! is_file($path)) {
        return [];
    }

    return collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        ->map(fn (string $line) => trim($line))
        ->reject(fn (string $line) => $line === '' || str_starts_with($line, '#') || ! str_contains($line, '='))
        ->mapWithKeys(fn (string $line) => [trim(Str::before($line, '=')) => false])
        ->all();
};

Artisan::command('coverage {--min= : Falha se a cobertura total ficar abaixo deste percentual}', function () use ($dotenvKeys) {
    // Config cacheada ignora o DB_DATABASE=testing do phpunit.xml.
    $this->call('config:clear');

    $command = [PHP_BINARY, 'artisan', 'test', '--coverage-html', 'public/coverage'];

    if ($min = $this->option('min')) {
        $command[] = '--min='.$min;
    }

    $process = new Process($command, base_path(), $dotenvKeys(), timeout: null);

    $exitCode = $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

    if ($exitCode === 0) {
        $this->newLine();
        $this->info('Relatório de cobertura: '.rtrim(config('app.url'), '/').'/coverage/index.html');
    }

    return $exitCode;
})->purpose('Roda a suíte de testes e publica o relatório de cobertura em public/coverage');
