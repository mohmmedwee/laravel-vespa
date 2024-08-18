<?php

namespace YourVendor\Vespa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VespaClient
{
    protected $vespaUrl;
    protected $plugins = [];
    protected $apiKey;
    protected $language;
    protected $rateLimit;
    protected $rateLimitCacheKey;
    protected $throttleLimit;
    protected $throttleCacheKey;

    public function __construct()
    {
        $this->vespaUrl = config('vespa.url');
        $this->apiKey = config('vespa.api_key');
        $this->language = config('vespa.language');
        $this->rateLimit = config('vespa.rate_limit'); // Max requests per minute
        $this->rateLimitCacheKey = config('vespa.rate_limit');
        $this->throttleLimit = config('vespa.throttle_limit'); // Max simultaneous requests
        $this->throttleCacheKey = config('vespa.throttle_limit');
    }

    // Plugin Registration
    public function registerPlugin($plugin, $priority = 0)
    {
        $this->plugins[$priority][] = $plugin;
        ksort($this->plugins);
    }

    protected function executePlugins($query)
    {
        foreach ($this->plugins as $priority => $plugins) {
            foreach ($plugins as $plugin) {
                $query = $plugin->handle($query);
            }
        }
        return $query;
    }

    // Authentication
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected function withAuthentication($method, $url, $body = [])
    {
        $request = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        return $request->$method($url, $body);
    }

    // Internationalization
    public function setLanguage($language)
    {
        $this->language = $language;
    }


    // Create or Deploy a Vespa Application
    public function createApplication($applicationPackagePath)
    {
        // Ensure the application package file exists
        if (!file_exists($applicationPackagePath)) {
            throw new \Exception("Application package not found at: {$applicationPackagePath}");
        }

        // Prepare the application (upload the ZIP file)
        $prepareResponse = $this->performRequestWithRetry(
            'post',
            "{$this->vespaUrl}/application/v2/tenant/default/session",
            [
                'headers' => ['Content-Type' => 'application/zip'],
                'body' => file_get_contents($applicationPackagePath)
            ]
        );

        // Extract session ID from the prepare response
        $sessionId = $prepareResponse->json()['session-id'] ?? null;
        if (!$sessionId) {
            throw new \Exception("Failed to obtain session ID during application preparation.");
        }

        // Activate the application
        $activateResponse = $this->performRequestWithRetry(
            'put',
            "{$this->vespaUrl}/application/v2/tenant/default/session/{$sessionId}/active"
        );

        if ($activateResponse->successful()) {
            Log::info("Application successfully deployed with session ID {$sessionId}");
            return $activateResponse->json();
        } else {
            throw new \Exception("Failed to activate the application: " . $activateResponse->body());
        }
    }


    // Retry Logic with Exponential Backoff
    public function performRequestWithRetry($method, $url, $body = [], $retryCount = 3)
    {
        $attempt = 0;
        while ($attempt < $retryCount) {
            try {
                $response = $this->withAuthentication($method, $url, $body);
                if ($response->successful()) {
                    return $response;
                }
                throw new \Exception("Request failed with status {$response->status()}");
            } catch (\Exception $e) {
                $attempt++;
                Log::warning("Attempt {$attempt} failed: {$e->getMessage()}", [
                    'url' => $url,
                    'body' => $body,
                    'attempt' => $attempt,
                ]);
                sleep(pow(2, $attempt)); // Exponential backoff
                if ($attempt >= $retryCount) {
                    throw new \Exception("All retries failed: {$e->getMessage()}");
                }
            }
        }
    }

    // Rate Limiting
    protected function rateLimitCheck()
    {
        $count = Cache::get($this->rateLimitCacheKey, 0);
        if ($count >= $this->rateLimit) {
            throw new \Exception("Rate limit exceeded. Please try again later.");
        }
        Cache::increment($this->rateLimitCacheKey);
        Cache::put($this->rateLimitCacheKey, $count + 1, 60); // Expire the key after 60 seconds
    }

