<?php
namespace Application\Controller\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Application\Controller\UnitedWayController;
use Application\Model\UnitedWayModel;
use Application\Form\UnitedWayForm;

class UnitedWayControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $controller = new UnitedWayController();
        $adapter = $container->get('unitedway-model-adapter');
        $model = new UnitedWayModel($adapter);
        $form = $container->get('FormElementManager')->get(UnitedWayForm::class);
        
        $controller->setModel($model);
        $controller->setForm($form);
        $controller->setDbAdapter($adapter);
        
        $controller->employee_adapter = $container->get('employee-model-adapter');
        $controller->user_adapter = $container->get('user-model-adapter');
        
        return $controller;
    }
}