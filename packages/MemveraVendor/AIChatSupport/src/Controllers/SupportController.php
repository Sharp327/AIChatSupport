<?php
namespace MemveraVendor\AIChatSupport\Controllers;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class SupportController extends Controller
{
    public function handleQuery(Request $request)
    {
        $query = $request->input('query');
        $clientId = $request->input('clientId');

        // Generate embedding for the user query
        $queryEmbedding = $this->generateEmbeddings($query);

        // Retrieve relevant documents from Pinecone
        $relevantDocs = $this->retrieveRelevantDocs($queryEmbedding, $clientId);

        // Format the retrieved documents
        $formattedDocs = $this->formatRelevantDocs($relevantDocs);

        // Generate a response using ChatGPT
        $response = $this->generateResponse($query, $formattedDocs);

        return response()->json(['response' => $response]);
    }

    private function generateEmbeddings($text)
    {
        $client = new Client();
        
        $response = $client->post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ],
            'json' => [
                'input' => $text,
                'model' => 'text-embedding-ada-002',
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['data'][0]['embedding'] ?? [];
    }

    private function retrieveRelevantDocs($queryEmbedding, $clientId)
    {
        $client = new Client();

        if (!is_array($queryEmbedding) || empty($queryEmbedding)) {
            throw new \Exception("Invalid query embedding data.");
        }

        $response = $client->post(env('PINECONE_ENDPOINT') . '/query', [
            'headers' => [
                'Api-Key' => env('PINECONE_API_KEY'),
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-07',
            ],
            'json' => [
                'vector' => $queryEmbedding,
                'top_k' => 2,
                'namespace' => $clientId,
                'include_metadata' => true,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    private function formatRelevantDocs($relevantDocs)
    {
        if (!isset($relevantDocs['matches'])) {
            return "No relevant documents found.";
        }

        $formattedDocs = "";

        foreach ($relevantDocs['matches'] as $match) {
            $formattedDocs .= "- " . ($match['metadata']['title'] ?? 'Document') . ": " . ($match['metadata']['text'] ?? '') . "\n";
        }

        return $formattedDocs;
    }

    private function generateResponse($query, $formattedDocs)
    {
        $client = new Client();
        
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that provides information based on retrieved documents.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $query,
                    ],
                    [
                        'role' => 'assistant',
                        'content' => "Based on the following documents:\n" . $formattedDocs,
                    ],
                ],
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';
    }
}
