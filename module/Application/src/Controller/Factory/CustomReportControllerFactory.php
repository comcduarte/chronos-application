<?php
namespace Application\Controller\Factory;

use Application\Controller\CustomReportController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Report\Form\ReportForm;
use Report\Model\ReportModel;

class CustomReportControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter = $container->get('report-model-adapter');
        
        $controller = new CustomReportController();
        $controller->setDbAdapter($adapter);
        
        $model = new ReportModel($adapter);
        $form = $container->get('FormElementManager')->get(ReportForm::class);
        
        $controller->employee_adapter = $container->get('employee-model-adapter');
        
        $controller->setModel($model);
        $controller->setForm($form);
        
        return $controller;
    }
}