    // Throttling
    protected function throttleCheck()
    {
        $count = Cache::get($this->throttleCacheKey, 0);
        if ($count >= $this->throttleLimit) {
            throw new \Exception("Too many requests. Please try again later.");
        }
        Cache::increment($this->throttleCacheKey);
        Cache::put($this->throttleCacheKey, $count + 1, 60); // Expire the key after 60 seconds
    }

    // Search with Advanced Capabilities
    public function search($query, $options = [])
    {
        $this->rateLimitCheck();
        $this->throttleCheck();

        $query = $this->executePlugins($query);

        $body = array_merge([
            'yql' => "select * from sources * where userInput('$query');",
            'ranking.profile' => $options['ranking.profile'] ?? 'default',
            'language' => $this->language,
        ], $options);

        $start = microtime(true);
        $response = $this->performRequestWithRetry('post', "{$this->vespaUrl}/search/", $body);

        $executionTime = microtime(true) - $start;
        Log::info("Vespa search executed in {$executionTime} seconds", [
            'query' => $query,
            'response' => $response->json(),
        ]);

        $this->auditLog('search', [
            'query' => $query,
            'execution_time' => $executionTime,
            'response' => $response->json(),
        ]);

        Cache::decrement($this->throttleCacheKey);
        return $response->json();
    }

    // Cached Search with Invalidation
    public function cachedSearch($query, $options = [], $cacheTime = 3600)
    {
        $cacheKey = md5(json_encode([$query, $options]));
        return Cache::remember($cacheKey, $cacheTime, function() use ($query, $options) {
            return $this->search($query, $options);
        });
    }

    public function invalidateCache($query, $options = [])
    {
        $cacheKey = md5(json_encode([$query, $options]));
        Cache::forget($cacheKey);
    }

    // Search with Pagination
    public function searchWithPagination($query, $page = 1, $perPage = 10, $options = [])
    {
        $options['offset'] = ($page - 1) * $perPage;
        $options['hits'] = $perPage;

        return $this->search($query, $options);
    }

    // Search with Custom Ranking
    public function searchWithCustomRanking($query, $rankingExpression)
    {
        return $this->search($query, ['ranking.expression' => $rankingExpression]);
    }

