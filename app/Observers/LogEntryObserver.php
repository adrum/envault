<?php

namespace App\Observers;

use App\Models\LogEntry;

class LogEntryObserver
{
    /**
     * Handle the log entry "created" event.
     *
     * @return void
     */
    public function created(LogEntry $logEntry)
    {
        if (auth()->user()) {
            $logEntry->user()->associate(auth()->user());

            $logEntry->save();
        }
    }
}
