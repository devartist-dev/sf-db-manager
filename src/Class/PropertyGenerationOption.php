<?php

namespace App\Class;

class PropertyGenerationOption {

    const TYPE_DATETIME = 'datetime_immutable';
    const TYPE_STRING = 'string';
    const TYPE_NUMBER = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_TEXT = 'text';

    const TYPE_MANY_TO_ONE = 'ManyToOne';
    const TYPE_ONE_TO_ONE = 'OneToOne';

    public function __construct(
        public string $propertyName,
        public string $propertyType = self::TYPE_STRING,
        public ?int $propertyMaxLength,
        public bool $isRequired = false, 
        public bool $isPropertyUnique = false,
        public ?string $relatedEntityClass = null,
        public bool $orphanRemoval = false,
    ){}

}