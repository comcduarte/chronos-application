<?php
namespace Application\Controller\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Application\Controller\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $controller = new IndexController();
        $adapter = $container->get('employee-model-adapter');
        $controller->setDbAdapter($adapter);
        $controller->setFilesAdapter($container->get('files-model-adapter'));
        return $controller;
    }
}