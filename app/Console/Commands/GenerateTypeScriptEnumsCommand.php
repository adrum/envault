<?php

namespace App\Console\Commands;

use ReflectionObject;
use Illuminate\Console\Command;
use Spatie\TypeScriptTransformer\Writers\ModuleWriter;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\Collectors\EnumCollector;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

class GenerateTypeScriptEnumsCommand extends Command
{
    protected $signature = 'typescript:enums';

    protected $description = 'Generate TypeScript values from PHP Enums.';

    public function handle(): void
    {
        $config = TypeScriptTransformerConfig::create()
            ->transformers(config('typescript-transformer.transformers'))
            ->defaultTypeReplacements(config('typescript-transformer.default_type_replacements'))
            ->formatter(null)
            ->transformToNativeEnums(true)
            ->autoDiscoverTypes(app_path('Enums'))
            ->writer(ModuleWriter::class)
            ->outputFile(resource_path('js/types/enums.ts'));

        $r = new ReflectionObject($config);
        $p = $r->getProperty('collectors');
        $p->setValue($config, [
            EnumCollector::class,
        ]);

        TypeScriptTransformer::create($config)->transform();
    }
}
