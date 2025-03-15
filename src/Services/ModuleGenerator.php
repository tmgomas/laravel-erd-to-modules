<?php

namespace tmgomas\ErdToModules\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleGenerator
{
    /**
     * @var array
     */
    protected $moduleStructure;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var \tmgomas\ErdToModules\Services\ErdParser
     */
    protected $parser;

    /**
     * @var array
     */
    protected $parsedData;

    /**
     * ModuleGenerator constructor.
     *
     * @param \tmgomas\ErdToModules\Services\ErdParser $parser
     * @param array $moduleStructure
     * @param \Illuminate\Filesystem\Filesystem $files
     */
    public function __construct(ErdParser $parser, array $moduleStructure, Filesystem $files)
    {
        $this->parser = $parser;
        $this->moduleStructure = $moduleStructure;
        $this->files = $files;
    }

    /**
     * Parse the ERD content
     *
     * @param string $erdContent
     * @return array
     */
    public function parseErd(string $erdContent): array
    {
        $this->parsedData = $this->parser->parse($erdContent);
        return $this->parsedData;
    }

    /**
     * Generate all module files for all entities
     *
     * @param string $erdContent
     * @param bool $force
     * @return array
     */
    public function generateFromErd(string $erdContent, bool $force = false): array
    {
        $parsedData = $this->parseErd($erdContent);
        $generatedFiles = [];

        foreach ($parsedData['entities'] as $entityName => $entityData) {
            $generatedEntityFiles = $this->generateForEntity($entityName, $entityData['attributes'], $force);
            $generatedFiles = array_merge($generatedFiles, $generatedEntityFiles);
        }

        return $generatedFiles;
    }

    /**
     * Generate all module files for an entity
     *
     * @param string $entityName
     * @param array $attributes
     * @param bool $force
     * @return array
     */
    public function generateForEntity(string $entityName, array $attributes, bool $force = false): array
    {
        $singularName = Str::studly($entityName);
        $pluralName = Str::plural($singularName);
        $generatedFiles = [];

        // Generate model
        if (config('erd-to-modules.generate.model', true)) {
            $modelPath = $this->generateModel($singularName, $attributes, $force);
            $generatedFiles[] = $modelPath;
        }

        // Generate migration
        if (config('erd-to-modules.generate.migration', true)) {
            $migrationPath = $this->generateMigration($singularName, $pluralName, $attributes, $force);
            $generatedFiles[] = $migrationPath;
        }

        // Generate controller
        if (config('erd-to-modules.generate.controller', true)) {
            $controllerPath = $this->generateController($singularName, $pluralName, $force);
            $generatedFiles[] = $controllerPath;
        }

        // Generate requests
        if (config('erd-to-modules.generate.requests', true)) {
            $requestPaths = $this->generateRequests($singularName, $attributes, $force);
            $generatedFiles = array_merge($generatedFiles, $requestPaths);
        }

        // Generate factory
        if (config('erd-to-modules.generate.factory', true)) {
            $factoryPath = $this->generateFactory($singularName, $attributes, $force);
            $generatedFiles[] = $factoryPath;
        }

        // Generate views
        if (config('erd-to-modules.generate.views', true)) {
            $viewPaths = $this->generateViews($singularName, $pluralName, $attributes, $force);
            $generatedFiles = array_merge($generatedFiles, $viewPaths);
        }

        // Generate routes
        if (config('erd-to-modules.generate.routes', true)) {
            $routesPath = $this->generateRoutes($singularName, $pluralName, $force);
            $generatedFiles[] = $routesPath;
        }

        // Generate repository
        if (config('erd-to-modules.generate.repository', true)) {
            $repositoryPath = $this->generateRepository($singularName, $force);
            $generatedFiles[] = $repositoryPath;
        }

        // Generate service
        if (config('erd-to-modules.generate.service', true)) {
            $servicePath = $this->generateService($singularName, $force);
            $generatedFiles[] = $servicePath;
        }

        // Generate resource/transformer
        if (config('erd-to-modules.generate.resource', true)) {
            $resourcePath = $this->generateResource($singularName, $attributes, $force);
            $generatedFiles[] = $resourcePath;
        }

        // Generate seeder
        if (config('erd-to-modules.generate.seeder', true)) {
            $seederPath = $this->generateSeeder($singularName, $force);
            $generatedFiles[] = $seederPath;
        }

        // Generate test
        if (config('erd-to-modules.generate.test', true)) {
            $testPath = $this->generateTest($singularName, $force);
            $generatedFiles[] = $testPath;
        }

        return $generatedFiles;
    }
    /**
     * Generate model file
     *
     * @param string $singularName
     * @param array $attributes
     * @param bool $force
     * @return string
     */
    protected function generateModel(string $singularName, array $attributes, bool $force = false): string
    {
        $modelPath = base_path($this->moduleStructure['Models'] . $singularName . '.php');
        $modelDir = dirname($modelPath);

        if (!$this->files->isDirectory($modelDir)) {
            $this->files->makeDirectory($modelDir, 0755, true);
        }

        if ($this->files->exists($modelPath) && !$force) {
            return $modelPath;
        }

        // Prepare fillable attributes
        $fillable = array_map(function ($attribute) {
            return "'" . $attribute['name'] . "'";
        }, array_filter($attributes, function ($attribute) {
            // Usually we'd exclude id and timestamps from fillable
            return !in_array($attribute['name'], ['id', 'created_at', 'updated_at']) &&
                !($attribute['isPrimary'] ?? false);
        }));

        $fillableString = implode(', ', $fillable);

        // Generate relationships
        $relationships = $this->generateRelationshipMethods($singularName);

        // Check if we have a custom stub
        $stubPath = config('erd-to-modules.use_custom_stubs', false)
            ? config('erd-to-modules.custom_stubs_path') . '/model.stub'
            : __DIR__ . '/../../resources/stubs/model.stub';

        if ($this->files->exists($stubPath)) {
            $stub = $this->files->get($stubPath);
            $modelContent = str_replace(
                ['{className}', '{fillable}', '{relationships}'],
                [$singularName, $fillableString, $relationships],
                $stub
            );
        } else {
            // Default model content
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
    protected \$fillable = [{$fillableString}];

    $relationships
}
EOT;
        }

