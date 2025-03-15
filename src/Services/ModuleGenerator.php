<?php

namespace tmgomas\ErdToModules\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleGenerator
{
    /**
     * The parser instance.
     *
     * @var \YourName\ErdToModules\Services\ErdParser
     */
    protected $parser;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Module directory structure.
     * 
     * @var array
     */
    protected $moduleStructure = [];

    /**
     * Create a new generator instance.
     *
     * @param \YourName\ErdToModules\Services\ErdParser $parser
     * @param array $moduleStructure
     * @param \Illuminate\Filesystem\Filesystem $files
     * @return void
     */
    public function __construct(ErdParser $parser, array $moduleStructure = [], Filesystem $files = null)
    {
        $this->parser = $parser;
        $this->moduleStructure = $moduleStructure ?: config('erd-to-modules.paths', []);
        $this->files = $files ?: new Filesystem;
    }

    /**
     * Generate module structure for an entity
     *
     * @param string $entityName
     * @param array $attributes
     * @param bool $force
     * @return void
     */
    public function generateForEntity(string $entityName, array $attributes, bool $force = false): void
    {
        $singularName = Str::singular($entityName);
        $pluralName = Str::plural($entityName);

        // Create directory structure for the entity
        $this->createDirectoryStructure($singularName, $pluralName);

        // Check which files to generate from config
        $generateConfig = config('erd-to-modules.generate', []);

        // Generate files based on config
        if ($generateConfig['model'] ?? true) {
            $this->generateModel($singularName, $attributes, $force);
        }

        if ($generateConfig['migration'] ?? true) {
            $this->generateMigration($singularName, $pluralName, $attributes, $force);
        }

        if ($generateConfig['controller'] ?? true) {
            $this->generateController($singularName, $pluralName, $force);
        }

        if ($generateConfig['views'] ?? true) {
            $this->generateViews($singularName, $pluralName, $force);
        }

        // Continue with other file generations...
    }

    /**
     * Create the directory structure for the entity
     *
     * @param string $singularName
     * @param string $pluralName
     * @return void
     */
    protected function createDirectoryStructure(string $singularName, string $pluralName): void
    {
        $basePath = base_path();

        foreach ($this->moduleStructure as $module => $path) {
            $fullPath = $basePath . '/' . $path;

            // Create module-specific subdirectories
            switch ($module) {
                case 'Views':
                    $viewPath = $fullPath . strtolower($pluralName);
                    if (!$this->files->isDirectory($viewPath)) {
                        $this->files->makeDirectory($viewPath, 0755, true, true);
                    }
                    break;

                case 'Routes':
                    // Routes directory already exists in Laravel
                    break;

                default:
                    if (!$this->files->isDirectory($fullPath)) {
                        $this->files->makeDirectory($fullPath, 0755, true, true);
                    }
                    break;
            }
        }
    }

    /**
     * Generate model class
     *
     * @param string $singularName
     * @param array $attributes
     * @param bool $force
     * @return void
     */
    protected function generateModel(string $singularName, array $attributes, bool $force = false): void
    {
        $modelPath = base_path($this->moduleStructure['Models'] . $singularName . '.php');

        if ($this->files->exists($modelPath) && !$force) {
            return;
        }

        $fillableAttributes = array_map(function ($attr) {
            return "'" . $attr['name'] . "'";
        }, $attributes);

        $fillableStr = implode(', ', $fillableAttributes);

        $useCustomStubs = config('erd-to-modules.use_custom_stubs', false);
        $stubsPath = config('erd-to-modules.custom_stubs_path', resource_path('stubs/vendor/erd-to-modules'));

        $modelContent = '';

        if ($useCustomStubs && $this->files->exists($stubsPath . '/model.stub')) {
            $modelContent = $this->files->get($stubsPath . '/model.stub');
            $modelContent = str_replace(
                ['{className}', '{fillable}'],
                [$singularName, $fillableStr],
                $modelContent
            );
        } else {
            $modelContent = <<<EOT
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {$singularName} extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected \$fillable = [{$fillableStr}];
}
EOT;
        }

        $this->files->put($modelPath, $modelContent);
    }

    // Additional methods for generating other files...
}
