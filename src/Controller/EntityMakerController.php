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

        $entityOption = new EntityGenerationOption(
            'Product',
            true,[
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
                    propertyType: PropertyGenerationOption::TYPE_ONE_TO_ONE,
                    relatedEntityClass: "App\\Entity\\Product",
                    orphanRemoval: true
                ),
            ]
        );

        // dd($entityOption);

        $this->entityMaker->generate($entityOption,$this->generator);

        return $this->render('entity_maker/index.html.twig', [
            'controller_name' => 'EntityMakerController',
        ]);
    }
}
