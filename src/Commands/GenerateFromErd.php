<?php

namespace tmgomas\ErdToModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use tmgomas\ErdToModules\Exceptions\ErdParseException;
use tmgomas\ErdToModules\Services\ErdParser;
use tmgomas\ErdToModules\Services\ModuleGenerator;

class GenerateFromErd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erd:generate 
                            {file : Path to the Mermaid ERD file} 
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Laravel module structure from a Mermaid ERD diagram';

    /**
     * Execute the console command.
     *
     * @param \tmgomas\ErdToModules\Services\ErdParser $parser
     * @param \tmgomas\ErdToModules\Services\ModuleGenerator $generator
     * @return int
     */
    public function handle(ErdParser $parser, ModuleGenerator $generator)
    {
        try {
            $filePath = $this->argument('file');

            if (!File::exists($filePath)) {
                $this->error("File does not exist: $filePath");
                return 1;
            }

            $this->info('Parsing ERD diagram...');
            $erdContent = File::get($filePath);

            // Parse the ERD content
            $parsedData = $generator->parseErd($erdContent);
            $entities = $parsedData['entities'];
            $relationships = $parsedData['relationships'] ?? [];

            $this->info('Found ' . count($entities) . ' entities in the ERD:');
            foreach ($entities as $entityName => $entityData) {
                $this->line(' - ' . $entityName . ' (' . count($entityData['attributes']) . ' attributes)');
            }

            $this->info('Found ' . count($relationships) . ' relationships in the ERD.');

            $force = $this->option('force');

            $this->info('Generating module structure...');
            $progressBar = $this->output->createProgressBar(count($entities));
            $progressBar->start();

            $generatedFiles = [];

            foreach ($entities as $entityName => $entityData) {
                $entityFiles = $generator->generateForEntity($entityName, $entityData['attributes'], $force);
                $generatedFiles = array_merge($generatedFiles, $entityFiles);
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info('Generated ' . count($generatedFiles) . ' files:');

            // Group files by type for better readability
            $filesByType = [];
            foreach ($generatedFiles as $file) {
                $relativePath = str_replace(base_path() . '/', '', $file);
                $type = explode('/', $relativePath)[0];
                $filesByType[$type][] = $relativePath;
            }

            foreach ($filesByType as $type => $files) {
                $this->line("<comment>$type:</comment>");
                foreach ($files as $file) {
                    $this->line("  - $file");
                }
            }

            $this->newLine();
            $this->info('Module structure created successfully!');
            $this->info('Remember to register the routes in your routes/web.php file:');
            $this->line("// Example:");
            $this->line("// require base_path('routes/users.php');");

            return 0;
        } catch (ErdParseException $e) {
            $this->error("Error parsing ERD: " . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }
}
