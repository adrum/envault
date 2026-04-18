<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\User;
use Inertia\Inertia;
use App\Models\LogEntry;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('administrate');

        $query = LogEntry::with('user')->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        if ($request->filled('app')) {
            $query->where('loggable_type', 'app')->where('loggable_id', $request->get('app'));
        }

        if ($request->filled('user')) {
            $query->where('user_id', $request->get('user'));
        }

        $entries = $query->paginate(15)->withQueryString();

        return Inertia::render('log/index', [
            'entries' => $entries,
            'filters' => $request->only(['action', 'app', 'user']),
            'actions' => LogEntry::distinct()->pluck('action'),
            'apps' => App::pluck('name', 'id'),
            'users' => User::select('id', 'first_name', 'last_name')->get(),
        ]);
    }
}
