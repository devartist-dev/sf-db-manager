<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Class\GenerationState;
use Symfony\Bundle\MakerBundle\Str;
use ApiPlatform\Metadata\ApiResource;
use App\Class\EntityGenerationOption;
use App\Class\PropertyGenerationOption;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRelation;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;
use Symfony\Bundle\MakerBundle\Doctrine\EntityClassGenerator;
use Symfony\Bundle\MakerBundle\Doctrine\ORMDependencyBuilder;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassProperty;


/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Anderson FACHINA <andersonfachina@outlook.fr>
 */
class EntityMaker
{

    private Generator $generator;
    private EntityClassGenerator $entityClassGenerator;

    public function __construct(
        private FileManager $fileManager,
        private DoctrineHelper $doctrineHelper,
        ?string $projectDirectory = null,
        ?Generator $generator = null,
        ?EntityClassGenerator $entityClassGenerator = null,
    ) {
        if (null !== $projectDirectory) {
            @trigger_error('The $projectDirectory constructor argument is no longer used since 1.41.0', \E_USER_DEPRECATED);
        }

        if (null === $generator) {
            @trigger_error(sprintf('Passing a "%s" instance as 4th argument is mandatory since version 1.5.', Generator::class), \E_USER_DEPRECATED);
            $this->generator = new Generator($fileManager, 'App\\');
        } else {
            $this->generator = $generator;
        }

        if (null === $entityClassGenerator) {
            @trigger_error(sprintf('Passing a "%s" instance as 5th argument is mandatory since version 1.15.1', EntityClassGenerator::class), \E_USER_DEPRECATED);
            $this->entityClassGenerator = new EntityClassGenerator($generator, $this->doctrineHelper);
        } else {
            $this->entityClassGenerator = $entityClassGenerator;
        }
    }


