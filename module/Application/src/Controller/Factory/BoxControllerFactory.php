<?php
namespace Application\Controller\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Application\Controller\BoxController;
use Laminas\Box\API\AccessToken;

class BoxControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $controller = new BoxController();
        
        $adapter = $container->get('model-adapter');
        $controller->setDbAdapter($adapter);
        
        $access_token = new AccessToken($container->get('access-token-config'));
        $controller->setAccessToken($access_token);
        
        return $controller;
    }
}