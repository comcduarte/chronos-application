<?php
namespace Application\Model\Entity;

use Employee\Model\DepartmentModel;
use Employee\Model\EmployeeModel;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use User\Model\UserModel;
use Exception;
use Laminas\Db\Sql\Insert;

class UserEntity
{
    use AdapterAwareTrait;
    
    public $user;
    public $employee;
    public $department;
    public $groups;
    
    public function __construct(Adapter $adapter)
    {
        $this->setDbAdapter($adapter);
        $this->user = new UserModel($adapter);
        $this->employee = new EmployeeModel($adapter);
        $this->department = new DepartmentModel($adapter);
        $this->groups = [];
    }
    
    public function getUser(String $uuid)
    {
        $this->user->read(['UUID' => $uuid]);
        $emp_uuid = $this->getEmployeeRelationship();
        if (! $emp_uuid ) {
            if ( $this->findEmployee() ) {
                $this->setRelationship($this->user->UUID, $this->employee->UUID);
            }
        } else {
            $this->employee->read(['UUID' => $emp_uuid]);
            $this->department->read(['UUID' => $this->employee->DEPT]);
        }
        
        $this->getGroups();
        
        return $this;
    }
    
    public function getEmployee(String $uuid)
    {
        $this->employee->read(['UUID' => $uuid]);
        $this->getDepartment($this->employee->DEPT);
        
        $user_uuid = $this->getUserRelationship();
        if (! $user_uuid) {
            if ( $this->findUser() ) {
                $this->setRelationship($this->user->UUID, $this->employee->UUID);
            }
        } else {
            $this->user->read(['UUID' => $user_uuid]);
            $this->getGroups();
        }
        return $this;
    }
    
    public function getDepartment(String $uuid)
    {
        $this->department->read(['UUID' => $uuid]);
        return $this;
    }
    
    public function getGroups()
    {
        $this->groups = $this->user->memberOf();
        return $this;
    }
    
    private function getUserRelationship()
    {
        $sql = new Sql($this->adapter);
        
        $where = new Where();
        $where->equalTo('EMP_UUID', $this->employee->UUID);
        
        $select = new Select();
        $select->from('user_employee');
        $select->where($where);
        
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultSet = new ResultSet();
        try {
            $results = $statement->execute();
            $resultSet->initialize($results);
        } catch (Exception $e) {
            return FALSE;
        }
        
        $data = $resultSet->toArray();
        if (sizeof($data)) {
            return $data[0]['USER_UUID'];
        } else {
            return false;
        }
    }
    
    private function getEmployeeRelationship()
    {
        $sql = new Sql($this->adapter);
        
        $where = new Where();
        $where->equalTo('USER_UUID', $this->user->UUID);
        
        $select = new Select();
        $select->from('user_employee');
        $select->where($where);
        
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultSet = new ResultSet();
        try {
            $results = $statement->execute();
            $resultSet->initialize($results);
        } catch (Exception $e) {
            return FALSE;
        }
        
        $data = $resultSet->toArray();
        if (sizeof($data)) {
            return $data[0]['EMP_UUID'];
        } else {
            return false;
        }
    }
    
    private function setRelationship (String $user_uuid, String $emp_uuid)
    {
        $sql = new Sql($this->adapter);
        
        $insert = new Insert();
        $insert->into('user_employee');
        $insert->values(['UUID' => $this->user->generate_uuid(), 'USER_UUID' => $this->user->UUID, 'EMP_UUID' => $this->employee->UUID]);
        
        $statement = $sql->prepareStatementForSqlObject($insert);
        $resultSet = new ResultSet();
        try {
            $results = $statement->execute();
            $resultSet->initialize($results);
        } catch (Exception $e) {
            return FALSE;
        }
        
        return $this;
    }
    
    private function findEmployee()
    {
        $retval = $this->employee->read(['FNAME' => $this->user->FNAME, 'LNAME' => $this->user->LNAME]);
        if (! $retval) {
            return $retval;
        }
        return $this;
    }
    
    private function findUser()
    {
        $retval = $this->user->read([['FNAME' => $this->employee->FNAME, 'LNAME' => $this->employee->LNAME]]);
        if (! $retval) {
            return $retval;
        }
        return $this;
    }
}