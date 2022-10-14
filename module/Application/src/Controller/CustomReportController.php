<?php
namespace Application\Controller;

use Laminas\View\Model\ViewModel;
use Report\Controller\ReportController;
use Report\Model\ReportModel;
use Timecard\Model\PaycodeModel;
use Timecard\Traits\DateAwareTrait;

class CustomReportController extends ReportController
{
    use DateAwareTrait;
    
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
    
    private function dept_blue_sheet_v3($data)
    {
        /******************************
         * Data Parameter Structure
         * $data = [
         *    0 => [
         *       [UUID],
         *       [STATUS],
         *       [DATE_CREATED],
         *       [DATE_MODIFIED],
         *       [WORK_WEEK],
         *       [TIMECARD_UUID],
         *       [PAY_UUID],
         *       [SUN]-[DAYS],
         *       [ORD],
         *       [EMP_UUID],
         *    ],
         * ];
         *
         ******************************/
        
        /******************************
         * Initialize return data structure.
         ******************************/
        $results = [
            'ACCRUALS' => [],
            'ACCRUAL_LIST' => [],
            'WORK_WEEK' => NULL,
            'DOW' => ['MON','TUE','WED','THU','FRI','SAT','SUN'],
            'DEPT' => NULL,
            'BLUESHEET' => [],
            'REG_UUID' => 0,
        ];
        
        /******************************
         * Retrieve list of Accrual Codes
         ******************************/
        $pm = new PaycodeModel($this->adapter);
        $accruals = $pm->get_accruals();
        $results['ACCRUALS'] = $accruals[1];
        $results['ACCRUAL_LIST'] = $accruals[0];

        /******************************
         * Process
         ******************************/
        foreach ($data as $paycode) {
            $index = sprintf('%s-%s-%s', $paycode['CODE'], $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP']);
            
            /**
             * Find Work Week
             */
            if ($paycode['WORK_WEEK'] != NULL && $results['WORK_WEEK'] == NULL) {
                $results['WORK_WEEK'] = $this->getEndofWeek($paycode['WORK_WEEK']);
            }
            
            $hours = 0;
            $days = 0;
            
            foreach ($results['DOW'] as $day) {
                $hours += floatval($paycode[$day]);
            }
            $days = $paycode['DAYS'];
            
            $results['BLUESHEET'][$index] = [
                'PAYCODE' => sprintf('%s - %s', $paycode['CODE'],  $paycode['DESC']),
                'TIMESHEET_GROUP' => sprintf('%s-%s', $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP']),
                $paycode['PAY_TYPE'] => [
                    'HOURS' => $hours,
                    'DAYS' => $days,
                ],
            ];
            
            /******************************
             * Add hours to parent if present
             ******************************/
            if ($paycode['PARENT']) {
                $pm->read(['UUID' => $paycode['PARENT']]);
                $index = sprintf('%s-%s-%s', $pm->CODE, $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP']);
                
                $results['BLUESHEET'][$index]['Regular']['HOURS'] += $hours;
                $results['BLUESHEET'][$index]['Regular']['DAYS'] += $days;
            }
        }
        
        ksort($results['BLUESHEET']);
        return $results;
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
            'WORK_WEEK' => $this->getEndofWeek($data[0]['WORK_WEEK']),
            'DOW' => ['MON','TUE','WED','THU','FRI','SAT','SUN','DAYS'],
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
            $emp_index = sprintf('%s-%s-%s', $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP'], $paycode['EMP_NUM']);
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
                    $z = 'REGULAR';
                    $total = 'Payroll Totals';
                    foreach ($results['DOW'] as $day) {
                        if ($paycode[$day]) {
                            /**
                             * For Accruals, if time is 8.5, move to 8
                             */
                            ($paycode[$day] == 8.5) ? $hours = 8 : $hours = $paycode[$day];
                            
                            $results['EMPLOYEES'][$emp_index][$z][$day][$paycode['CODE']] = $hours;
                            $code = $paycode['CODE'];
                        }
                    }
                    ksort($results['BLUESHEET'][$total]);
                    
                    if ($paycode['HOUR'] > 0) {
                        $results['BLUESHEET'][$total][$code] += $paycode['HOUR'];
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
                    break;
            }
        }
        ksort($results['EMPLOYEES']);
        return $results;
    }

    private function dept_time_cards_v3($data)
    {
        /******************************
         * Data Parameter Structure
         * $data = [
         *    0 => [
         *       [EMP_NUM],
         *       [FNAME],
         *       [LNAME],
         *       [TIME_GROUP],
         *       [TIME_SUBGROUP],
         *       [SHIFT_CODE],
         *       [EMP_UUID],
         *       [DEPT],
         *       [UUID],
         *       [CODE],
         *       [DESC],
         *       [CAT],
         *       [PARENT],
         *       [PAY_TYPE],
         *       [ACCURAL],
         *       [SUN]-[DAYS],
         *       [WORK_WEEK],
         *    ],
         * ];
         * 
         ******************************/
        
        
        /******************************
         * Initialize return data structure.
         ******************************/
        $results = [
            'ACCRUALS' => [],
            'ACCRUAL_LIST' => [],
            'EMPLOYEES' => [],
            'WORK_WEEK' => $this->getEndofWeek($data[0]['WORK_WEEK']),
            'DOW' => ['MON','TUE','WED','THU','FRI','SAT','SUN','DAYS'],
            'DEPT' => $data[0]['DEPT'],
            'BLUESHEET' => [
                'Codes' => [],
                'Time Off Totals' => [],
                'Payroll Totals' => [
                    '001' => 0,
                ],
            ],
            'REG_UUID' => 0,
        ];
        
        /******************************
         * Retrieve list of Accrual Codes
         ******************************/
        $pm = new PaycodeModel($this->adapter);
        $accruals = $pm->get_accruals();
        $results['ACCRUALS'] = $accruals[1];
        $results['ACCRUAL_LIST'] = $accruals[0];
        
        /******************************
         * Find UUID for 001 Regular Paycode
         ******************************/
        foreach ($data as $paycode) {
            switch (true) {
                case $paycode['CODE'] == '001':
                    $results['REG_UUID'] = $paycode['UUID'];
                    break 2;
                default:
                    break;
            }
        }
        
        /******************************
         * Process
         ******************************/
        foreach ($data as $paycode) {
            $emp_index = sprintf('%s-%s-%s', $paycode['TIME_GROUP'], $paycode['TIME_SUBGROUP'], $paycode['EMP_NUM']);
            $results['EMPLOYEES'][$emp_index]['RECORD'] = $paycode;
            
            /**
             * Find Work Week
             */
            if ($paycode['WORK_WEEK'] != NULL) {
                $results['WORK_WEEK'] = $this->getEndofWeek($paycode['WORK_WEEK']);
            }
            
            /**
             * If total was not initialized yet, set to zero.
             */
            if (empty($results['BLUESHEET']['Codes'][$paycode['CODE']])) {
                $results['BLUESHEET']['Codes'][$paycode['CODE']] = intval(0);
            }
            
            /**
             * If line total was not initialized, set to zero.
             */
            if (empty($results['EMPLOYEES'][$emp_index]['TOTALS'][$paycode['CODE']])) {
                $results['EMPLOYEES'][$emp_index]['TOTALS'][$paycode['CODE']] = intval(0);
            }
            
            foreach ($results['DOW'] as $day) {
                if ($paycode[$day]) {
                    /**
                     * Individual Day Total
                     */
                    $results['EMPLOYEES'][$emp_index]['OT'][$paycode['CODE']][$day] = $paycode[$day];
                    
                    /**
                     * Bluesheet Total
                     */
                    $results['BLUESHEET']['Codes'][$paycode['CODE']] += $paycode[$day];
                    
                    /**
                     * Employee Line Total
                     */
                    $results['EMPLOYEES'][$emp_index]['TOTALS'][$paycode['CODE']] += $paycode[$day];
                }
                
                /**
                 * Increase parent code if specified.
                 */
                if ($paycode['PARENT'] != NULL) {
                    $pm->read(['UUID' => $paycode['PARENT']]);
                    if (empty($results['BLUESHEET']['Codes'][$pm->CODE])) {
                        $results['BLUESHEET']['Codes'][$pm->CODE] = intval(0);
                    }
                    $results['BLUESHEET']['Codes'][$pm->CODE] += $paycode[$day];
                    
                    /**
                     * Increase Line Total
                     */
                    if (empty($results['EMPLOYEES'][$emp_index]['TOTALS'][$pm->CODE])) {
                        $results['EMPLOYEES'][$emp_index]['TOTALS'][$pm->CODE] = intval(0);
                    }
                    $results['EMPLOYEES'][$emp_index]['TOTALS'][$pm->CODE] += $paycode[$day];
                }
            }
        }
        
        /******************************
         * Sorting
         ******************************/
        foreach ($results['EMPLOYEES'] as $index => $record) {
            if (isset($record['REGULAR'])) { ksort($results['EMPLOYEES'][$index]['REGULAR']); }
            if (isset($record['OT'])) { ksort($results['EMPLOYEES'][$index]['OT']); }
        }
        ksort($results['BLUESHEET']['Codes']);
        ksort($results['EMPLOYEES']);
        
        /**
         * Separate the Codes array into their respective lists and sort.
         */
        foreach ($results['ACCRUALS'] as $code => $accrual) {
            if (isset($results['BLUESHEET']['Codes'][$code])) {
                if (empty($results['BLUESHEET']['Time Off Totals'][$accrual])) {
                    $results['BLUESHEET']['Time Off Totals'][$accrual] = intval(0);
                }
                $results['BLUESHEET']['Time Off Totals'][$accrual] += $results['BLUESHEET']['Codes'][$code];
                unset($results['BLUESHEET']['Codes'][$code]);
            }
        }
        $results['BLUESHEET']['Payroll Totals'] = $results['BLUESHEET']['Codes'];
        
        unset($results['BLUESHEET']['Codes']);
        ksort($results['BLUESHEET']['Payroll Totals']);
        ksort($results['BLUESHEET']['Time Off Totals']);
        
        /******************************
         * Return Result Structure
         * $results = [
         *    'EMPLOYEES' => [
         *       '000-000-000000' => [
         *          'RECORD' =>    //-- Original Record from Data 
         *          'REGULAR' => [
         *             '<DOW>' => [
         *                '<CODE>' => '<HOURS>',
         *             ],
         *          ], 
         *          'OT' => [
         *             '<DOW>' => [
         *                '<CODE>' => '<HOURS>',
         *             ],
         *          ],
         *          'TOTALS' => [
         *              '<CODE>' => '<HOURS>',
         *          ],
         *       ],
         *    ],
         * ];
         ******************************/
        return $results;
    }
}