    // Insert a Document
    public function insertDocument($document, $id)
    {
        $response = $this->withAuthentication(
            Http::put("{$this->vespaUrl}/document/v1/myapp/myapp/docid/{$id}", $document)
        );

        Log::info("Inserted document with ID {$id}", [
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    // Update a Document
    public function updateDocument($id, $fields)
    {
        $response = $this->withAuthentication(
            Http::put("{$this->vespaUrl}/document/v1/myapp/myapp/docid/{$id}", [
                'fields' => $fields,
            ])
        );

        Log::info("Updated document with ID {$id}", [
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    // Delete a Document
    public function deleteDocument($id)
    {
        $response = $this->withAuthentication(
            Http::delete("{$this->vespaUrl}/document/v1/myapp/myapp/docid/{$id}")
        );

        Log::info("Deleted document with ID {$id}", [
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    // Batch Insert Documents with Retry
    public function batchInsertDocuments($documents)
    {
        $responses = [];
        foreach ($documents as $id => $document) {
            $responses[$id] = $this->performRequestWithRetry('put', "{$this->vespaUrl}/document/v1/myapp/myapp/docid/{$id}", $document);
        }

        Log::info("Batch insert completed", [
            'responses' => $responses,
        ]);

        return $responses;
    }

    // Partial Update of a Document
    public function partialUpdateDocument($id, $fields)
    {
        $response = $this->withAuthentication(
            Http::put("{$this->vespaUrl}/document/v1/myapp/myapp/docid/{$id}", [
                'fields' => $fields,
            ])
        );

        Log::info("Partially updated document with ID {$id}", [
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    // Real-Time Search with WebSocket (Basic Setup)
    public function realTimeSearch($query, $callback)
    {
        // For a real-time WebSocket implementation, integrate with a WebSocket client
        // This is a placeholder method for demo purposes
        $response = $this->search($query);

        // Call the callback function with the response
        $callback($response);

        return $response;
    }

    // High Availability with Failover
    public function searchWithFailover($query, $nodes)
    {
        foreach ($nodes as $node) {
            $this->vespaUrl = $node;
            try {
                return $this->search($query);
            } catch (\Exception $e) {
                Log::warning("Node {$node} failed: {$e->getMessage()}");
                continue;
            }
        }
        throw new \Exception("All Vespa nodes are down.");
    }

    // Integration with Other Tools (e.g., ML Models)
    public function searchWithMLModel($query, $modelEndpoint, $options = [])
    {
        $modelResponse = Http::post($modelEndpoint, ['query' => $query]);

        if ($modelResponse->successful()) {
            $mlParams = $modelResponse->json();
            return $this->search($query, array_merge($options, $mlParams));
        }

        return $this->search($query, $options);
    }

    // Search in Multiple Languages
    public function searchInMultipleLanguages($query, $languages = [], $options = [])
    {
        $results = [];
        foreach ($languages as $language) {
            $this->setLanguage($language);
            $results[$language] = $this->search($query, $options);
        }
        return $results;
    }

    // Custom Query Builder
    public function searchWithBuilder(VespaQueryBuilder $builder, $options = [])
    {
        $query = $builder->getQuery();
        return $this->search($query, $options);
    }

    // Audit Logging
    protected function auditLog($operation, $details)
    {
        Log::channel('audit')->info("Vespa operation: {$operation}", [
            'user_id' => auth()->id(),
            'details' => $details,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'session_id' => session()->getId(),
        ]);
    }

    // Custom Error Handling
    protected function handleErrors($response)
    {
        if (!$response->successful()) {
            $errorDetails = [
                'status' => $response->status(),
                'body' => $response->body(),
            ];
            switch ($response->status()) {
                case 400:
                    $this->reportError('Bad Request', $errorDetails);
                    throw new \Exception("Bad Request: {$response->body()}");
                case 401:
                    $this->reportError('Unauthorized', $errorDetails);
                    throw new \Exception("Unauthorized: Please check your API key.");
                case 404:
                    $this->reportError('Not Found', $errorDetails);
                    throw new \Exception("Not Found: The requested resource could not be found.");
                case 500:
                    $this->reportError('Server Error', $errorDetails);
                    throw new \Exception("Internal Server Error: Please try again later.");
                default:
                    $this->reportError('Unknown Error', $errorDetails);
                    throw new \Exception("An error occurred: {$response->body()}");
            }
        }
        return $response;
    }

    // Error Reporting
    protected function reportError($errorType, $details)
    {
        Log::channel('vespa_errors')->error("Vespa Error: {$errorType}", $details);
        // Optionally, integrate with an alerting system (e.g., email, Slack, etc.)
    }

    // Method to handle search request with error handling
    public function searchWithErrorHandling($query, $options = [])
    {
        $this->rateLimitCheck();
        $this->throttleCheck();

        $query = $this->executePlugins($query);

        $body = array_merge([
            'yql' => "select * from sources * where userInput('$query');",
            'ranking.profile' => $options['ranking.profile'] ?? 'default',
            'language' => $this->language,
        ], $options);

        $start = microtime(true);
        $response = $this->performRequestWithRetry('post', "{$this->vespaUrl}/search/", $body);

        $this->handleErrors($response);  // Custom error handling

        $executionTime = microtime(true) - $start;
        Log::info("Vespa search executed in {$executionTime} seconds", [
            'query' => $query,
            'response' => $response->json(),
        ]);

        $this->auditLog('search', [
            'query' => $query,
            'execution_time' => $executionTime,
            'response' => $response->json(),
        ]);

        Cache::decrement($this->throttleCacheKey);
        return $response->json();
    }

    // Health Check for Vespa Nodes
    public function healthCheck($nodes)
    {
        $healthStatus = [];
        foreach ($nodes as $node) {
            try {
                $response = Http::get("{$node}/ApplicationStatus");
                $healthStatus[$node] = $response->successful() ? 'Healthy' : 'Unhealthy';
            } catch (\Exception $e) {
                $healthStatus[$node] = 'Unreachable';
                Log::warning("Node {$node} unreachable: {$e->getMessage()}");
            }
        }
        return $healthStatus;
    }
}
