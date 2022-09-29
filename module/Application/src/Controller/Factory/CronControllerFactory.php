<?php
namespace Application\Controller\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Application\Controller\CronController;

class CronControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $controller = new CronController();
        $controller->employee_adapter = $container->get('employee-model-adapter');
        $controller->timecard_adapter = $container->get('timecard-model-adapter');
        $controller->logger = $container->get('syslogger');
        return $controller;
    }
}