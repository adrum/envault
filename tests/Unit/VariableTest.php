<?php

use App\Models\App;
use App\Models\Variable;
use App\Models\VariableVersion;

test('latest version retrieved', function () {
    $app = App::factory()->create();

    $variable = $app->variables()->create(Variable::factory()->make()->toArray());

    $variableVersion = $variable->versions()->create(VariableVersion::factory()->make()->toArray());

    expect($variableVersion->id)->toEqual($variable->latest_version->id);
});
