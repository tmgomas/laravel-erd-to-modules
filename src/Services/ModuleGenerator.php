/**
 * Generate migration file
 *
 * @param string $singularName
 * @param string $pluralName
 * @param array $attributes
 * @param bool $force
 * @return void
 */
protected function generateMigration(string $singularName, string $pluralName, array $attributes, bool $force = false): void
{
    $tableName = Str::snake($pluralName);
    $migrationName = "create_{$tableName}_table";
    $migrationPath = database_path('migrations/' . date('Y_m_d_His') . "_$migrationName.php");

    if ($this->files->exists($migrationPath) && !$force) {
        return;
    }

    $schema = '';
    foreach ($attributes as $attribute) {
        $type = $this->mapAttributeTypeToColumnType($attribute['type']);
        $schema .= "\$table->{$type}('{$attribute['name']}');\n            ";
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
        Schema::create('$tableName', function (Blueprint \$table) {
            \$table->id();
            $schema
            \$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('$tableName');
    }
};
EOT;

    $this->files->put($migrationPath, $migrationContent);
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
 * @return void
 */
protected function generateController(string $singularName, string $pluralName, bool $force = false): void
{
    $controllerPath = base_path($this->moduleStructure['Controllers'] . $singularName . 'Controller.php');

    if ($this->files->exists($controllerPath) && !$force) {
        return;
    }

    $controllerContent = <<<EOT
<?php

namespace App\Http\Controllers;

use App\Models\\$singularName;
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
     * @param  \Illuminate\Http\Request  \$request
     * @return \Illuminate\Http\Response
     */
    public function store(Request \$request)
    {
        \$validated = \$request->validate([
            // Define validation rules here
        ]);

        {$singularName}::create(\$validated);

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
     * @param  \Illuminate\Http\Request  \$request
     * @param  \App\Models\\{$singularName}  \${$singularName}
     * @return \Illuminate\Http\Response
     */
    public function update(Request \$request, {$singularName} \${$singularName})
    {
        \$validated = \$request->validate([
            // Define validation rules here
        ]);

        \${$singularName}->update(\$validated);

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
}

/**
 * Generate views
 *
 * @param string $singularName
 * @param string $pluralName
 * @param bool $force
 * @return void
 */
protected function generateViews(string $singularName, string $pluralName, bool $force = false): void
{
    $viewsPath = base_path($this->moduleStructure['Views'] . strtolower($pluralName));

    if (!$this->files->isDirectory($viewsPath)) {
        $this->files->makeDirectory($viewsPath, 0755, true);
    }

    // Generate index view
    $indexViewPath = $viewsPath . '/index.blade.php';
    if (!$this->files->exists($indexViewPath) || $force) {
        $indexViewContent = <<<EOT
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{$pluralName}</h1>
    <a href="{{ route('{$pluralName}.create') }}" class="btn btn-primary mb-3">Create New {$singularName}</a>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <!-- Add columns here -->
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\${$pluralName} as \${$singularName})
            <tr>
                <td>{{ \${$singularName}->id }}</td>
                <!-- Add columns here -->
                <td>
                    <a href="{{ route('{$pluralName}.show', \${$singularName}) }}" class="btn btn-info btn-sm">View</a>
                    <a href="{{ route('{$pluralName}.edit', \${$singularName}) }}" class="btn btn-primary btn-sm">Edit</a>
                    <form action="{{ route('{$pluralName}.destroy', \${$singularName}) }}" method="POST" style="display: inline-block">
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
@endsection
EOT;
        $this->files->put($indexViewPath, $indexViewContent);
    }

    // Create other views (show, create, edit) similarly
}