

## Laravel Vespa Client

This package provides a Laravel service provider and client for interacting with a Vespa search engine. It includes features like query building, rate limiting, caching, plugin integration, and more.

### Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
    - [Basic Search](#basic-search)
    - [Query Builder](#query-builder)
    - [Caching](#caching)
    - [Custom Plugin](#custom-plugin)
    - [Facades](#facades)
- [Testing](#testing)
- [License](#license)

### Installation

To get started with the Laravel Vespa Client, you'll need to follow these steps:

1. **Install the package via Composer:**

   ```bash
   composer require ostah/laravel-vespa
   ```

2. **Publish the configuration file:**

   This will allow you to customize the Vespa settings for your application.

   ```bash
   php artisan vendor:publish --tag=config --provider="YourVendor\Vespa\VespaServiceProvider"
   ```

3. **Add the necessary environment variables:**

   Add the following lines to your `.env` file:

   ```env
   VESPA_URL=http://localhost:8080
   VESPA_API_KEY=your-api-key-here
   VESPA_HTTP_TIMEOUT=30
   VESPA_RATE_LIMIT=100
   VESPA_THROTTLE_LIMIT=10
   VESPA_DEFAULT_LANGUAGE=en
   VESPA_LOG_CHANNEL=default
   VESPA_AUDIT_LOG_CHANNEL=audit
   VESPA_ERROR_LOG_CHANNEL=vespa_errors
   ```

### Configuration

The configuration for the Vespa client is stored in `config/vespa.php`. This file includes settings for the Vespa URL, API key, rate limiting, throttling, and logging. You can adjust these settings to match your application's needs.

Here's an example of what the `config/vespa.php` file looks like:

```php
return [

    'url' => env('VESPA_URL', 'http://localhost:8080'),

    'api_key' => env('VESPA_API_KEY', null),

    'timeout' => env('VESPA_HTTP_TIMEOUT', 30),

    'rate_limit' => env('VESPA_RATE_LIMIT', 100),

    'throttle_limit' => env('VESPA_THROTTLE_LIMIT', 10),

    'language' => env('VESPA_DEFAULT_LANGUAGE', 'en'),

    'log_channel' => env('VESPA_LOG_CHANNEL', 'default'),
    'audit_log_channel' => env('VESPA_AUDIT_LOG_CHANNEL', 'audit'),
    'error_log_channel' => env('VESPA_ERROR_LOG_CHANNEL', 'vespa_errors'),
];
```

### Usage

The Laravel Vespa Client provides various ways to interact with the Vespa search engine. Below are some examples of how to use the client in your application.

#### Basic Search

You can perform a basic search using the `VespaClient`:

```php
use YourVendor\Vespa\VespaClient;

$vespa = app(VespaClient::class);

$response = $vespa->search('your search query');

dd($response);
```

#### Query Builder

The `VespaQueryBuilder` class allows you to construct complex queries with a fluent API:

```php
use YourVendor\Vespa\VespaQueryBuilder;

$builder = new VespaQueryBuilder();
$query = $builder->select(['title', 'description'])
                 ->from('documents')
                 ->where('status', 'published')
                 ->orderBy('created_at', 'desc')
                 ->limit(10)
                 ->getQuery();

$response = $vespa->searchWithBuilder($builder);

dd($response);
```

#### Caching

To cache search results, you can use the `cachedSearch` method:

```php
$response = $vespa->cachedSearch('your search query', [], 600); // Cache for 10 minutes

dd($response);
```

#### Custom Plugin

You can create custom plugins to modify queries before they are sent to Vespa. Here’s how you can use a custom plugin:

```php
use YourVendor\Vespa\Plugins\ExamplePlugin;

$plugin = new ExamplePlugin(['append' => 'AND status:published']);
$vespa->registerPlugin($plugin);

$response = $vespa->search('your search query');

dd($response);
```

#### Facades

If you prefer using facades, you can access the `VespaClient` like this:

```php
use Vespa;

$response = Vespa::search('your search query');

dd($response);
```

### Testing

To ensure that the Vespa Client functions correctly, a test suite is provided. The tests cover basic search functionality, query building, caching, plugin integration, rate limiting, and throttling.

To run the tests, use PHPUnit:

```bash
vendor/bin/phpunit
```

Here’s an example of a basic test class:

```php
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
```

### License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

