<?php

namespace App\Transformers;

use Carbon\Carbon;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Transformers\Transformer;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContext;

class DateTimeTransformer implements Cast, Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        if (is_null($value)) {
            return null;
        }

        /** @var Carbon $value */
        return $value->clone()->timezone('UTC')->toIso8601String();
    }

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (is_null($value)) {
            return null;
        }

        return Carbon::parse($value)->timezone('UTC');
    }
}
