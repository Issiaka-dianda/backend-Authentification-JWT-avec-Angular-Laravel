<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class OpenAIController extends Controller
{
    private $client;

    public function __construct()
    {
       // sk-proj- -Wvq-OFLnXglln0N7Wviu9F6TYBdqCz3s9IvXoA
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    public function askOpenAI(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-3.5-turbo',  // Modèle récent d'OpenAI
                    'messages' => [
                        ['role' => 'user', 'content' => $request->message]
                    ],
                    'max_tokens' => 1024,
                    'temperature' => 0.7
                ]
            ]);

            return response()->json(json_decode($response->getBody(), true));

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur de communication avec OpenAI API',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}