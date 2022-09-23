<?php
namespace Application\Controller\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Application\Controller\FilesController;

class FilesControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $controller = new FilesController();
        $adapter = $container->get('employee-model-adapter');
        $controller->setDbAdapter($adapter);
        $controller->setFilesAdapter($container->get('files-model-adapter'));
        return $controller;
    }
}