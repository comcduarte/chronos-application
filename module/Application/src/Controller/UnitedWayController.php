<?php
namespace Application\Controller;

use Components\Controller\AbstractBaseController;
use Laminas\View\Model\ViewModel;
use Application\Model\Entity\UserEntity;
use Laminas\Db\Sql\Where;
use Application\Model\UnitedWayModel;

class UnitedWayController extends AbstractBaseController
{
    public $user_adapter;
    public $employee_adapter;
    public $timecard_adapter;
    
    public function indexAction()
    {
        $view = new ViewModel();
        
        $uuid = $this->params()->fromRoute('uuid', 0);
        if (!$uuid) {
            /******************************
             * CURRENT USER ENTITY
             ******************************/
            $user = $this->currentUser();
            $user_entity = new UserEntity($this->user_adapter);
            $user_entity->employee->setDbAdapter($this->employee_adapter);
            $user_entity->department->setDbAdapter($this->employee_adapter);
            $user_entity->getUser($user->UUID);
            return $this->redirect()->toRoute('application/unitedway', ['uuid' => $user_entity->employee->UUID, 'action' => 'index']);
        }
        $view->setVariable('uuid', $uuid);
        return $view;
    }
    
    public function payrollAction()
    {
        $view = new ViewModel();
        $view->setTemplate('unitedway');
        
        $user = $this->currentUser();
        
        $user_entity = new UserEntity($this->user_adapter);
        $user_entity->employee->setDbAdapter($this->employee_adapter);
        $user_entity->department->setDbAdapter($this->employee_adapter);
        
        $uuid = $this->params()->fromRoute('uuid', 0);
        if (!$uuid) {
            /******************************
             * CURRENT USER ENTITY
             ******************************/
            $user = $this->currentUser();
            $user_entity->getUser($user->UUID);
            return $this->redirect()->toRoute('application/unitedway', ['uuid' => $user_entity->employee->UUID, 'action' => 'payroll']);
        } else {
            $user_entity->getEmployee($uuid);
            $view->setVariable('user', $user_entity);
        }
        
        /******************************
         * VERIFY IF ENTRY WAS ALREADY MADE
         ******************************/
        $where = new Where();
        $where->equalTo('EMP_UUID', $user_entity->employee->UUID);
        $where->equalTo('STATUS', 1);
        $records = $this->model->fetchAll($where);
        
        if (!empty($records)) {
            $view->setVariable('records', $records);
            return $view;
        }
        
        /******************************
         * FORM
         ******************************/
        $this->form->get('EMP_UUID')->setValue($user_entity->employee->UUID);
        $this->form->get('USER_UUID')->setValue($user->UUID);
        $this->form->get('METHOD')->setValue('PAYROLL');
        $view->setVariable('payroll_form', $this->form);
        
        return $view;
    }
    
    public function cashAction()
    {
        $view = new ViewModel();
        $view->setTemplate('unitedway');
        
        $user = $this->currentUser();
        
        $user_entity = new UserEntity($this->user_adapter);
        $user_entity->employee->setDbAdapter($this->employee_adapter);
        $user_entity->department->setDbAdapter($this->employee_adapter);
        
        $uuid = $this->params()->fromRoute('uuid', 0);
        if (!$uuid) {
            /******************************
             * CURRENT USER ENTITY
             ******************************/
            $user = $this->currentUser();
            $user_entity->getUser($user->UUID);
            return $this->redirect()->toRoute('application/unitedway', ['uuid' => $user_entity->employee->UUID, 'action' => 'cash']);
        } else {
            $user_entity->getEmployee($uuid);
            $view->setVariable('user', $user_entity);
        }
        
        /******************************
         * VERIFY IF ENTRY WAS ALREADY MADE
         ******************************/
        $where = new Where();
        $where->equalTo('EMP_UUID', $user_entity->employee->UUID);
        $where->equalTo('STATUS', 1);
        $records = $this->model->fetchAll($where);
        
        if (!empty($records)) {
            $view->setVariable('records', $records);
            return $view;
        }
        
        /******************************
         * FORM
         ******************************/
        $this->form->get('EMP_UUID')->setValue($user_entity->employee->UUID);
        $this->form->get('USER_UUID')->setValue($user->UUID);
        $this->form->get('METHOD')->setValue('CASH');
        $view->setVariable('payroll_form', $this->form);
        
        return $view;
    }
    
    public function createAction()
    {
        parent::createAction();
        
        /**
         * 
         * @var UnitedWayModel $model
         */
        $model = $this->model;
        if ($model->DEDUCTION == 0) {
            $model->DEDUCTION = $model->OTHER;
            $model->update();
        }
        
        $route = $this->getEvent()->getRouteMatch()->getMatchedRouteName();
        $params = array_merge(
            $this->getEvent()->getRouteMatch()->getParams(),
            ['action' => 'payroll', 'uuid' => $this->model->EMP_UUID]
            );
        
        return $this->redirect()->toRoute($route, $params);
    }
}