<?php

namespace YourVendor\Vespa\Tests;

use YourVendor\Vespa\VespaClient;
use YourVendor\Vespa\VespaQueryBuilder;
use YourVendor\Vespa\Plugins\ExamplePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VespaClientTest extends TestCase
{
    use RefreshDatabase;

    protected $vespaClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vespaClient = $this->app->make(VespaClient::class);
    }

    public function testBasicSearch()
    {
        $response = $this->vespaClient->search('test query');

        $this->assertNotNull($response);
        $this->assertIsArray($response);
    }

    public function testQueryBuilderSearch()
    {
        $builder = new VespaQueryBuilder();
        $query = $builder->select(['title', 'description'])
            ->from('documents')
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->getQuery();

        $response = $this->vespaClient->searchWithBuilder($builder);

        $this->assertNotNull($response);
        $this->assertIsArray($response);
    }

    public function testCachedSearch()
    {
        $response = $this->vespaClient->cachedSearch('test query', [], 600);

        $this->assertNotNull($response);
        $this->assertIsArray($response);

        // Check that the response is cached
        $cachedResponse = $this->vespaClient->cachedSearch('test query', [], 600);
        $this->assertEquals($response, $cachedResponse);
    }

    public function testPluginIntegration()
    {
        $plugin = new ExamplePlugin(['append' => 'AND status:published']);
        $this->vespaClient->registerPlugin($plugin);

        $response = $this->vespaClient->search('test query');

        $this->assertNotNull($response);
        $this->assertIsArray($response);
    }

    public function testRateLimitExceeded()
    {
        $this->expectException(\Exception::class);

        config(['vespa.rate_limit' => 1]);

        $this->vespaClient->search('test query');
        $this->vespaClient->search('another query'); // This should trigger the rate limit exception
    }

    public function testThrottlingExceeded()
    {
        $this->expectException(\Exception::class);

        config(['vespa.throttle_limit' => 1]);

        $this->vespaClient->search('test query');
        $this->vespaClient->search('another query'); // This should trigger the throttling exception
    }
}