    public function generate(EntityGenerationOption $options, Generator $generator): GenerationState
    {
        $overwrite = $options->overwrite;

        $entityClassDetails = $generator->createClassNameDetails(
            $options->entityName,
            'Entity\\'
        );

        $classExists = class_exists($entityClassDetails->getFullName());
        if (!$classExists) {

            $entityPath = $this->entityClassGenerator->generateEntityClass(
                $entityClassDetails,
                $options->apiResources,
                false,
                true,
                false
            );

            $generator->writeChanges();
        }

        if ($classExists) {
            $entityPath = $this->getPathOfClass($entityClassDetails->getFullName());
        } 

        $currentFields = $this->getPropertyNames($entityClassDetails->getFullName());
        $manipulator = $this->createClassManipulator($entityPath, $overwrite);

        $isFirstField = true;

        foreach ($options->properties as $propsOptions) {
            $newField = $this->askForNextField($propsOptions, $currentFields, $entityClassDetails->getFullName(), $isFirstField);
            $isFirstField = false;

            if (null === $newField) {
                break;
            }

            $fileManagerOperations = [];
            $fileManagerOperations[$entityPath] = $manipulator;

            if ($newField instanceof ClassProperty) {
                $manipulator->addEntityField($newField);

                $currentFields[] = $newField->propertyName;
            } elseif ($newField instanceof EntityRelation) {
                // both overridden below for OneToMany
                $newFieldName = $newField->getOwningProperty();
                if ($newField->isSelfReferencing()) {
                    $otherManipulatorFilename = $entityPath;
                    $otherManipulator = $manipulator;
                } else {
                    $otherManipulatorFilename = $this->getPathOfClass($newField->getInverseClass());
                    $otherManipulator = $this->createClassManipulator($otherManipulatorFilename, $overwrite);
                }
                switch ($newField->getType()) {
                    case EntityRelation::MANY_TO_ONE:
                        if ($newField->getOwningClass() === $entityClassDetails->getFullName()) {
                            // THIS class will receive the ManyToOne
                            $manipulator->addManyToOneRelation($newField->getOwningRelation());

                            if ($newField->getMapInverseRelation()) {
                                $otherManipulator->addOneToManyRelation($newField->getInverseRelation());
                            }
                        } else {
                            // the new field being added to THIS entity is the inverse
                            $newFieldName = $newField->getInverseProperty();
                            $otherManipulatorFilename = $this->getPathOfClass($newField->getOwningClass());
                            $otherManipulator = $this->createClassManipulator($otherManipulatorFilename, $overwrite);

                            // The *other* class will receive the ManyToOne
                            $otherManipulator->addManyToOneRelation($newField->getOwningRelation());
                            if (!$newField->getMapInverseRelation()) {
                                throw new \Exception('Somehow a OneToMany relationship is being created, but the inverse side will not be mapped?');
                            }
                            $manipulator->addOneToManyRelation($newField->getInverseRelation());
                        }

                        break;
                    case EntityRelation::MANY_TO_MANY:
                        $manipulator->addManyToManyRelation($newField->getOwningRelation());
                        if ($newField->getMapInverseRelation()) {
                            $otherManipulator->addManyToManyRelation($newField->getInverseRelation());
                        }

                        break;
                    case EntityRelation::ONE_TO_ONE:
                        $manipulator->addOneToOneRelation($newField->getOwningRelation());
                        if ($newField->getMapInverseRelation()) {
                            $otherManipulator->addOneToOneRelation($newField->getInverseRelation());
                        }

                        break;
                    default:
                        throw new \Exception('Invalid relation type');
                }

                // save the inverse side if it's being mapped
                if ($newField->getMapInverseRelation()) {
                    $fileManagerOperations[$otherManipulatorFilename] = $otherManipulator;
                }
                $currentFields[] = $newFieldName;
            } else {
                throw new \Exception('Invalid value');
            }

            foreach ($fileManagerOperations as $path => $manipulatorOrMessage) {
                if (\is_string($manipulatorOrMessage)) {
                    
                } else {
                    $this->fileManager->dumpFile($path, $manipulatorOrMessage->getSourceCode());
                }
            }
        }

        return GenerationState::SUCCESS;
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        if (null !== $input && $input->getOption('api-resource')) {
            $dependencies->addClassDependency(
                ApiResource::class,
                'api'
            );
        }

        ORMDependencyBuilder::buildDependencies($dependencies);
    }

    private function askForNextField(PropertyGenerationOption $options, array $fields, string $entityClass, bool $isFirstField): EntityRelation|ClassProperty|null
    {

        $fieldName = Validator::validateDoctrineFieldName($options->propertyName, $this->doctrineHelper->getRegistry());

        if (!$fieldName) {
            return null;
        }

        $type = $options->propertyType;

        if (\in_array($type, EntityRelation::getValidRelationTypes())) {
            return $this->askRelationDetails($entityClass, $options);
        }

        // this is a normal field
        $classProperty = new ClassProperty(propertyName: $fieldName, type: $type);

        if ('string' === $type) {
            // default to 255, avoid the question
            $classProperty->length = $options->propertyMaxLength;
        }

        if ($options->isRequired) {
            $classProperty->nullable = true;
        }

        if($options->isPropertyUnique){
            $classProperty->unique = true;
        }

        return $classProperty;
    }


