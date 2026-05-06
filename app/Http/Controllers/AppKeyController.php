<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Encryption\Encrypter;

class AppKeyController extends Controller
{
    /**
     * Generate a fresh Laravel APP_KEY (base64-encoded random bytes for the
     * configured cipher). Used by the warnings modal to one-click resolve
     * an empty APP_KEY warning.
     */
    public function generate(): JsonResponse
    {
        $cipher = config('app.cipher', 'AES-256-CBC');
        $key = 'base64:' . base64_encode(Encrypter::generateKey($cipher));

        return response()->json(['key' => $key]);
    }
}
