<?php

namespace tmgomas\ErdToModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use YourName\ErdToModules\Exceptions\ErdParseException;
use YourName\ErdToModules\Services\ErdParser;
use YourName\ErdToModules\Services\ModuleGenerator;

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
     * @param \YourName\ErdToModules\Services\ErdParser $parser
     * @param \YourName\ErdToModules\Services\ModuleGenerator $generator
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

            $entities = $parser->parse($erdContent);

            $this->info('Found ' . count($entities) . ' entities in the ERD.');

            $force = $this->option('force');

            $this->info('Generating module structure...');
            $progressBar = $this->output->createProgressBar(count($entities));
            $progressBar->start();

            foreach ($entities as $entityName => $attributes) {
                $generator->generateForEntity($entityName, $attributes, $force);
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            $this->info('Module structure created successfully!');

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