        $this->files->put($modelPath, $modelContent);
        return $modelPath;
    }

    /**
     * Generate relationship methods for a model
     *
     * @param string $modelName
     * @return string
     */
    protected function generateRelationshipMethods(string $modelName): string
    {
        if (!isset($this->parsedData['relationships'])) {
            return '';
        }

        $relationships = '';

        foreach ($this->parsedData['relationships'] as $relation) {
            if ($relation['from'] === $modelName) {
                $targetModel = Str::studly($relation['to']);
                $targetVariable = Str::camel($relation['to']);

                switch ($relation['type']) {
                    case 'hasMany':
                        $targetVariable = Str::plural($targetVariable);
                        $relationships .= $this->generateHasManyMethod($targetModel, $targetVariable);
                        break;
                    case 'hasOne':
                        $relationships .= $this->generateHasOneMethod($targetModel, $targetVariable);
                        break;
                    case 'belongsToMany':
                        $targetVariable = Str::plural($targetVariable);
                        $relationships .= $this->generateBelongsToManyMethod($targetModel, $targetVariable);
                        break;
                }
            } elseif ($relation['to'] === $modelName) {
                $sourceModel = Str::studly($relation['from']);
                $sourceVariable = Str::camel($relation['from']);

                if ($relation['type'] === 'hasMany' || $relation['type'] === 'hasOne') {
                    $relationships .= $this->generateBelongsToMethod($sourceModel, $sourceVariable);
                } elseif ($relation['type'] === 'belongsToMany') {
                    $sourceVariable = Str::plural($sourceVariable);
                    $relationships .= $this->generateBelongsToManyMethod($sourceModel, $sourceVariable);
                }
            }
        }

        return $relationships;
    }

    /**
     * Generate hasMany relationship method
     *
     * @param string $targetModel
     * @param string $targetVariable
     * @return string
     */
    protected function generateHasManyMethod(string $targetModel, string $targetVariable): string
    {
        return <<<EOT
    
    /**
     * Get the {$targetVariable} for the {$targetModel}.
     */
    public function {$targetVariable}()
    {
        return \$this->hasMany({$targetModel}::class);
    }

EOT;
    }

    /**
     * Generate hasOne relationship method
     *
     * @param string $targetModel
     * @param string $targetVariable
     * @return string
     */
    protected function generateHasOneMethod(string $targetModel, string $targetVariable): string
    {
        return <<<EOT
    
    /**
     * Get the {$targetVariable} for the {$targetModel}.
     */
    public function {$targetVariable}()
    {
        return \$this->hasOne({$targetModel}::class);
    }

EOT;
    }

    /**
     * Generate belongsTo relationship method
     *
     * @param string $targetModel
     * @param string $targetVariable
     * @return string
     */
    protected function generateBelongsToMethod(string $targetModel, string $targetVariable): string
    {
        return <<<EOT
    
    /**
     * Get the {$targetVariable} that owns the {$targetModel}.
     */
    public function {$targetVariable}()
    {
        return \$this->belongsTo({$targetModel}::class);
    }

EOT;
    }

    /**
     * Generate belongsToMany relationship method
     *
     * @param string $targetModel
     * @param string $targetVariable
     * @return string
     */
    protected function generateBelongsToManyMethod(string $targetModel, string $targetVariable): string
    {
        // Try to determine the pivot table name based on convention
        $pivotTable = $this->getPivotTableName(Str::singular(Str::snake(class_basename($targetModel))), Str::singular(Str::snake($targetModel)));

        return <<<EOT
    
    /**
     * The {$targetVariable} that belong to this model.
     */
    public function {$targetVariable}()
    {
        return \$this->belongsToMany({$targetModel}::class, '{$pivotTable}');
    }

EOT;
    }

    /**
     * Get the pivot table name based on two model names
     *
     * @param string $model1
     * @param string $model2
     * @return string
     */
    protected function getPivotTableName(string $model1, string $model2): string
    {
        $models = [$model1, $model2];
        sort($models);
        return implode('_', $models);
    }

    /**
     * Generate migration file
     *
     * @param string $singularName
     * @param string $pluralName
     * @param array $attributes
     * @param bool $force
     * @return string
     */
    protected function generateMigration(string $singularName, string $pluralName, array $attributes, bool $force = false): string
    {
        $tableName = Str::snake($pluralName);
        $migrationName = "create_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $migrationPath = database_path("migrations/{$timestamp}_{$migrationName}.php");
        $migrationsDir = dirname($migrationPath);

        if (!$this->files->isDirectory($migrationsDir)) {
            $this->files->makeDirectory($migrationsDir, 0755, true);
        }

        if ($this->files->exists($migrationPath) && !$force) {
            return $migrationPath;
        }

        $schema = '';
        $foreignKeys = '';

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $type = $this->mapAttributeTypeToColumnType($attribute['type']);

            if ($name === 'id' && ($attribute['isPrimary'] ?? false)) {
                $schema .= "\$table->id();\n            ";
            } elseif (Str::endsWith($name, '_id') && ($attribute['isForeign'] ?? false)) {
                $referencedTable = Str::plural(Str::beforeLast($name, '_id'));
                $schema .= "\$table->{$type}('{$name}');\n            ";
                $foreignKeys .= "\$table->foreign('{$name}')->references('id')->on('{$referencedTable}')->onDelete('cascade');\n            ";
            } elseif (in_array($name, ['created_at', 'updated_at'])) {
                continue; // Will be handled by the timestamps() method
            } else {
                $schema .= "\$table->{$type}('{$name}')";

                // Add nullable if it's not a required field
                if (
                    strpos($name, 'email_verified_at') !== false ||
                    strpos($name, 'remember_token') !== false ||
                    strpos($name, 'published_at') !== false
                ) {
                    $schema .= "->nullable()";
                }

                $schema .= ";\n            ";
            }
        }

        // Check if we need to add timestamps
        $needsTimestamps = false;
        foreach ($attributes as $attribute) {
            if (in_array($attribute['name'], ['created_at', 'updated_at'])) {
                $needsTimestamps = true;
                break;
            }
        }

        if ($needsTimestamps) {
            $schema .= "\$table->timestamps();\n            ";
        }

        $migrationContent = <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            {$schema}{$foreignKeys}
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('{$tableName}');
    }
};
EOT;

        $this->files->put($migrationPath, $migrationContent);
        return $migrationPath;
    }

    /**
     * Map attribute type to column type
     *
     * @param string $attributeType
     * @return string
     */
    protected function mapAttributeTypeToColumnType(string $attributeType): string
    {
        $map = [
            'int' => 'integer',
            'integer' => 'integer',
            'string' => 'string',
            'text' => 'text',
            'boolean' => 'boolean',
            'timestamp' => 'timestamp',
            'date' => 'date',
            'datetime' => 'dateTime',
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'json' => 'json',
        ];

        return $map[$attributeType] ?? 'string';
    }

    /**
     * Generate controller
     *
     * @param string $singularName
     * @param string $pluralName
     * @param bool $force
     * @return string
     */
    protected function generateController(string $singularName, string $pluralName, bool $force = false): string
    {
        $controllerPath = base_path($this->moduleStructure['Controllers'] . $singularName . 'Controller.php');
        $controllerDir = dirname($controllerPath);

        if (!$this->files->isDirectory($controllerDir)) {
            $this->files->makeDirectory($controllerDir, 0755, true);
        }

        if ($this->files->exists($controllerPath) && !$force) {
            return $controllerPath;
        }

        $controllerContent = <<<EOT
<?php

namespace App\Http\Controllers;

use App\Models\\{$singularName};
use App\Http\Requests\\{$singularName}\\Store{$singularName}Request;
use App\Http\Requests\\{$singularName}\\Update{$singularName}Request;
use Illuminate\Http\Request;

class {$singularName}Controller extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        \${$pluralName} = {$singularName}::all();
        return view('{$pluralName}.index', compact('{$pluralName}'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('{$pluralName}.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\\{$singularName}\\Store{$singularName}Request  \$request
     * @return \Illuminate\Http\Response
     */
    public function store(Store{$singularName}Request \$request)
    {
        {$singularName}::create(\$request->validated());

        return redirect()->route('{$pluralName}.index')
            ->with('success', '{$singularName} created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\\{$singularName}  \${$singularName}
     * @return \Illuminate\Http\Response
     */
    public function show({$singularName} \${$singularName})
    {
        return view('{$pluralName}.show', compact('{$singularName}'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\\{$singularName}  \${$singularName}
     * @return \Illuminate\Http\Response
     */
    public function edit({$singularName} \${$singularName})
    {
        return view('{$pluralName}.edit', compact('{$singularName}'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\\{$singularName}\\Update{$singularName}Request  \$request
     * @param  \App\Models\\{$singularName}  \${$singularName}
     * @return \Illuminate\Http\Response
     */
    public function update(Update{$singularName}Request \$request, {$singularName} \${$singularName})
    {
        \${$singularName}->update(\$request->validated());

        return redirect()->route('{$pluralName}.index')
            ->with('success', '{$singularName} updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\\{$singularName}  \${$singularName}
     * @return \Illuminate\Http\Response
     */
    public function destroy({$singularName} \${$singularName})
    {
        \${$singularName}->delete();

        return redirect()->route('{$pluralName}.index')
            ->with('success', '{$singularName} deleted successfully.');
    }
}
EOT;

        $this->files->put($controllerPath, $controllerContent);
        return $controllerPath;
    }

    /**
     * Generate form request classes
     *
     * @param string $singularName
     * @param array $attributes
     * @param bool $force
     * @return array
     */
    protected function generateRequests(string $singularName, array $attributes, bool $force = false): array
    {
        $requestPaths = [];
        $requestsNamespace = "App\Http\Requests\\{$singularName}";
        $requestsDir = base_path($this->moduleStructure['Requests'] . $singularName);

        if (!$this->files->isDirectory($requestsDir)) {
            $this->files->makeDirectory($requestsDir, 0755, true);
        }

        // Generate Store Request
        $storeRequestPath = $requestsDir . "/Store{$singularName}Request.php";
        if (!$this->files->exists($storeRequestPath) || $force) {
            $validationRules = $this->generateValidationRules($attributes);
            $storeRequestContent = <<<EOT
<?php

namespace {$requestsNamespace};

use Illuminate\Foundation\Http\FormRequest;

class Store{$singularName}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            {$validationRules}
        ];
    }
}
EOT;
            $this->files->put($storeRequestPath, $storeRequestContent);
            $requestPaths[] = $storeRequestPath;
        }

        // Generate Update Request
        $updateRequestPath = $requestsDir . "/Update{$singularName}Request.php";
        if (!$this->files->exists($updateRequestPath) || $force) {
            $validationRules = $this->generateValidationRules($attributes, true);
            $updateRequestContent = <<<EOT
<?php

namespace {$requestsNamespace};

use Illuminate\Foundation\Http\FormRequest;

class Update{$singularName}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            {$validationRules}
        ];
    }
}
EOT;
            $this->files->put($updateRequestPath, $updateRequestContent);
            $requestPaths[] = $updateRequestPath;
        }

        return $requestPaths;
    }

    /**
     * Generate validation rules for form requests
     *
     * @param array $attributes
     * @param bool $isUpdate
     * @return string
     */
    protected function generateValidationRules(array $attributes, bool $isUpdate = false): string
    {
        $rules = [];

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $type = $attribute['type'];

            // Skip primary key and timestamps
            if (in_array($name, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $rule = '';

            // Add required rule for most fields unless it's nullable
            if (!in_array($name, ['email_verified_at', 'remember_token', 'published_at']) && !$isUpdate) {
                $rule .= 'required|';
            } elseif ($isUpdate) {
                $rule .= 'sometimes|';
            } else {
                $rule .= 'nullable|';
            }

            // Add type-specific validation
            switch ($type) {
                case 'string':
                    $rule .= 'string|max:255';
                    break;
                case 'text':
                    $rule .= 'string';
                    break;
                case 'int':
                case 'integer':
                    $rule .= 'integer';
                    break;
                case 'boolean':
                    $rule .= 'boolean';
                    break;
                case 'timestamp':
                case 'date':
                case 'datetime':
                    $rule .= 'date';
                    break;
                case 'float':
                case 'double':
                case 'decimal':
                    $rule .= 'numeric';
                    break;
                default:
                    $rule .= 'string';
            }

            // Add specific rules for common fields
            if ($name === 'email') {
                $rule .= '|email';
            } elseif ($name === 'password') {
                $rule .= '|min:8';
            } elseif (Str::endsWith($name, '_id')) {
                $rule .= '|exists:' . Str::plural(Str::beforeLast($name, '_id')) . ',id';
            }

            $rules[] = "'{$name}' => '{$rule}'";
        }

        return implode(",\n            ", $rules);
    }

    /**
     * Generate model factory
     *
     * @param string $singularName
     * @param array $attributes
     * @param bool $force
     * @return string
     */
    protected function generateFactory(string $singularName, array $attributes, bool $force = false): string
    {
        $factoryPath = base_path($this->moduleStructure['Factories'] . $singularName . 'Factory.php');
        $factoryDir = dirname($factoryPath);

        if (!$this->files->isDirectory($factoryDir)) {
            $this->files->makeDirectory($factoryDir, 0755, true);
        }

        if ($this->files->exists($factoryPath) && !$force) {
            return $factoryPath;
        }

        $factoryDefaults = '';
        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $type = $attribute['type'];

            // Skip primary key and timestamps
            if (in_array($name, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $factoryDefault = $this->getFactoryDefaultForType($name, $type);
            if ($factoryDefault) {
                $factoryDefaults .= "            '{$name}' => {$factoryDefault},\n";
            }
        }

        $factoryContent = <<<EOT
<?php

namespace Database\Factories;

use App\Models\\{$singularName};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class {$singularName}Factory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected \$model = {$singularName}::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
{$factoryDefaults}
        ];
    }
}
EOT;

        $this->files->put($factoryPath, $factoryContent);
        return $factoryPath;
    }

    /**
     * Get factory default value based on attribute type
     *
     * @param string $name
     * @param string $type
     * @return string|null
     */
    protected function getFactoryDefaultForType(string $name, string $type): ?string
    {
        // Handle common field names
        if ($name === 'name') {
            return "\$this->faker->name()";
        } elseif ($name === 'email') {
            return "\$this->faker->unique()->safeEmail()";
        } elseif ($name === 'email_verified_at') {
            return "now()";
        } elseif ($name === 'password') {
            return "bcrypt('password')";
        } elseif ($name === 'remember_token') {
            return "Str::random(10)";
        } elseif ($name === 'title') {
            return "\$this->faker->sentence()";
        } elseif ($name === 'content' || $name === 'description') {
            return "\$this->faker->paragraphs(3, true)";
        } elseif ($name === 'slug') {
            return "\$this->faker->slug()";
        } elseif ($name === 'is_published' || Str::startsWith($name, 'is_')) {
            return "\$this->faker->boolean()";
        } elseif ($name === 'published_at') {
            return "\$this->faker->dateTimeBetween('-1 year', 'now')";
        } elseif (Str::endsWith($name, '_id')) {
            // For foreign keys, defer to the related factory
            $relatedModel = Str::studly(Str::singular(Str::beforeLast($name, '_id')));
            return "\App\Models\\{$relatedModel}::factory()";
        }

        // Handle by type
        switch ($type) {
            case 'int':
            case 'integer':
                return "\$this->faker->numberBetween(1, 1000)";
            case 'string':
                return "\$this->faker->word()";
            case 'text':
                return "\$this->faker->text()";
            case 'boolean':
                return "\$this->faker->boolean()";
            case 'date':
                return "\$this->faker->date()";
            case 'datetime':
            case 'timestamp':
                return "\$this->faker->dateTime()";
            case 'float':
            case 'double':
            case 'decimal':
                return "\$this->faker->randomFloat(2, 0, 1000)";
            default:
                return null;
        }
    }

    /**
     * Generate views
     *
     * @param string $singularName
     * @param string $pluralName
     * @param array $attributes
     * @param bool $force
     * @return array
     */
    protected function generateViews(string $singularName, string $pluralName, array $attributes, bool $force = false): array
    {
        $viewPaths = [];
        $viewsBasePath = strtolower($pluralName);
        $viewsDir = base_path($this->moduleStructure['Views'] . $viewsBasePath);

        if (!$this->files->isDirectory($viewsDir)) {
            $this->files->makeDirectory($viewsDir, 0755, true);
        }

        // Generate table headers and form fields based on attributes
        $tableHeaders = '';
        $tableRows = '';
        $formFields = '';
        $showFields = '';

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $type = $attribute['type'];

            // Skip primary key and timestamps for display
            if (in_array($name, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            // Table headers
            $label = Str::title(str_replace('_', ' ', $name));
            $tableHeaders .= "                <th>{$label}</th>\n";

            // Table rows
            $tableRows .= "                <td>{{ \${$singularName}->{$name} }}</td>\n";

            // Form fields
            $formFields .= $this->generateFormField($name, $label, $type, $singularName);
            // Show fields
            $showFields .= <<<EOT
            <div class="mb-4">
                <strong>{$label}:</strong> {{ \${$singularName}->{$name} }}
            </div>

EOT;
        }

        // Generate index view
        $indexViewPath = $viewsDir . '/index.blade.php';
        if (!$this->files->exists($indexViewPath) || $force) {
            $indexViewContent = <<<EOT
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between">
                        <h2>{$pluralName}</h2>
                        <a href="{{ route('{$viewsBasePath}.create') }}" class="btn btn-primary">Create New {$singularName}</a>
                    </div>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
{$tableHeaders}
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (\${$pluralName} as \${$singularName})
                                <tr>
                                    <td>{{ \${$singularName}->id }}</td>
{$tableRows}
                                    <td>
                                        <a href="{{ route('{$viewsBasePath}.show', \${$singularName}) }}" class="btn btn-info btn-sm">View</a>
                                        <a href="{{ route('{$viewsBasePath}.edit', \${$singularName}) }}" class="btn btn-primary btn-sm">Edit</a>
                                        <form action="{{ route('{$viewsBasePath}.destroy', \${$singularName}) }}" method="POST" style="display: inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
            $this->files->put($indexViewPath, $indexViewContent);
            $viewPaths[] = $indexViewPath;
        }

        // Generate create view
        $createViewPath = $viewsDir . '/create.blade.php';
        if (!$this->files->exists($createViewPath) || $force) {
            $createViewContent = <<<EOT
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2>Create {$singularName}</h2>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('{$viewsBasePath}.store') }}">
                        @csrf

{$formFields}

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    Create
                                </button>
                                <a href="{{ route('{$viewsBasePath}.index') }}" class="btn btn-secondary">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
            $this->files->put($createViewPath, $createViewContent);
            $viewPaths[] = $createViewPath;
        }

        // Generate edit view
        $editViewPath = $viewsDir . '/edit.blade.php';
        if (!$this->files->exists($editViewPath) || $force) {
            $editViewContent = <<<EOT
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2>Edit {$singularName}</h2>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('{$viewsBasePath}.update', \${$singularName}) }}">
                        @csrf
                        @method('PUT')

{$formFields}

                        <div class="form-group row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    Update
                                </button>
                                <a href="{{ route('{$viewsBasePath}.index') }}" class="btn btn-secondary">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
            $this->files->put($editViewPath, $editViewContent);
            $viewPaths[] = $editViewPath;
        }

        // Generate show view
        $showViewPath = $viewsDir . '/show.blade.php';
        if (!$this->files->exists($showViewPath) || $force) {
            $showViewContent = <<<EOT
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2>{$singularName} Details</h2>
                </div>

                <div class="card-body">
                    <div class="mb-4">
                        <strong>ID:</strong> {{ \${$singularName}->id }}
                    </div>

{$showFields}

                    <div class="mb-4">
                        <strong>Created At:</strong> {{ \${$singularName}->created_at }}
                    </div>

                    <div class="mb-4">
                        <strong>Updated At:</strong> {{ \${$singularName}->updated_at }}
                    </div>

                    <div class="d-flex mt-4">
                        <a href="{{ route('{$viewsBasePath}.edit', \${$singularName}) }}" class="btn btn-primary mr-2">Edit</a>
                        <a href="{{ route('{$viewsBasePath}.index') }}" class="btn btn-secondary">Back</a>
                        <form action="{{ route('{$viewsBasePath}.destroy', \${$singularName}) }}" method="POST" class="ml-auto">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOT;
            $this->files->put($showViewPath, $showViewContent);
            $viewPaths[] = $showViewPath;
        }

        return $viewPaths;
    }

    /**
     * Generate form field HTML based on attribute type
     *
     * @param string $name
     * @param string $label
     * @param string $type
     * @return string
     */
    protected function generateFormField(string $name, string $label, string $type, string $singularName = 'item'): string
    {
        $fieldValue = "{{ old('{$name}', \${$singularName}->{$name} ?? '') }}";

        switch ($type) {
            case 'text':
                return <<<EOT
                        <div class="form-group row mb-3">
                            <label for="{$name}" class="col-md-4 col-form-label text-md-right">{$label}</label>
                            <div class="col-md-6">
                                <textarea id="{$name}" class="form-control @error('{$name}') is-invalid @enderror" name="{$name}" rows="4">{$fieldValue}</textarea>
                                @error('{$name}')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ \$message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

EOT;

            case 'boolean':
                return <<<EOT
                        <div class="form-group row mb-3">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="{$name}" id="{$name}" {{ old('{$name}', \${$singularName}->{$name} ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="{$name}">
                                        {$label}
                                    </label>
                                </div>
                                @error('{$name}')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ \$message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

EOT;

            case 'date':
            case 'datetime':
            case 'timestamp':
                return <<<EOT
                        <div class="form-group row mb-3">
                            <label for="{$name}" class="col-md-4 col-form-label text-md-right">{$label}</label>
                            <div class="col-md-6">
                                <input id="{$name}" type="datetime-local" class="form-control @error('{$name}') is-invalid @enderror" name="{$name}" value="{{ old('{$name}', \${$singularName}->{$name} ? \${$singularName}->{$name}->format('Y-m-d\TH:i') : '') }}">
                                @error('{$name}')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ \$message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

EOT;

            default:
                // For most other types, a text input is sufficient
                $inputType = ($name === 'password') ? 'password' : 'text';
                return <<<EOT
                        <div class="form-group row mb-3">
                            <label for="{$name}" class="col-md-4 col-form-label text-md-right">{$label}</label>
                            <div class="col-md-6">
                                <input id="{$name}" type="{$inputType}" class="form-control @error('{$name}') is-invalid @enderror" name="{$name}" value="{$fieldValue}" autocomplete="{$name}">
                                @error('{$name}')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ \$message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

EOT;
        }
    }

    /**
     * Generate routes file
     *
     * @param string $singularName
     * @param string $pluralName
     * @param bool $force
     * @return string
     */
    protected function generateRoutes(string $singularName, string $pluralName, bool $force = false): string
    {
        $routesPath = base_path($this->moduleStructure['Routes'] . '/' . strtolower($pluralName) . '.php');
        $routesDir = dirname($routesPath);

        if (!$this->files->isDirectory($routesDir)) {
            $this->files->makeDirectory($routesDir, 0755, true);
        }

        if ($this->files->exists($routesPath) && !$force) {
            return $routesPath;
        }

        $routesContent = <<<EOT
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\\{$singularName}Controller;

/*
|--------------------------------------------------------------------------
| {$pluralName} Routes
|--------------------------------------------------------------------------
*/

Route::resource('{$pluralName}', {$singularName}Controller::class);

EOT;

        $this->files->put($routesPath, $routesContent);
        return $routesPath;
    }

    /**
     * Generate repository
     *
     * @param string $singularName
     * @param bool $force
     * @return string
     */
    protected function generateRepository(string $singularName, bool $force = false): string
    {
        $repositoryPath = base_path($this->moduleStructure['Repositories'] . $singularName . 'Repository.php');
        $repositoryDir = dirname($repositoryPath);
        $contractPath = base_path($this->moduleStructure['Contracts'] . $singularName . 'RepositoryInterface.php');
        $contractDir = dirname($contractPath);

        if (!$this->files->isDirectory($repositoryDir)) {
            $this->files->makeDirectory($repositoryDir, 0755, true);
        }

        if (!$this->files->isDirectory($contractDir)) {
            $this->files->makeDirectory($contractDir, 0755, true);
        }

        // First generate the interface
        if (!$this->files->exists($contractPath) || $force) {
            $interfaceContent = <<<EOT
<?php

namespace App\Contracts;

interface {$singularName}RepositoryInterface
{
    /**
     * Get all {$singularName} items
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all();

    /**
     * Get {$singularName} by id
     *
     * @param int \$id
     * @return \App\Models\\{$singularName}
     */
    public function find(\$id);

    /**
     * Create new {$singularName}
     *
     * @param array \$data
     * @return \App\Models\\{$singularName}
     */
    public function create(array \$data);

    /**
     * Update {$singularName}
     *
     * @param array \$data
     * @param int \$id
     * @return \App\Models\\{$singularName}
     */
    public function update(array \$data, \$id);

    /**
     * Delete {$singularName}
     *
     * @param int \$id
     * @return bool
     */
    public function delete(\$id);
}
EOT;
            $this->files->put($contractPath, $interfaceContent);
        }

        // Next generate the repository implementation
        if ($this->files->exists($repositoryPath) && !$force) {
            return $repositoryPath;
        }

        $repositoryContent = <<<EOT
<?php

namespace App\Repositories;

use App\Models\\{$singularName};
use App\Contracts\\{$singularName}RepositoryInterface;

class {$singularName}Repository implements {$singularName}RepositoryInterface
{
    /**
     * @var \App\Models\\{$singularName}
     */
    protected \$model;

    /**
     * {$singularName}Repository constructor.
     *
     * @param \App\Models\\{$singularName} \$model
     */
    public function __construct({$singularName} \$model)
    {
        \$this->model = \$model;
    }

    /**
     * Get all {$singularName} items
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        return \$this->model->all();
    }

    /**
     * Get {$singularName} by id
     *
     * @param int \$id
     * @return \App\Models\\{$singularName}
     */
    public function find(\$id)
    {
        return \$this->model->findOrFail(\$id);
    }

    /**
     * Create new {$singularName}
     *
     * @param array \$data
     * @return \App\Models\\{$singularName}
     */
    public function create(array \$data)
    {
        return \$this->model->create(\$data);
    }

    /**
     * Update {$singularName}
     *
     * @param array \$data
     * @param int \$id
     * @return \App\Models\\{$singularName}
     */
    public function update(array \$data, \$id)
    {
        \$record = \$this->find(\$id);
        \$record->update(\$data);
        return \$record;
    }

    /**
     * Delete {$singularName}
     *
     * @param int \$id
     * @return bool
     */
    public function delete(\$id)
    {
        return \$this->model->destroy(\$id);
    }
}
EOT;

        $this->files->put($repositoryPath, $repositoryContent);
        return $repositoryPath;
    }

    /**
     * Generate service
     *
     * @param string $singularName
     * @param bool $force
     * @return string
     */
    protected function generateService(string $singularName, bool $force = false): string
    {
        $servicePath = base_path($this->moduleStructure['Services'] . $singularName . 'Service.php');
        $serviceDir = dirname($servicePath);

        if (!$this->files->isDirectory($serviceDir)) {
            $this->files->makeDirectory($serviceDir, 0755, true);
        }

        if ($this->files->exists($servicePath) && !$force) {
            return $servicePath;
        }

        $serviceContent = <<<EOT
<?php

namespace App\Services;

use App\Contracts\\{$singularName}RepositoryInterface;

class {$singularName}Service
{
    /**
     * @var \App\Contracts\\{$singularName}RepositoryInterface
     */
    protected \$repository;

    /**
     * {$singularName}Service constructor.
     *
     * @param \App\Contracts\\{$singularName}RepositoryInterface \$repository
     */
    public function __construct({$singularName}RepositoryInterface \$repository)
    {
        \$this->repository = \$repository;
    }

    /**
     * Get all {$singularName} items
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        return \$this->repository->all();
    }

    /**
     * Get {$singularName} by id
     *
     * @param int \$id
     * @return \App\Models\\{$singularName}
     */
    public function findById(\$id)
    {
        return \$this->repository->find(\$id);
    }

    /**
     * Create new {$singularName}
     *
     * @param array \$data
     * @return \App\Models\\{$singularName}
     */
    public function create(array \$data)
    {
        return \$this->repository->create(\$data);
    }

    /**
     * Update {$singularName}
     *
     * @param array \$data
     * @param int \$id
     * @return \App\Models\\{$singularName}
     */
    public function update(array \$data, \$id)
    {
        return \$this->repository->update(\$data, \$id);
    }

    /**
     * Delete {$singularName}
     *
     * @param int \$id
     * @return bool
     */
    public function delete(\$id)
    {
        return \$this->repository->delete(\$id);
    }
}
EOT;

        $this->files->put($servicePath, $serviceContent);
        return $servicePath;
    }

    /**
     * Generate resource/transformer
     *
     * @param string $singularName
     * @param array $attributes
     * @param bool $force
     * @return string
     */
    protected function generateResource(string $singularName, array $attributes, bool $force = false): string
    {
        $resourcePath = base_path($this->moduleStructure['Resources'] . $singularName . 'Resource.php');
        $resourceDir = dirname($resourcePath);
        $collectionPath = base_path($this->moduleStructure['Resources'] . $singularName . 'Collection.php');

        if (!$this->files->isDirectory($resourceDir)) {
            $this->files->makeDirectory($resourceDir, 0755, true);
        }

        if ($this->files->exists($resourcePath) && !$force) {
            return $resourcePath;
        }

        // Build resource transformation
        $transformations = '';
        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            if (!in_array($name, ['id', 'created_at', 'updated_at'])) {
                $transformations .= "            '{$name}' => \$this->{$name},\n";
            }
        }

        // Include timestamps
        $transformations .= "            'created_at' => \$this->created_at,\n";
        $transformations .= "            'updated_at' => \$this->updated_at,\n";

        $resourceContent = <<<EOT
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {$singularName}Resource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  \$request
     * @return array
     */
    public function toArray(\$request)
    {
        return [
            'id' => \$this->id,
{$transformations}
        ];
    }
}
EOT;

        $this->files->put($resourcePath, $resourceContent);

        // Generate Collection resource too
        if (!$this->files->exists($collectionPath) || $force) {
            $collectionContent = <<<EOT
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class {$singularName}Collection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  \$request
     * @return array
     */
    public function toArray(\$request)
    {
        return [
            'data' => \$this->collection,
        ];
    }
}
EOT;
            $this->files->put($collectionPath, $collectionContent);
        }

        return $resourcePath;
    }
    /**
     * Generate seeder
     *
     * @param string $singularName
     * @param bool $force
     * @return string
     */
    protected function generateSeeder(string $singularName, bool $force = false): string
    {
        $seederPath = base_path($this->moduleStructure['Seeders'] . $singularName . 'Seeder.php');
        $seederDir = dirname($seederPath);

        if (!$this->files->isDirectory($seederDir)) {
            $this->files->makeDirectory($seederDir, 0755, true);
        }

        if ($this->files->exists($seederPath) && !$force) {
            return $seederPath;
        }

        $pluralName = Str::plural($singularName);
        $tableName = Str::snake($pluralName);

        $seederContent = <<<EOT
<?php

namespace Database\Seeders;

use App\Models\\{$singularName};
use Illuminate\Database\Seeder;

class {$singularName}Seeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate the table before seeding
        \DB::table('{$tableName}')->truncate();

        // Create sample data using the factory
        {$singularName}::factory()->count(10)->create();
    }
}
EOT;

        $this->files->put($seederPath, $seederContent);
        return $seederPath;
    }

    /**
     * Generate test
     *
     * @param string $singularName
     * @param bool $force
     * @return string
     */
    protected function generateTest(string $singularName, bool $force = false): string
    {
        $testPath = base_path($this->moduleStructure['Tests'] . 'Feature/' . $singularName . 'Test.php');
        $testDir = dirname($testPath);

        if (!$this->files->isDirectory($testDir)) {
            $this->files->makeDirectory($testDir, 0755, true);
        }

        if ($this->files->exists($testPath) && !$force) {
            return $testPath;
        }

        $pluralName = Str::plural($singularName);
        $variableName = Str::camel($singularName);

        $testContent = <<<EOT
<?php

namespace Tests\Feature;

use App\Models\\{$singularName};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class {$singularName}Test extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test if {$pluralName} index page is accessible
     *
     * @return void
     */
    public function test_{$pluralName}_index_page_is_accessible()
    {
        \$response = \$this->get(route('{$pluralName}.index'));
        \$response->assertStatus(200);
    }

    /**
     * Test if {$pluralName} create page is accessible
     *
     * @return void
     */
    public function test_{$pluralName}_create_page_is_accessible()
    {
        \$response = \$this->get(route('{$pluralName}.create'));
        \$response->assertStatus(200);
    }

    /**
     * Test {$singularName} can be created
     *
     * @return void
     */
    public function test_{$variableName}_can_be_created()
    {
        \${$variableName} = {$singularName}::factory()->make()->toArray();
        \$response = \$this->post(route('{$pluralName}.store'), \${$variableName});
        \$response->assertRedirect(route('{$pluralName}.index'));
        \$this->assertDatabaseHas('{$pluralName}', \${$variableName});
    }

    /**
     * Test {$singularName} can be viewed
     *
     * @return void
     */
    public function test_{$variableName}_can_be_viewed()
    {
        \${$variableName} = {$singularName}::factory()->create();
        \$response = \$this->get(route('{$pluralName}.show', \${$variableName}->id));
        \$response->assertStatus(200);
    }

    /**
     * Test {$singularName} can be edited
     *
     * @return void
     */
    public function test_{$variableName}_can_be_edited()
    {
        \${$variableName} = {$singularName}::factory()->create();
        \$response = \$this->get(route('{$pluralName}.edit', \${$variableName}->id));
        \$response->assertStatus(200);
    }

    /**
     * Test {$singularName} can be updated
     *
     * @return void
     */
    public function test_{$variableName}_can_be_updated()
    {
        \${$variableName} = {$singularName}::factory()->create();
        \$updatedData = {$singularName}::factory()->make()->toArray();
        
        \$response = \$this->put(route('{$pluralName}.update', \${$variableName}->id), \$updatedData);
        \$response->assertRedirect(route('{$pluralName}.index'));
        \$this->assertDatabaseHas('{$pluralName}', \$updatedData);
    }

    /**
     * Test {$singularName} can be deleted
     *
     * @return void
     */
    public function test_{$variableName}_can_be_deleted()
    {
        \${$variableName} = {$singularName}::factory()->create();
        \$response = \$this->delete(route('{$pluralName}.destroy', \${$variableName}->id));
        \$response->assertRedirect(route('{$pluralName}.index'));
        \$this->assertDatabaseMissing('{$pluralName}', ['id' => \${$variableName}->id]);
    }
}
EOT;

        $this->files->put($testPath, $testContent);
        return $testPath;
    }
}
