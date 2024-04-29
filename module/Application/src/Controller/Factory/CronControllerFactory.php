<?php
namespace Application\Controller\Factory;

use Application\Controller\CronController;
use Laminas\Box\API\AccessToken;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CronControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $controller = new CronController();
        $controller->employee_adapter = $container->get('employee-model-adapter');
        $controller->timecard_adapter = $container->get('timecard-model-adapter');
        $controller->logger = $container->get('syslogger');
        
        $access_token = new AccessToken($container->get('access-token-config'));
        $controller->setAccessToken($access_token);
        
        return $controller;
    }
}