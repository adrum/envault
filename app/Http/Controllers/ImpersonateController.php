<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function start(Request $request, User $user)
    {
        if (!$request->user()->isOwner()) {
            abort(403);
        }

        if ($request->user()->id === $user->id) {
            return back();
        }

        $request->session()->put('impersonator_id', $request->user()->id);

        Auth::login($user);

        return redirect('/apps');
    }

    public function stop(Request $request)
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        if (!$impersonatorId) {
            return redirect('/apps');
        }

        Auth::loginUsingId($impersonatorId);

        return redirect('/apps');
    }
}
