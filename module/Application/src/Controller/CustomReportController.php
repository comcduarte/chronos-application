<?php
namespace Application\Controller;

use Laminas\View\Model\ViewModel;
use Report\Controller\ReportController;
use Report\Model\ReportModel;

class CustomReportController extends ReportController
{
    public function viewAction()
    {
        $view = new ViewModel();
        $view = parent::viewAction();
        
        /**
         * @var ReportModel $report
         */
        if ($report = $view->getVariable('report')) {
            if (method_exists($this, $report->FUNC)) {
                $function = $report->FUNC;
                $data = $this->$function($view->getVariable('data'));
                $view->setVariable('data', $data);
            }
        }
        $view->setTemplate('report/report/view');
        return $view;
    }
    
    private function deptbluesheet($data)
    {
        $dow = ['SUN','MON','TUES','WED','THURS','FRI','SAT', 'DAYS'];
        
        $keys = NULL;
        foreach ($data as $i => $paycode) {
            $index = sprintf('%s-%s-%s', $paycode['UUID'], $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP']);
            $keys[$index] = $i;
        }
        
        foreach ($data as $i => $paycode) {
            if ($paycode['PARENT'] != NULL) {
                $index = sprintf('%s-%s-%s', $paycode['PARENT'], $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP']);
                
                foreach ($dow as $day) {
                    $data[$keys[$index]][$day] += $paycode[$day];
                }
                
                unset($data[$i]);
            }
        }
        
        return $data;
    }
    
    private function dept_time_cards($data) 
    {
        $dow = ['SUN','MON','TUES','WED','THURS','FRI','SAT','DAYS'];
        
        $results = [];
        foreach ($data as $i => $paycode) {
            $index = sprintf('%s-%s', $paycode['EMP_NUM'], $paycode['CODE']);
            if (array_key_exists($index, $results)) {
                foreach ($dow as $day) {
                    if ($data[$i][$day]) {
                        $results[$index][$day] += $data[$i][$day];
                    }
                }
            } else {
                $results[$index] = $paycode;
            }
        }
        
        return $results;
    }
}