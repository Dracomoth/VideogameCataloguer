<?php
/**
 * src/Services/GeminiService.php
 * Lightweight, zero-dependency Google Gemini API service for AI metadata and specifications generation.
 * Compatible with standard PHP shared hosting (Hostinger) without external SDKs.
 * Version: 2.5.0.1
 */

declare(strict_types=1);

namespace Vault\Services;

use RuntimeException;
use InvalidArgumentException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class GeminiService
{
    private array $config;

    /**
     * @param array<string, mixed>|null $config Optional custom config array. Defaults to global config.
     */
    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
        } else {
            $globalConfig = $GLOBALS['config'] ?? null;
            if (empty($globalConfig) || empty($globalConfig['gemini'])) {
                $configFile = dirname(__DIR__, 2) . '/config/config.php';
                if (file_exists($configFile)) {
                    if (!defined('APP_INIT')) {
                        define('APP_INIT', true);
                    }
                    $loaded = require $configFile;
                    if (is_array($loaded)) {
                        $globalConfig = $loaded;
                        $GLOBALS['config'] = $loaded;
                    }
                }
            }
            $this->config = $globalConfig['gemini'] ?? [];
        }
    }

    /**
     * Generates technical specifications, hardware overview, and BIOS requirements for a console.
     *
     * @param array<string, mixed> $consoleData Hardware metadata (name, maker, type, generation, year)
     * @return string Generated markdown/plain-text specifications for Personal Notes / Specs
     * @throws RuntimeException If API key is missing or API request fails
     */
    public function generateConsoleSpecs(array $consoleData): string
    {
        $name       = trim((string)($consoleData['name'] ?? ''));
        $maker      = trim((string)($consoleData['maker'] ?? $consoleData['publisher_name'] ?? ''));
        $type       = trim((string)($consoleData['type'] ?? $consoleData['type_name'] ?? ''));
        $generation = trim((string)($consoleData['generation'] ?? ''));
        $year       = trim((string)($consoleData['year'] ?? $consoleData['release_year'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Console name is required to generate specifications.');
        }

        $promptParts = [
            "You are a retro and modern video game hardware preservation specialist.",
            "Generate structured technical specifications and notes for the following console/hardware platform:",
            "- Console Name: " . $name,
        ];

        if ($maker !== '') {
            $promptParts[] = "- Manufacturer / Maker: " . $maker;
        }
        if ($type !== '') {
            $promptParts[] = "- Hardware Type: " . $type;
        }
        if ($generation !== '') {
            $promptParts[] = "- Generation: " . $generation;
        }
        if ($year !== '') {
            $promptParts[] = "- Release Year: " . $year;
        }

        $promptParts[] = "";
        $promptParts[] = "Your response must strictly follow this exact structure without any meta-commentary, introductory text, thinking steps, or markdown code fences:";
        $promptParts[] = "";
        $promptParts[] = "[A brief 1-paragraph description of the console without any title or section heading]";
        $promptParts[] = "";
        $promptParts[] = "Technical Specs";
        $promptParts[] = "A bulleted list of key technical specs (architecture/bits, CPU, RAM, video/graphics, audio, media format).";
        $promptParts[] = "";
        $promptParts[] = "BIOS Files";
        $promptParts[] = "List of BIOS files needed for emulation/operation (with exact filenames if applicable, or explicitly state 'No BIOS files needed' if none are required).";

        $prompt = implode("\n", $promptParts);

        return $this->generateContent($prompt);
    }

    /**
     * Generates a company description and brief history for a video game publisher.
     *
     * @param string $name Publisher name
     * @param bool $isConsoleMaker Whether the publisher is also a console manufacturer
     * @return string Generated narrative description and brief history
     * @throws RuntimeException If API key is missing or API request fails
     */
    public function generatePublisherDescription(string $name, bool $isConsoleMaker = false): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Publisher name is required to generate description and history.');
        }

        $promptParts = [
            "You are a video game industry historian and preservation specialist.",
            "Write a concise description and brief history for the video game publisher/company: " . $name . ".",
        ];

        if ($isConsoleMaker) {
            $promptParts[] = "Note: This company is also known as a video game console / hardware manufacturer.";
        }

        $promptParts[] = "";
        $promptParts[] = "Instructions:";
        $promptParts[] = "- Provide 2 to 3 well-written, engaging paragraphs covering:";
        $promptParts[] = "  1. A small description of the company, its origins/founding, headquarters, and major identity in gaming.";
        $promptParts[] = "  2. A brief history highlighting key eras, landmark franchises, notable consoles (if applicable), and major contributions to the video game industry.";
        $promptParts[] = "  3. Its current status or lasting legacy.";
        $promptParts[] = "- STRICT FORMAT RULE: Do NOT include any markdown headings (no '#', '##', or '###'), bullet points, asterisks lists, or meta-commentary like 'Here is a description'.";
        $promptParts[] = "- Output ONLY the clean narrative text with standard paragraphs separated by empty lines.";

        $prompt = implode("\n", $promptParts);

        $raw = $this->generateContent($prompt);

        return $this->cleanParagraphs($raw);
    }

    /**
     * Generates relevant taxonomy tags and a concise 1-3 paragraph description for a video game.
     *
     * @param array<string, mixed> $gameData Game metadata (title, console, category, subcategory, publisher, year)
     * @return array{tags: string, comments: string} Associative array with sanitized tags and comments
     * @throws RuntimeException If API request fails or title is missing
     */
    public function generateGameMetadata(array $gameData): array
    {
        $title       = trim((string)($gameData['title'] ?? ''));
        $console     = trim((string)($gameData['console'] ?? $gameData['console_name'] ?? ''));
        $category    = trim((string)($gameData['category'] ?? $gameData['category_name'] ?? ''));
        $subcategory = trim((string)($gameData['subcategory'] ?? $gameData['subcategory_name'] ?? ''));
        $publisher   = trim((string)($gameData['publisher'] ?? $gameData['publisher_name'] ?? ''));
        $year        = trim((string)($gameData['year'] ?? $gameData['release_year'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('Game title is required to generate game metadata.');
        }

        $promptParts = [
            "You are a retro and modern video game preservation specialist and archivist.",
            "Analyze the following video game and generate cataloging metadata:",
            "- Game Title: " . $title,
        ];

        if ($console !== '') {
            $promptParts[] = "- Platform / Console: " . $console;
        }
        if ($category !== '') {
            $promptParts[] = "- Category / Genre: " . $category;
        }
        if ($subcategory !== '') {
            $promptParts[] = "- Subcategory: " . $subcategory;
        }
        if ($publisher !== '') {
            $promptParts[] = "- Publisher / Developer: " . $publisher;
        }
        if ($year !== '') {
            $promptParts[] = "- Release Year: " . $year;
        }

        $promptParts[] = "";
        $promptParts[] = "Generate two specific fields:";
        $promptParts[] = "1. \"tags\": A comma-separated list of concise lowercase tags (without '#' symbol) including:";
        $promptParts[] = "   - Console / platform (e.g. nes, snes, genesis, playstation, arcade, atari 2600)";
        $promptParts[] = "   - Category and subcategory";
        $promptParts[] = "   - Game family or subgenre if applicable (e.g. metroidvania, roguelike, souls-like, shmup, bullet-hell, beat-em-up, dungeon-crawler)";
        $promptParts[] = "   - Special peripherals required or supported if applicable (e.g. zapper, light-gun, power pad, paddle, super scope, mouse, multi-tap)";
        $promptParts[] = "   - Gameplay characteristics (e.g. single-player, multiplayer, 2-player, co-op, vs-mode, split-screen)";
        $promptParts[] = "";
        $promptParts[] = "2. \"comments\": A brief description of the game itself in exactly 1 to 3 paragraphs:";
        $promptParts[] = "   - Summarize the gameplay premise and core mechanics.";
        $promptParts[] = "   - Note if the game has different gameplay modes.";
        $promptParts[] = "   - Note if the game has multiplayer (number of players, cooperative or competitive).";
        $promptParts[] = "   - Note if it requires or can be played with a particular peripheral (such as Atari paddle controllers, NES Zapper, Power Pad, etc.).";
        $promptParts[] = "   - STRICT FORMAT RULE: No headings, no subheadings, no bullet points, and no introductory meta-text. Just 1 to 3 narrative paragraphs separated by empty lines.";
        $promptParts[] = "";
        $promptParts[] = "Return ONLY a valid JSON object matching this schema with no markdown code blocks:";
        $promptParts[] = '{"tags": "...", "comments": "..."}';

        $prompt = implode("\n", $promptParts);

        $rawResponse = $this->generateContent($prompt);

        return $this->parseGameMetadataResponse($rawResponse);
    }

    /**
     * Parses and cleans Gemini raw response into structured tags and comments.
     *
     * @param string $raw
     * @return array{tags: string, comments: string}
     */
    private function parseGameMetadataResponse(string $raw): array
    {
        $cleaned = trim($raw);

        // Strip markdown code fences if present
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $cleaned, $m)) {
            $cleaned = trim($m[1]);
        }

        // Direct JSON decode
        $decoded = json_decode($cleaned, true);
        if (is_array($decoded) && (isset($decoded['tags']) || isset($decoded['comments']))) {
            return [
                'tags'     => $this->sanitizeTags((string)($decoded['tags'] ?? '')),
                'comments' => $this->cleanParagraphs((string)($decoded['comments'] ?? '')),
            ];
        }

        // Fallback: locate JSON object within text
        if (preg_match('/\{[\s\S]*?"tags"[\s\S]*?"comments"[\s\S]*?\}/', $cleaned, $jsonMatch)) {
            $subDecoded = json_decode($jsonMatch[0], true);
            if (is_array($subDecoded) && (isset($subDecoded['tags']) || isset($subDecoded['comments']))) {
                return [
                    'tags'     => $this->sanitizeTags((string)($subDecoded['tags'] ?? '')),
                    'comments' => $this->cleanParagraphs((string)($subDecoded['comments'] ?? '')),
                ];
            }
        }

        // Fallback: regex search
        $tags = '';
        $comments = '';

        if (preg_match('/"tags"\s*:\s*"([^"]+)"/i', $raw, $tm)) {
            $tags = $tm[1];
        } elseif (preg_match('/(?:^|\n)(?:Tags|TAGS):\s*([^\n]+)/i', $raw, $tm)) {
            $tags = $tm[1];
        }

        if (preg_match('/"comments"\s*:\s*"((?:[^"\\\\]+|\\\\.)*)(?:"|\z)/s', $raw, $cm)) {
            $comments = $this->unescapeJsonString($cm[1]);
        } elseif (preg_match('/(?:^|\n)(?:Comments|Description|Notes):\s*([\s\S]+)$/i', $raw, $cm)) {
            $comments = trim($cm[1]);
        } else {
            $stripped = preg_replace('/^\s*\{\s*"tags"[\s\S]*?"comments"\s*:\s*"?/i', '', $raw);
            $stripped = preg_replace('/"?\s*\}\s*$/', '', $stripped);
            $comments = $this->unescapeJsonString(trim($stripped));
        }

        return [
            'tags'     => $this->sanitizeTags($tags),
            'comments' => $this->cleanParagraphs($comments),
        ];
    }

    /**
     * Unescapes common JSON string sequences without corrupting newlines.
     *
     * @param string $str
     * @return string
     */
    private function unescapeJsonString(string $str): string
    {
        return str_replace(
            ['\\"', '\\\\', '\\/', '\n', '\r', '\t'],
            ['"', '\\', '/', "\n", "\r", "\t"],
            $str
        );
    }

    /**
     * Sanitizes, normalizes, and deduplicates a comma-separated tags string.
     *
     * @param string $tags
     * @return string
     */
    private function sanitizeTags(string $tags): string
    {
        $normalized = str_replace(["\r", "\n", ";"], ',', $tags);
        $parts = explode(',', $normalized);

        $clean = [];
        $seen = [];

        foreach ($parts as $part) {
            $tag = trim($part);
            $tag = ltrim($tag, '#');
            $tag = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);

            if ($tag !== '' && !isset($seen[$tag])) {
                $clean[] = $tag;
                $seen[$tag] = true;
            }
        }

        return implode(', ', $clean);
    }

    /**
     * Cleans comment text to ensure strictly 1-3 narrative paragraphs with no headings or bullets.
     *
     * @param string $text
     * @return string
     */
    private function cleanParagraphs(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove markdown headings (# Heading, ## Heading)
        $text = preg_replace('/^#+\s+.*$/m', '', $text) ?? $text;

        // Remove common labels like "Description:", "Notes:", "Overview:", "Game Modes:" at line starts
        $text = preg_replace('/^(?:Description|Overview|Personal Notes|Comments|Game Modes|Modes|Features|Gameplay):\s*/mi', '', $text) ?? $text;

        // Remove bullet markers at line starts (- item, * item, • item)
        $text = preg_replace('/^\s*[-*•]\s+/m', '', $text) ?? $text;

        // Split into non-empty paragraphs
        $paragraphs = array_values(array_filter(array_map('trim', explode("\n\n", $text))));

        // If no double newlines, try single newlines
        if (count($paragraphs) <= 1 && str_contains($text, "\n")) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
            if (count($lines) > 1) {
                $paragraphs = $lines;
            }
        }

        // Limit to max 3 paragraphs
        if (count($paragraphs) > 3) {
            $paragraphs = array_slice($paragraphs, 0, 3);
        }

        return implode("\n\n", $paragraphs);
    }

    private ?string $lastUsedModel = null;

    /**
     * Returns the model that successfully generated content in the latest execution.
     *
     * @return string|null
     */
    public function getLastUsedModel(): ?string
    {
        return $this->lastUsedModel;
    }

    /**
     * Resolves the list of models in order of priority.
     *
     * @return array<int, string>
     */
    public function getModels(): array
    {
        if (!empty($this->config['models']) && is_array($this->config['models'])) {
            return array_values(array_filter(array_map('trim', $this->config['models'])));
        }

        if (!empty($this->config['model'])) {
            return [trim((string)$this->config['model'])];
        }

        return ['gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.5-flash'];
    }

    /**
     * Executes a generateContent call against Google Gemini REST API with automatic multi-model fallback.
     *
     * In case a higher-priority model is unavailable, rate-limited, or overloaded (e.g. 503 / 429),
     * it automatically cascades to the next configured model.
     *
     * @param string $prompt
     * @return string Response text
     * @throws RuntimeException If all models in the fallback chain fail or credentials missing
     */
    public function generateContent(string $prompt): string
    {
        // Extend maximum script execution time for AI multi-model fallback cascade (up to 120s)
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');

        $apiKey = trim((string)($this->config['api_key'] ?? ''));

        if ($apiKey === '' || $apiKey === 'YOUR_GEMINI_API_KEY' || $apiKey === 'your_gemini_api_key') {
            throw new RuntimeException(
                "Gemini API key is not configured. Please add a valid 'api_key' to the 'gemini' section in config/config.php."
            );
        }

        $models = $this->getModels();
        if (empty($models)) {
            $models = ['gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.5-flash'];
        }

        $attemptErrors = [];

        foreach ($models as $model) {
            try {
                $result = $this->callModel($model, $apiKey, $prompt);
                $this->lastUsedModel = $model;
                return $result;
            } catch (\Throwable $e) {
                $attemptErrors[$model] = $e->getMessage();
                error_log(sprintf(
                    "[GeminiService] Model '%s' failed (%s). Cascading to next fallback model...",
                    $model,
                    $e->getMessage()
                ));
            }
        }

        $errorSummary = [];
        foreach ($attemptErrors as $model => $err) {
            $errorSummary[] = "{$model}: {$err}";
        }

        throw new RuntimeException(
            'All Gemini models failed: ' . implode(' | ', $errorSummary)
        );
    }

    /**
     * Executes the API request for a specific model.
     *
     * @param string $model
     * @param string $apiKey
     * @param string $prompt
     * @return string
     * @throws RuntimeException
     */
    private function callModel(string $model, string $apiKey, string $prompt): string
    {
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($model),
            urlencode($apiKey)
        );

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => 2048,
            ]
        ];

        $response = $this->httpRequest('POST', $endpoint, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        // Parse candidate response
        $candidates = $response['candidates'] ?? [];
        if (!empty($candidates) && isset($candidates[0]['content']['parts'][0]['text'])) {
            return trim((string)$candidates[0]['content']['parts'][0]['text']);
        }

        if (isset($response['error']['message'])) {
            throw new RuntimeException((string)$response['error']['message']);
        }

        throw new RuntimeException('Unexpected or empty response from model.');
    }

    /**
     * Lightweight zero-dependency HTTP client using cURL with stream context fallback.
     *
     * @param string $method
     * @param string $url
     * @param array<string, mixed>|null $data
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function httpRequest(string $method, string $url, ?array $data = null, array $headers = []): array
    {
        $jsonBody = $data !== null ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $timeout = max(5, (int)($this->config['timeout'] ?? 20));
        $connectTimeout = min(8, $timeout);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $curlHeaders = $headers;
            if ($jsonBody !== null) {
                $curlHeaders[] = 'Content-Length: ' . strlen($jsonBody);
            }

            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $curlHeaders,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'VideogameVault-Gemini-Client/1.0',
            ]);

            if ($jsonBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            }

            $rawResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($rawResponse === false) {
                throw new RuntimeException("HTTP connection failed: {$curlError}");
            }

            $decoded = json_decode((string)$rawResponse, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("Invalid JSON response from Gemini API (HTTP {$httpCode}): {$rawResponse}");
            }

            if ($httpCode >= 400) {
                $msg = $decoded['error']['message'] ?? "HTTP error {$httpCode}";
                throw new RuntimeException("Gemini API error ({$httpCode}): {$msg}");
            }

            return $decoded;
        }

        // Fallback: stream context
        $streamHeaders = implode("\r\n", $headers);
        if ($jsonBody !== null) {
            $streamHeaders .= "\r\nContent-Length: " . strlen($jsonBody);
        }
        $streamHeaders .= "\r\nUser-Agent: VideogameVault-Gemini-Client/1.0";

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => $streamHeaders,
                'content'       => $jsonBody,
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $rawResponse = @file_get_contents($url, false, $context);
        if ($rawResponse === false) {
            $err = error_get_last();
            throw new RuntimeException("Connection failed: " . ($err['message'] ?? 'Unknown error'));
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid response from Gemini API: {$rawResponse}");
        }

        if (isset($decoded['error']['message'])) {
            throw new RuntimeException("Gemini API error: " . $decoded['error']['message']);
        }

        return $decoded;
    }
}
