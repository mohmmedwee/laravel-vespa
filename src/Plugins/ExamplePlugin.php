<?php

namespace YourVendor\Vespa\Plugins;

use Illuminate\Support\Facades\Log;

class ExamplePlugin
{
    protected $config;

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    public function handle($query)
    {
        // Log the original query before modification
        Log::info("Original query before plugin modification", ['query' => $query]);

        // Modify the query based on configuration or conditions
        if ($this->shouldModifyQuery($query)) {
            $query = $this->modifyQuery($query);
        }

        // Log the modified query after plugin modification
        Log::info("Modified query after plugin modification", ['query' => $query]);

        return $query;
    }

    protected function shouldModifyQuery($query)
    {
        // Example condition: only modify if a specific keyword is present
        return isset($this->config['keyword']) && strpos($query, $this->config['keyword']) !== false;
    }

    protected function modifyQuery($query)
    {
        // Modify the query as needed
        // Example: Append a condition based on the plugin's configuration
        if (isset($this->config['append'])) {
            $query .= ' ' . $this->config['append'];
        }

        return $query;
    }
}
