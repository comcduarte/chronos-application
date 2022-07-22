<?php
namespace Application\Controller;

use Laminas\View\Model\ViewModel;
use Report\Controller\ReportController;
use Report\Model\ReportModel;
use Timecard\Model\PaycodeModel;

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
        $dow = ['SUN','MON','TUE','WED','THU','FRI','SAT', 'DAYS'];
        
        $keys = NULL;
        foreach ($data as $i => $paycode) {
            $index = sprintf('%s-%s-%s', $paycode['UUID'], $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP']);
            $keys[$index] = $i;
        }
        
        foreach ($data as $i => $paycode) {
            /**
             * If a paycode has a parent, add the hours to the parents total as well as the individual paycode's total.
             */
            if ($paycode['PARENT'] != NULL) {
                $index = sprintf('%s-%s-%s', $paycode['PARENT'], $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP']);
                
                foreach ($dow as $day) {
                    $data[$keys[$index]][$day] += $paycode[$day];
                }
             /**
              * If the original paycodes total needs to be removed, uncomment the following line.
              * unset($data[$i]);
              */
            }
        }
        return $data;
    }
    
    private function dept_time_cards($data) 
    {
        $dow = ['SUN','MON','TUE','WED','THU','FRI','SAT','DAYS'];
        
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
    
    private function dept_time_cards_v2($data)
    {
        $pm = new PaycodeModel($this->adapter);
        /**
         * Returns
         * $paycodes[0]['ACCRUAL'] = 'XX'
         * @var array $paycodes
         */
        $accruals = $pm->get_accruals();
        $results = [
            'ACCRUALS' => $accruals[1],
            'ACCRUAL_LIST' => $accruals[0],
            'EMPLOYEES' => [],
            'WORK_WEEK' => $data[0]['WORK_WEEK'],
            'DOW' => ['SUN','MON','TUE','WED','THU','FRI','SAT','DAYS'],
            'DEPT' => $data[0]['DEPT'],
            'BLUESHEET' => [
                'Time Off Totals' => [],
                'Payroll Totals' => [
                    '001' => 0,
                ],
            ],
            'REG_UUID' => 0,
        ];
        
        /**
         * Find UUID for 001 Regular Paycode
         */
        foreach ($data as $paycode) {
            switch (true) {
                case $paycode['CODE'] == '001':
                    $results['REG_UUID'] = $paycode['UUID'];
                    break 2;
                default:
                    break;
            }
        }
        
        foreach ($data as $i => $paycode) {
            $emp_index = sprintf('%s-%s',$paycode['TIME_SUBGROUP'], $paycode['EMP_NUM']);
            $results['EMPLOYEES'][$emp_index]['RECORD'] = $paycode;
            switch (true) {
                case array_search($paycode['CODE'], array_keys($accruals[1])):
                    $z = 'REGULAR';
                    foreach ($results['DOW'] as $day) {
                        if ($paycode[$day]) {
                            /**
                             * For Accruals, if time is 8.5, move to 8
                             */
                            ($paycode[$day] == 8.5) ? $hours = 8 : $hours = $paycode[$day];
                            
                            $results['EMPLOYEES'][$emp_index][$z][$day][$paycode['CODE']] = $hours;
                            $code = $paycode['CODE'];
                            $accrual = $results['ACCRUALS'][$code];
                            
                            if (!empty($results['BLUESHEET']['Time Off Totals'][$accrual])) {
                                $results['BLUESHEET']['Time Off Totals'][$accrual] += $hours;
                            } else {
                                $results['BLUESHEET']['Time Off Totals'][$accrual] = $hours;
                            }
                        }
                    }
                    ksort($results['BLUESHEET']['Time Off Totals']);
                    break;
                case $paycode['CODE'] == '001':
                    if ($paycode['HOUR'] > 0) {
                        $results['BLUESHEET']['Payroll Totals']['001'] += $paycode['HOUR'];
                        continue 2;
                    }
                case $paycode['PARENT'] == $results['REG_UUID']:
                    $z = 'OT';
                    foreach ($results['DOW'] as $day) {
                        if ($paycode[$day]) {
                            $results['EMPLOYEES'][$emp_index][$z][$paycode['CODE']][$day] = $paycode[$day];
                            $code = '001'; //-- Force 001 Code --//
                            
                            if (!empty($results['BLUESHEET']['Payroll Totals'][$code])) {
                                $results['BLUESHEET']['Payroll Totals'][$code] += $paycode[$day];
                            } else {
                                $results['BLUESHEET']['Payroll Totals'][$code] = $paycode[$day];
                            }
                        }
                        
                        
                    }
                    break;
                default:
                    $z = 'OT';
                    foreach ($results['DOW'] as $day) {
                        if ($paycode[$day]) {
                            $results['EMPLOYEES'][$emp_index][$z][$paycode['CODE']][$day] = $paycode[$day];
                            $code = $paycode['CODE'];
                            
                            if (!empty($results['BLUESHEET']['Payroll Totals'][$code])) {
                                $results['BLUESHEET']['Payroll Totals'][$code] += $paycode[$day];
                            } else {
                                $results['BLUESHEET']['Payroll Totals'][$code] = $paycode[$day];
                            }
                        }
                        
                        
                    }
                    ksort($results['BLUESHEET']['Payroll Totals']);
//                     ksort($results['EMPLOYEES'][$emp_index][$z]);
                    break;
            }
        }
        ksort($results['EMPLOYEES']);
        return $results;
    }
}