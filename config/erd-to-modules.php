<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Structure Paths
    |--------------------------------------------------------------------------
    |
    | This value determines the path structure for each module type.
    | You can customize these paths according to your project structure.
    |
    */
    'paths' => [
        'Models' => 'app/Models/',
        'Views' => 'resources/views/',
        'Controllers' => 'app/Http/Controllers/',
        'Routes' => 'routes/',
        'Migrations' => 'database/migrations/',
        'Requests' => 'app/Http/Requests/',
        'Resources' => 'app/Http/Resources/',
        'Services' => 'app/Services/',
        'Repositories' => 'app/Repositories/',
        'Events' => 'app/Events/',
        'Listeners' => 'app/Listeners/',
        'Traits' => 'app/Traits/',
        'Middleware' => 'app/Http/Middleware/',
        'Jobs' => 'app/Jobs/',
        'Observers' => 'app/Observers/',
        'Providers' => 'app/Providers/',
        'Commands' => 'app/Console/Commands/',
        'Contracts' => 'app/Contracts/',
        'Factories' => 'database/factories/',
        'Seeders' => 'database/seeders/',
        'Mail' => 'app/Mail/',
        'Notifications' => 'app/Notifications/',
        'Policies' => 'app/Policies/',
        'Rules' => 'app/Rules/',
        'Exceptions' => 'app/Exceptions/',
        'Casts' => 'app/Casts/',
        'ViewComposers' => 'app/View/Composers/',
        'Tests' => 'tests/',
        'Translations' => 'lang/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Use Custom Stubs
    |--------------------------------------------------------------------------
    |
    | When true, the package will look for custom stubs in the stubs directory.
    |
    */
    'use_custom_stubs' => false,

    /*
    |--------------------------------------------------------------------------
    | Custom Stubs Path
    |--------------------------------------------------------------------------
    |
    | The path where custom stubs are located.
    |
    */
    'custom_stubs_path' => resource_path('stubs/vendor/erd-to-modules'),

    /*
    |--------------------------------------------------------------------------
    | Generate Related Files
    |--------------------------------------------------------------------------
    |
    | Determine which related files should be generated for each entity.
    |
    */
    'generate' => [
        'model' => true,
        'migration' => true,
        'controller' => true,
        'views' => true,
        'routes' => true,
        'requests' => true,
        'repository' => true,
        'service' => true,
        'factory' => true,
        'seeder' => true,
        'resource' => true,
        'test' => true,
    ],
];
