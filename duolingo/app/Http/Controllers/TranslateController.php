<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TranslateController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/translate",
     *   tags={"Translate"},
     *   summary="Translate a sentence from a source language to a target language",
     *   description="Uses the public MyMemory API. Accepts language names (e.g., 'German') or ISO-2 codes (e.g., 'de').",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="q", in="query", required=true, description="Text to translate (max 2000 chars)",
     *     @OA\Schema(type="string", maxLength=2000), example="Hello, how are you?"
     *   ),
     *   @OA\Parameter(
     *     name="source", in="query", required=true, description="Source language (name or ISO-2 code)",
     *     @OA\Schema(type="string"), example="en"
     *   ),
     *   @OA\Parameter(
     *     name="target", in="query", required=true, description="Target language (name or ISO-2 code)",
     *     @OA\Schema(type="string"), example="de"
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="source", type="object",
     *         @OA\Property(property="lang", type="string", example="en"),
     *         @OA\Property(property="text", type="string", example="Hello, how are you?")
     *       ),
     *       @OA\Property(
     *         property="target", type="object",
     *         @OA\Property(property="lang", type="string", example="de"),
     *         @OA\Property(property="text", type="string", example="Hallo, wie geht es dir?")
     *       ),
     *       @OA\Property(property="provider", type="string", example="MyMemory")
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(type="object", @OA\Property(property="error", type="string", example="Unauthorized"))
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error or unsupported language",
     *     @OA\JsonContent(type="object", @OA\Property(property="error", type="string", example="Use ISO-2 codes (e.g., en, de, fr) or common names (English, German...)"))
     *   ),
     *   @OA\Response(
     *     response=502,
     *     description="Translation service not reachable",
     *     @OA\JsonContent(type="object", @OA\Property(property="error", type="string", example="Translation service not reachable"))
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="No translation found",
     *     @OA\JsonContent(type="string", example="No translation found.")
     *   )
     * )
     */
    public function translate(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'q' => 'required|string|max:2000',
            'source' => 'required|string|max:20',
            'target' => 'required|string|max:20',
        ]);

        $map = [
            'english' => 'en',
            'en' => 'en',
            'german' => 'de',
            'de' => 'de',
            'french' => 'fr',
            'fr' => 'fr',
            'spanish' => 'es',
            'es' => 'es',
            'italian' => 'it',
            'it' => 'it',
            'portuguese' => 'pt',
            'pt' => 'pt',
            'serbian' => 'sr',
            'sr' => 'sr',
            'russian' => 'ru',
            'ru' => 'ru',
            'japanese' => 'ja',
            'ja' => 'ja',
            'chinese' => 'zh',
            'zh' => 'zh',
        ];

        $source = strtolower(trim($validated['source']));
        $target = strtolower(trim($validated['target']));
        $source = $map[$source] ?? $source;
        $target = $map[$target] ?? $target;

        if (strlen($source) !== 2 || strlen($target) !== 2) {
            return response()->json(['error' => 'Use ISO-2 codes (e.g., en, de, fr) or common names (English, German...)'], 422);
        }

        $email = env('MYMEMORY_EMAIL');

        $res = Http::timeout(10)->get('https://api.mymemory.translated.net/get', array_filter([
            'q' => $validated['q'],
            'langpair' => "{$source}|{$target}",
            'de'  => $email,
        ]));

        if (!$res->ok()) {
            return response()->json(['error' => 'Translation service not reachable'], 502);
        }

        $json = $res->json();
        $text = $json['responseData']['translatedText'] ?? null;

        if (!$text) {
            return response()->json('No translation found.', 404);
        }

        return response()->json([
            'source' => [
                'lang' => $source,
                'text' => $validated['q'],
            ],
            'target' => [
                'lang' => $target,
                'text' => $text,
            ],
            'provider' => 'MyMemory',
        ]);
    }
}