    private function askRelationDetails(string $generatedEntityClass, PropertyGenerationOption $options): EntityRelation
    {
        $type = $options->propertyType; 
        $newFieldName = $options->propertyName;
        // ask the targetEntity
        $targetEntityClass = $options->relatedEntityClass;

        $askFieldName = fn (string $defaultValue) =>  
            Validator::validateDoctrineFieldName($defaultValue, $this->doctrineHelper->getRegistry());

        $askIsNullable = $options->isRequired;

        $askOrphanRemoval = $options->orphanRemoval;

        $askInverseSide = function (EntityRelation $relation) {
            if ($this->isClassInVendor($relation->getInverseClass())) {
                $relation->setMapInverseRelation(false);

                return;
            }

            // recommend an inverse side, except for OneToOne, where it's inefficient
            $recommendMappingInverse = EntityRelation::ONE_TO_ONE !== $relation->getType();

            $getterMethodName = 'get'.Str::asCamelCase(Str::getShortClassName($relation->getOwningClass()));
            if (EntityRelation::ONE_TO_ONE !== $relation->getType()) {
                // pluralize!
                $getterMethodName = Str::singularCamelCaseToPluralCamelCase($getterMethodName);
            }
            $mapInverse = $recommendMappingInverse;
            $relation->setMapInverseRelation($mapInverse);
        };

        switch ($type) {
            case EntityRelation::MANY_TO_ONE:
                $relation = new EntityRelation(
                    EntityRelation::MANY_TO_ONE,
                    $generatedEntityClass,
                    $targetEntityClass
                );
                $relation->setOwningProperty($newFieldName);

                $relation->setIsNullable($askIsNullable);

                $askInverseSide($relation);
                if ($relation->getMapInverseRelation()) {

                    $relation->setInverseProperty($askFieldName(
                        Str::singularCamelCaseToPluralCamelCase(Str::getShortClassName($relation->getOwningClass()))
                    ));

                    // orphan removal only applies if the inverse relation is set
                    if (!$relation->isNullable()) {
                        $relation->setOrphanRemoval($askOrphanRemoval);
                    }
                }

                break;
            case EntityRelation::ONE_TO_MANY:
                // we *actually* create a ManyToOne, but populate it differently
                $relation = new EntityRelation(
                    EntityRelation::MANY_TO_ONE,
                    $targetEntityClass,
                    $generatedEntityClass
                );
                $relation->setInverseProperty($newFieldName);

                $relation->setOwningProperty($askFieldName(
                    Str::asLowerCamelCase(Str::getShortClassName($relation->getInverseClass()))
                ));

                $relation->setIsNullable($askIsNullable);

                if (!$relation->isNullable()) {
                    $relation->setOrphanRemoval($askOrphanRemoval(
                        $relation->getOwningClass(),
                        $relation->getInverseClass()
                    ));
                }

                break;
            case EntityRelation::MANY_TO_MANY:
                $relation = new EntityRelation(
                    EntityRelation::MANY_TO_MANY,
                    $generatedEntityClass,
                    $targetEntityClass
                );
                $relation->setOwningProperty($newFieldName);

                $askInverseSide($relation);
                if ($relation->getMapInverseRelation()) {
                    $relation->setInverseProperty($askFieldName(
                        Str::singularCamelCaseToPluralCamelCase(Str::getShortClassName($relation->getOwningClass()))
                    ));
                }

                break;
            case EntityRelation::ONE_TO_ONE:
                $relation = new EntityRelation(
                    EntityRelation::ONE_TO_ONE,
                    $generatedEntityClass,
                    $targetEntityClass
                );
                $relation->setOwningProperty($newFieldName);

                $relation->setIsNullable($askIsNullable);

                $askInverseSide($relation);
                if ($relation->getMapInverseRelation()) {
                    
                    $relation->setInverseProperty($askFieldName(
                        Str::asLowerCamelCase(Str::getShortClassName($relation->getOwningClass()))
                    ));
                }

                break;
            default:
                throw new \InvalidArgumentException('Invalid type: '.$type);
        }

        return $relation;
    }

    private function createClassManipulator(string $path, bool $overwrite): ClassSourceManipulator
    {
        $manipulator = new ClassSourceManipulator(
            sourceCode: $this->fileManager->getFileContents($path),
            overwrite: $overwrite,
        );

        return $manipulator;
    }

    private function getPathOfClass(string $class): string
    {
        return (new ClassDetails($class))->getPath();
    }

    private function isClassInVendor(string $class): bool
    {
        $path = $this->getPathOfClass($class);

        return $this->fileManager->isPathInVendor($path);
    }

    private function getPropertyNames(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }

        $reflClass = new \ReflectionClass($class);

        return array_map(static fn (\ReflectionProperty $prop) => $prop->getName(), $reflClass->getProperties());
    }

}
