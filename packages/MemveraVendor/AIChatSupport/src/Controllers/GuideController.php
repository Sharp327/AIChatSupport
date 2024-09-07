<?php

namespace MemveraVendor\AIChatSupport\Controllers;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;

class GuideController extends Controller
{
    public function uploadGuide(Request $request)
    {
        set_time_limit(2000);

        $validator = Validator::make($request->all(), [
            'guide' => 'required|file|mimes:pdf,txt,docx|max:20000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $file = $request->file('guide');
        $filePath = $file->storeAs('guides', $file->getClientOriginalName());
        $clientId = $request->input('clientId');

        try {
            // Extract text content based on file type
            $textContent = $this->extractText(storage_path("app/$filePath"), $file->extension());

            // Convert to UTF-8
            $textContent = mb_convert_encoding($textContent, 'UTF-8', 'auto');

            // Generate embeddings
            $embeddings = $this->generateEmbeddings($textContent);

            // Store embeddings in Pinecone
            $this->storeEmbeddingsInPinecone($embeddings, $clientId);

            return response()->json(['message' => 'Guide uploaded and processed successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to process the guide. ' . $e->getMessage()], 500);
        }
    }

    private function extractText($filePath, $extension)
    {
        switch ($extension) {
            case 'pdf':
                return (new \Smalot\PdfParser\Parser())->parseFile($filePath)->getText();
            case 'txt':
                return file_get_contents($filePath);
            case 'docx':
                return $this->getDocumentText($filePath);
            default:
                throw new \Exception('Unsupported file type.');
        }
    }

    public function getDocumentText(string $filepath): string
    {
        $document = IOFactory::createReader('Word2007')
            ->load($filepath);
        $documentText = '';

        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text = $this->getElementText($element);
                if (strlen($text)) {
                    // Ensure that the text from one section doesn't stick to the next section
                    $documentText .= $text . "\n";
                }
            }
        }

        return $documentText;
    }
    
    protected function getElementText($element): string
    {
        $result = '';
        $text = '';
        if ($element instanceof AbstractContainer) {
            foreach ($element->getElements() as $subElement) {
                if ($subElement instanceof \PhpOffice\PhpWord\Element\Text) {
                    $text .= $subElement->getText();
                    if (strlen($text)) {
                        $result .= $text . "\n";
                    }
                }
            }
        }


        return $result;
    }

    private function splitTextIntoChunks($text, $maxTokens = 2048)
    {
        $tokens = $this->tokenize($text);
        $chunks = [];
        $currentChunk = [];
        $currentTokenCount = 0;

        foreach ($tokens as $token) {
            $tokenCount = 1; // Adjust if using a different tokenizer

            if ($currentTokenCount + $tokenCount > $maxTokens) {
                $chunks[] = implode(' ', $currentChunk);
                $currentChunk = [];
                $currentTokenCount = 0;
            }

            $currentChunk[] = $token;
            $currentTokenCount += $tokenCount;
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }

    private function tokenize($text)
    {
        return preg_split('/\s+/', $text);
    }

    private function generateEmbeddings($textContent)
    {
        $client = new Client();
        $embeddings = [];
        $chunks = $this->splitTextIntoChunks($textContent);
        foreach ($chunks as $chunk) {
            $retry = 0;
            $success = false;
            while (!$success && $retry < 5) {
                try {
                    $response = $client->post('https://api.openai.com/v1/embeddings', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                        ],
                        'json' => [
                            'input' => $chunk,
                            'model' => 'text-embedding-ada-002',
                        ],
                    ]);

                    $data = json_decode($response->getBody(), true);

                    foreach ($data['data'] as $item) {
                        $embeddings[] = [
                            'embedding' => $item['embedding'],
                            'text' => $chunk,
                        ];
                    }

                    $success = true;
                } catch (\Exception $e) {
                    if ($e->getCode() == 429) {
                        sleep(pow(2, $retry));
                        $retry++;
                    } else {
                        throw new \Exception("Error generating embeddings: " . $e->getMessage());
                    }
                }
            }
        }

        return $embeddings;
    }

    private function storeEmbeddingsInPinecone($embeddings, $clientId)
    {
        $client = new Client();
        $expectedDimension = 1536;
        $formattedEmbeddings = [];

        foreach ($embeddings as $i => $embedding) {
            if (count($embedding['embedding']) !== $expectedDimension) {
                throw new \Exception("Vector dimension " . count($embedding['embedding']) . " does not match the expected dimension $expectedDimension.");
            }

            $formattedEmbeddings[] = [
                'id' => uniqid('vector_' . $i, true),
                'values' => $embedding['embedding'],
                'metadata' => ['text' => $embedding['text']],
            ];
        }

        try {
            $response = $client->post(env('PINECONE_ENDPOINT') . '/vectors/upsert', [
                'headers' => [
                    'Api-Key' => env('PINECONE_API_KEY'),
                    'Content-Type' => 'application/json',
                    'X-Pinecone-API-Version' => '2024-07'
                ],
                'json' => [
                    'vectors' => $formattedEmbeddings,
                    'namespace' => $clientId,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new \Exception("Error storing embeddings in Pinecone: " . $e->getMessage());
        }
    }
}
