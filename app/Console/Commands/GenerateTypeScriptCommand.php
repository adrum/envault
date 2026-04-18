<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GenerateTypeScriptCommand extends Command
{
    protected $signature = 'typescript:all';

    protected $description = 'Generate all TypeScript and IDE Helper files.';

    public function handle(): void
    {
        Artisan::call('ide-helper:generate');
        $this->line(Artisan::output());
        Artisan::call('ide-helper:models --nowrite');
        $this->line(Artisan::output());
        Artisan::call('typescript:transform --ansi');
        $this->line(Artisan::output());
        Artisan::call('typescript:enums --ansi');
        $this->line(Artisan::output());
        Artisan::call('wayfinder:generate --path=resources/js/wayfinder --with-form');
        $this->line(Artisan::output());

        $files = [
            'enums.ts',
            'generated.d.ts',
        ];

        collect([
            'app',
        ])
            ->filter(fn ($dir) => is_dir(base_path('../' . $dir)))
            ->each(function ($dir) use ($files) {
                $dir = base_path('../' . $dir);
                exec("mkdir -p $dir/types 2>&1");
                foreach ($files as $file) {
                    exec('cp ' . resource_path('js/' . $file) . " $dir/types/" . $file . ' 2>&1');
                }
            });
    }
}
