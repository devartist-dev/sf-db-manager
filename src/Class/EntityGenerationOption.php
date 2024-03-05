<?php

namespace App\Class;

class EntityGenerationOption
{

    /** @param PropertyGenerationOption[] $properties */
    public function __construct(
        public string $entityName,
        public bool $apiResources,
        public bool $regenerate = false,
        public array $properties = [],
        public bool $overwrite = true,
    ) {
    }
}
