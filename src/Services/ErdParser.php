<?php

namespace tmgomas\ErdToModules\Services;

use tmgomas\ErdToModules\Exceptions\ErdParseException;

class ErdParser
{
    /**
     * Parse Mermaid ERD content and extract entities with their attributes and relationships
     *
     * @param string $content
     * @return array
     * @throws ErdParseException
     */
    public function parse(string $content): array
    {
        $entities = [];
        $relationships = [];
        $lines = explode("\n", $content);

        $currentEntity = null;
        $attributes = [];
        $inEntityBlock = false;

        try {
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);

                // Skip empty lines and comments
                if (empty($line) || strpos($line, '%%') === 0) {
                    continue;
                }

                // Check if we're starting with erDiagram which is common in Mermaid ERD
                if (strpos($line, 'erDiagram') === 0) {
                    continue;
                }

                // Check if line defines an entity
                if (preg_match('/^(\w+)\s+{/', $line, $matches)) {
                    // If we were processing an entity, save it before starting a new one
                    if ($currentEntity !== null && !empty($attributes)) {
                        $entities[$currentEntity]['attributes'] = $attributes;
                        $attributes = [];
                    }

                    $currentEntity = $matches[1];
                    $entities[$currentEntity] = [
                        'name' => $currentEntity,
                        'attributes' => []
                    ];
                    $inEntityBlock = true;
                }
                // Check if line contains an attribute (only if we're in an entity block)
                elseif ($inEntityBlock && preg_match('/^\s*(\w+)\s+(\w+)(?:\s+(PK|FK))?/', $line, $matches)) {
                    $attributeName = $matches[1];
                    $attributeType = $matches[2];
                    $constraint = isset($matches[3]) ? $matches[3] : null;

                    $attributes[] = [
                        'name' => $attributeName,
                        'type' => $attributeType,
                        'constraint' => $constraint,
                        'isPrimary' => $constraint === 'PK',
                        'isForeign' => $constraint === 'FK',
                    ];
                }
                // Check if line closes an entity definition
                elseif ($line === '}' && $inEntityBlock) {
                    $entities[$currentEntity]['attributes'] = $attributes;
                    $currentEntity = null;
                    $attributes = [];
                    $inEntityBlock = false;
                }
                // Check for relationship definitions
                elseif (!$inEntityBlock && preg_match('/^(\w+)\s+(\|\|--|o\|--|}o--|o{--|}\|--|}\{--)\s*(\w+)\s*:\s*"([^"]+)"/', $line, $matches)) {
                    $entityFrom = $matches[1];
                    $relationship = $matches[2];
                    $entityTo = $matches[3];
                    $description = $matches[4];

                    $relationType = $this->mapRelationshipType($relationship, $description);

                    $relationships[] = [
                        'from' => $entityFrom,
                        'to' => $entityTo,
                        'type' => $relationType,
                        'description' => $description
                    ];
                }
            }

            // If we end with an unclosed entity
            if ($currentEntity !== null && !empty($attributes)) {
                $entities[$currentEntity]['attributes'] = $attributes;
            }

            return [
                'entities' => $entities,
                'relationships' => $relationships
            ];
        } catch (\Exception $e) {
            throw new ErdParseException('Error parsing ERD at line ' . ($lineNumber + 1) . ': ' . $e->getMessage());
        }
    }

    /**
     * Map Mermaid relationship syntax to Laravel relationship type
     *
     * @param string $symbol
     * @param string $description
     * @return string
     */
    private function mapRelationshipType(string $symbol, string $description): string
    {
        // Default relationship mapping
        $map = [
            '||--o{' => 'hasMany',
            '||--||' => 'hasOne',
            '}o--||' => 'belongsTo',
            '}o--o{' => 'belongsToMany'
        ];

        // Override with description if available
        $descriptionMap = [
            'has many' => 'hasMany',
            'has one' => 'hasOne',
            'belongs to' => 'belongsTo',
            'belongs to many' => 'belongsToMany',
            'many to many' => 'belongsToMany'
        ];

        // First try to match by the description
        foreach ($descriptionMap as $key => $value) {
            if (stripos($description, $key) !== false) {
                return $value;
            }
        }

        // Fall back to symbol matching
        return $map[$symbol] ?? 'hasMany'; // Default to hasMany if not found
    }
}
