<?php
namespace Application\Controller\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Application\Controller\TelestaffImportController;

class TelestaffImportControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $controller = new TelestaffImportController();
        $adapter = $container->get('timecard-model-adapter');
        $controller->setDbAdapter($adapter);
        $controller->timecard_adapter = $container->get('timecard-model-adapter');
        $controller->employee_adapter = $container->get('employee-model-adapter');
        $controller->logger = $container->get('syslogger');
        return $controller;
    }
}