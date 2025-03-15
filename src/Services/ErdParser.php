<?php

namespace tmgomas\ErdToModules\Services;

use tmgomas\ErdToModules\Exceptions\ErdParseException;

class ErdParser
{
    /**
     * Parse Mermaid ERD content and extract entities with their attributes
     *
     * @param string $content
     * @return array
     * @throws ErdParseException
     */
    public function parse(string $content): array
    {
        $entities = [];
        $lines = explode("\n", $content);

        $currentEntity = null;
        $attributes = [];

        try {
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);

                // Skip empty lines and comments
                if (empty($line) || strpos($line, '%%') === 0) {
                    continue;
                }

                // Check if line defines an entity
                if (preg_match('/^(\w+)\s+{/', $line, $matches)) {
                    // If we were processing an entity, save it before starting a new one
                    if ($currentEntity !== null) {
                        $entities[$currentEntity] = $attributes;
                        $attributes = [];
                    }

                    $currentEntity = $matches[1];
                }
                // Check if line contains an attribute
                elseif ($currentEntity !== null && preg_match('/^\s*(\w+)\s+(\w+)/', $line, $matches)) {
                    $attributeName = $matches[1];
                    $attributeType = $matches[2];

                    $attributes[] = [
                        'name' => $attributeName,
                        'type' => $attributeType,
                    ];
                }
                // Check if line closes an entity definition
                elseif ($line === '}' && $currentEntity !== null) {
                    $entities[$currentEntity] = $attributes;
                    $currentEntity = null;
                    $attributes = [];
                }
            }

            // If we end with an unclosed entity
            if ($currentEntity !== null) {
                $entities[$currentEntity] = $attributes;
            }

            return $entities;
        } catch (\Exception $e) {
            throw new ErdParseException('Error parsing ERD at line ' . ($lineNumber + 1) . ': ' . $e->getMessage());
        }
    }
}
