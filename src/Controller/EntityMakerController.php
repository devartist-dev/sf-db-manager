<?php

namespace App\Controller;

use App\Service\EntityMaker;
use App\Class\EntityGenerationOption;
use App\Class\PropertyGenerationOption;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\MakerBundle\Doctrine\EntityClassGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;

class EntityMakerController extends AbstractController
{

    public function __construct(
        private EntityMaker $entityMaker,
        private Generator $generator
    ){}

    #[Route('/test-entity-maker', name: 'app_entity_maker')]
    public function index(): Response
    {
        $entityAOption = new EntityGenerationOption(
            entityName: 'Category',
            apiResources: true,
            properties: [
                new PropertyGenerationOption(
                    propertyName:'name',
                    propertyType: PropertyGenerationOption::TYPE_STRING,
                    isRequired: true, 
                    isPropertyUnique: true
                ),
                new PropertyGenerationOption(
                    propertyName: 'parent',
                    propertyType: PropertyGenerationOption::TYPE_ONE_TO_ONE,
                    relatedEntityClass: "App\\Entity\\Category",
                    orphanRemoval: true
                ),
            ]
        );

        $entityBOption = new EntityGenerationOption(
            entityName: 'Product',
            apiResources: true,
            properties: [
                new PropertyGenerationOption(
                    propertyName:'title',
                    propertyType: PropertyGenerationOption::TYPE_STRING,
                    propertyMaxLength: 120, 
                    isRequired: false, 
                    isPropertyUnique: true
                ),
                new PropertyGenerationOption(
                    propertyName: 'price',
                    propertyType: PropertyGenerationOption::TYPE_FLOAT
                ),
                new PropertyGenerationOption(
                    propertyName: 'parent',
                    propertyType: PropertyGenerationOption::TYPE_MANY_TO_ONE,
                    relatedEntityClass: "App\\Entity\\Product",
                    orphanRemoval: true
                ),
                new PropertyGenerationOption(
                    propertyName: 'category',
                    propertyType: PropertyGenerationOption::TYPE_MANY_TO_ONE,
                    relatedEntityClass: "App\\Entity\\Category",
                    orphanRemoval: false,
                    isRequired: true,
                ),
            ]
        );

        // dd($entityOption);

        $this->entityMaker->generate($entityAOption,$this->generator);
        $this->entityMaker->generate($entityBOption,$this->generator);

        return $this->render('entity_maker/index.html.twig', [
            'controller_name' => 'EntityMakerController',
        ]);
    }
}
