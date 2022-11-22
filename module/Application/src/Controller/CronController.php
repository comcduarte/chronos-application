<?php
namespace Application\Controller;

use Employee\Model\DepartmentModel;
use Employee\Model\EmployeeModel;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Identical;
use Laminas\View\Model\ViewModel;
use Exception;

class CronController extends AbstractActionController
{
    public $employee_adapter;
    public $timecard_adapter;
    
    public $logger;
    
    public function cronAction()
    {
        $view = new ViewModel();
        $this->layout('application/cron/layout');
        
        $messages = [];
        
        $sql = new Sql($this->timecard_adapter);
        
        $select = new Select();
        $select->from('update_employeedata');
        $select->columns([
            'EMP_NUM',
            'FNAME',
            'LNAME',
            'TIME_GROUP' => 'PTG',
            'TIME_SUBGROUP' => 'PTSG',
            'DEPT' => 'DEPT_NUM',
            'POSITION' => 'POS_NUM',
            'POSITION_DESC' => 'POS_DESC',
            
        ]);
        
        
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultSet = new ResultSet();
        
        try {
            $results = $statement->execute();
            $resultSet->initialize($results);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            $this->logger->info($e->getMessage());
        }
        
        foreach ($resultSet as $record) {
            $messages[] = $record;
            
            $employee = new EmployeeModel($this->employee_adapter);
            $department = new DepartmentModel($this->employee_adapter);
            if ($employee->read(['EMP_NUM' => $record['EMP_NUM']])) {
                
                $needs_updating = false;
                /**
                 * If employee number exists, compare all fields.
                 */
                foreach ($record as $field => $value) {
                    if ($field == 'DEPT' ) {
                        if ( $department->read(['CODE' => str_pad($record['TIME_GROUP'], 5, 0, STR_PAD_RIGHT)]) ) {
                            $value = $department->UUID;
                        } else {
                            continue;
                        }
                    }
                    
                    $validator = new Identical($value);
                    if (!$validator->isValid($employee->$field)) {
                        $message = sprintf('Updated field [%s] from [%s] to [%s] for employee number [%s]', $field, $employee->$field, $value, $employee->EMP_NUM);
                        $messages[] = $message;
                        $this->logger->info($message);
                        $needs_updating = true;
                        
                        $employee->$field = $value;
                    }
                }
                
                /**
                 * Update employee record.
                 */
                if ($needs_updating) {
                    $needs_updating = false;
                    try {
                        $employee->update();
                    } catch (Exception $e) {
                        $messages[] = $e->getMessage();
                        $this->logger->info($e->getMessage());
                    }
                }
                
            } else {
                /**
                 * New Employee
                 */
                foreach ($record as $field => $value) {
                    $employee->$field = $value;
                }
                
                $message = sprintf('Create new user %s %s [%s]', $employee->FNAME, $employee->LNAME, $employee->EMP_NUM);
                $messages[] = $message;
                $this->logger->info($message);
                
                try {
                    $employee->create();
                } catch (Exception $e) {
                    $messages[] = $e->getMessage();
                    $this->logger->info($e->getMessage());
                }
            }
            
            /**
             * Remove record from temporary table.
             * @var \Laminas\Db\Sql\Delete $delete
             */
            $delete = new Delete();
            $delete->from('update_employeedata');
            
            
            $where = new Where();
            $where->equalTo('EMP_NUM', $record['EMP_NUM']);
            $delete->where($where);
            
            $statement = $sql->prepareStatementForSqlObject($delete);
            try {
                $results = $statement->execute();
            } catch (Exception $e) {
                $messages[] = $e->getMessage();
                $this->logger->info($e->getMessage());
            }
        }
        
        $view->setVariable('messages', json_encode($messages));
        return $view;
    }
}