<?php


namespace YourVendor\Vespa\Facades;

use Illuminate\Support\Facades\Facade;

class Vespa extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'YourVendor\Vespa\VespaClient';
    }
}
