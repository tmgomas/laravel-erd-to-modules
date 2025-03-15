<?php


namespace tmgomas\ErdToModules\Facades;

use Illuminate\Support\Facades\Facade;

class ErdModules extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'module-generator';
    }
}
