<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app-setup-tokens:flush')->everyFiveMinutes();
Schedule::command('auth-requests:flush')->everyFiveMinutes();
Schedule::command('cloudflare:reload')->daily();
