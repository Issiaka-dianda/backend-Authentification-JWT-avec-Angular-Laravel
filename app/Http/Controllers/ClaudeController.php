<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ClaudeController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'headers' => [
                'x-api-key' => env('test-api-claude'), // Correction: utiliser une variable d'environnement correcte
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ]
        ]);
    }

    public function askClaude(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        try {
            $response = $this->client->post('messages', [
                'json' => [
                    'model' => 'claude-3-7-sonnet-20250219', // Mise à jour du modèle
                    'max_tokens' => 1024,
                    'messages' => [
                        ['role' => 'user', 'content' => $request->message]
                    ]
                ]
            ]);

            return response()->json(json_decode($response->getBody(), true));

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur de communication avec Claude API',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}