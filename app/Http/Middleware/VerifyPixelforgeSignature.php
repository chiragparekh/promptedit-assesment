<?php

namespace App\Http\Middleware;

use App\Support\PixelforgeSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPixelforgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $isValid = PixelforgeSignature::fromConfig()->isValid(
            $request->header('Pixelforge-Signature'),
            $request->getContent(),
            time(),
        );

        if (! $isValid) {
            return response()->json(['message' => 'Invalid Pixelforge signature.'], 400);
        }

        return $next($request);
    }
}
