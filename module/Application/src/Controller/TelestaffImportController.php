<?php
namespace Application\Controller;

use Annotation\Model\AnnotationModel;
use Application\Model\TelestaffModel;
use Components\Controller\AbstractConfigController;
use Components\Form\UploadFileForm;
use Employee\Model\EmployeeModel;
use Laminas\Db\Adapter\Exception\InvalidQueryException;
use Laminas\Db\Exception\ErrorException;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Ddl\DropTable;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Form\Form;
use Laminas\Form\Element\Submit;
use Laminas\Validator\GreaterThan;
use Laminas\Validator\NotEmpty;
use Laminas\View\Model\ViewModel;
use Timecard\Model\PaycodeModel;
use Timecard\Model\TimecardLineModel;
use Timecard\Model\TimecardModel;
use Timecard\Model\Entity\TimecardEntity;
use Timecard\Traits\DateAwareTrait;

class TelestaffImportController extends AbstractConfigController
{
    use DateAwareTrait;
    
    public $timecard_adapter;
    public $employee_adapter;
    
    public $logger;
    
    public function indexAction()
    {
        $view = new ViewModel();
        $view = parent::indexAction();
        $view->setTemplate('telestaff/config');
        
        /**
         * Get total count of records in temporary table.
         * @var \Application\Model\TelestaffModel $upload
         */
        $upload = new TelestaffModel($this->adapter);
        $records = $upload->fetchAll();
        $total = count($records);
        $view->setVariable('total', $total);
        
        $invalids = new Where();
        $invalids->equalTo('STATUS', TelestaffModel::INVALID_STATUS);
        
        $errors = $upload->fetchAll($invalids);
        $view->setVariable('errors', $errors);
        
        /**
         * Flush Form
         * @var \Laminas\Form\Form $flush
         */
        $flush = new Form();
        $flush->add([
            'name' => 'FLUSH',
            'type' => Submit::class,
            'attributes' => [
                'class' => 'btn btn-primary mt-2',
                'id' => 'FLUSH',
                'value' => 'Flush',
            ],
        ]);
        $view->setVariable('flushForm', $flush);
        
        /**
         * Legacy Import Form
         * Processes records line by line upon import.  Deprecated, use Upload/Process/Import.
         * @var \Components\Form\UploadFileForm $importForm
         */
        $importForm = new UploadFileForm('TELESTAFF');
        $importForm->init();
        $view->setVariable('importForm', $importForm);
        
        /**
         * Upload Form
         * @var \Components\Form\UploadFileForm $uploadForm
         */
        $uploadForm = new UploadFileForm('UPLOAD');
        $uploadForm->init();
        $view->setVariable('uploadForm', $uploadForm);
        
        return $view;
    }
    
    public function uploadAction()
    {
        ini_set('auto_detect_line_endings',TRUE);
        $this->logger->info('Started Telestaff Upload');
        
        /****************************************
         * Column Descriptions
         ****************************************/
        $NAME = 0;
        $PYID = 1;
        $EMID = 2;
        $CODE = 3;
        $HOUR = 4;
        $DATE = 5;
        $ACCT = 6;
        $DETC = 7;
        $NOTE = 8;
        
        /****************************************
         * Generate Form
         ****************************************/
        $request = $this->getRequest();
        
        $form = new UploadFileForm('UPLOAD');
        $form->init();
        $form->addInputFilter();
        
        if ($request->isPost()) {
            $data = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
                );
            
            $form->setData($data);
            
            if ($form->isValid()) {
                $data = $form->getData();
                if (($handle = fopen($data['FILE']['tmp_name'],"r")) !== FALSE) {
                    fgets($handle); //-- Skip Header --//
                    while (($record = fgetcsv($handle, NULL, ",")) !== FALSE) {
                        $entry = new TelestaffModel($this->adapter);
                        $entry->NAME = $record[$NAME];
                        $entry->PYID = $record[$PYID];
                        $entry->EMID = $record[$EMID];
                        $entry->CODE = $record[$CODE];
                        $entry->HOUR = $record[$HOUR];
                        $entry->DATE = $record[$DATE];
                        $entry->ACCT = $record[$ACCT];
                        $entry->DETC = $record[$DETC];
                        $entry->NOTE = $record[$NOTE];
                        $entry->create();
                    }
                }
            } else {
                $this->flashmessenger()->addErrorMessage("Form is Invalid.");
            }
            
            $this->logger->info('Stopped Telestaff Upload');
            
            $url = $this->getRequest()->getHeader('Referer')->getUri();
            return $this->redirect()->toUrl($url);
        }
    }
    
    public function clearDatabase()
    {
        $sql = new Sql($this->adapter);
        $ddl = [];
        
        $ddl[] = new DropTable('update_telestaff');
        
        foreach ($ddl as $obj) {
            try {
                $this->adapter->query($sql->buildSqlString($obj), $this->adapter::QUERY_MODE_EXECUTE);
            } catch (InvalidQueryException $e) {
                $this->flashMessenger()->addErrorMessage($e->getMessage());
            }
        }
        
        $this->clearSettings('TELESTAFF');
    }

    public function createDatabase()
    {
        /******************************
         * Temporary Table
         ******************************/
        $ddl = new CreateTable('update_telestaff');
        $ddl = $this->addStandardFields($ddl);
        
        $ddl->addColumn(new Varchar('NAME', 255, TRUE));
        $ddl->addColumn(new Varchar('PYID', 255, TRUE));
        $ddl->addColumn(new Varchar('EMID', 255, TRUE));
        $ddl->addColumn(new Varchar('CODE', 255, TRUE));
        $ddl->addColumn(new Varchar('HOUR', 255, TRUE));
        $ddl->addColumn(new Varchar('DATE', 255, TRUE));
        $ddl->addColumn(new Varchar('ACCT', 255, TRUE));
        $ddl->addColumn(new Varchar('DETC', 255, TRUE));
        $ddl->addColumn(new Varchar('NOTE', 255, TRUE));
        
        $ddl->addConstraint(new PrimaryKey('UUID'));
        
        $this->processDdl($ddl);
        unset($ddl);
    }
    
    public function processAction()
    {
        $view = new ViewModel();
        
        $this->layout('layout/api-layout');
        
        $view->setTemplate('application/telestaff/api-response');
        $object = new TelestaffModel($this->adapter);
        
        $notEmpty = new NotEmpty();
        $positive = new GreaterThan(['min' => 0]);
        
        $object->read(['STATUS' => TelestaffModel::ACTIVE_STATUS]);
        switch (true) {
            case !$notEmpty->isValid($object->ACCT):
            case !$positive->isValid($object->HOUR):
                $object->STATUS = TelestaffModel::INVALID_STATUS;
                break;
                
            /**
             * Remove Holiday Codes
             */
            case $object->CODE == 'HOL':
                $object->CODE = '001';
                $object->STATUS = TelestaffModel::VALID_STATUS;
                break;
            
            /**
             * Convert 8.5 hours to 8 hours
             */
            case $object->HOUR == '8.5':
                $object->HOUR = 8;
                $object->STATUS = TelestaffModel::VALID_STATUS;
                break;
            default:
                $object->STATUS = TelestaffModel::VALID_STATUS;
                break;
        }
        $object->update();
        
        $activeStatus = new Where();
        $activeStatus->equalTo('STATUS', TelestaffModel::ACTIVE_STATUS);
        
        $all = $object->fetchAll($activeStatus);
        
        $response = [
            'count' => count($all),
            'total' => 276,
        ];
        
        $view->setVariable('response', $response);
        return $view;
    }

    public function flushAction()
    {
        $object = new TelestaffModel($this->adapter);
        
        $sql = new Sql($this->adapter);
        
        $delete = new Delete();
        $delete->from($object->getTableName());
        $statement = $sql->prepareStatementForSqlObject($delete);
        
        try {
            $statement->execute();
        } catch (ErrorException $e) {
            
        }
        
        $url = $this->getRequest()->getHeader('Referer')->getUri();
        return $this->redirect()->toUrl($url);
    }

    public function importAction()
    {
        $view = new ViewModel();
        $this->layout('layout/api-layout');
        $view->setTemplate('application/telestaff/api-response');
        $response = [];
        
        $object = new TelestaffModel($this->adapter);
        $object->read(['STATUS' => TelestaffModel::VALID_STATUS]);
        
        /**
         * Retrieve Paycode
         * @var \Timecard\Model\PaycodeModel $paycode
         */
        $paycode = new PaycodeModel($this->adapter);
        if (!$paycode->read(['CODE' => $object->CODE])) {
            $this->logger->err("Paycode does not exist: " . $object->CODE);
            return $view;
        }
        
        /**
         * Retrieve Employee
         * @var \Employee\Model\EmployeeModel $employee
         */
        $employee = new EmployeeModel($this->adapter);
        if (!$employee->read(['EMP_NUM' => sprintf('%06d', $object->EMID)])) {
            $this->logger->err("Unable to find employee " . $object->EMID);
            return $view;
        }
        
        /**
         * Retrieve Timecard
         * @var \Timecard\Model\Entity\TimecardEntity $timecard
         */
        $timecard = new TimecardEntity();
        $timecard->setDbAdapter($this->adapter);
        $timecard->EMP_UUID = $employee->UUID;
        $timecard->WORK_WEEK = $this->getEndofWeek($object->DATE);
        if (!$timecard->getTimecard()) {
            $timecard->createTimecard();
            $timecard->getTimecard();
        }
        
        if ($timecard->STATUS >= TimecardModel::SUBMITTED_STATUS) {
            //-- Do not make modifications to Timecards that have already been submitted, or reviewed. --//
            $message = sprintf('Timecard for %s already has a status of %s and cannot be updated.', $object->EMID, TimecardModel::retrieveStatus($timecard->STATUS));
            $this->flashmessenger()->addErrorMessage($message);
            $this->logger->err($message);
            return $view;
        }
        
        
        /**
         * Create Timecard Lines
         * @var \Timecard\Model\TimecardLineModel $tcl
         */
        $tcl = new TimecardLineModel($this->adapter);
        $day = $this->DAYS[date('w', strtotime($object->DATE))];
        $restcl = $tcl->read(['PAY_UUID' => $paycode->UUID, 'TIMECARD_UUID' => $timecard->TIMECARD_UUID]);
        if ($restcl) {
            $tcl->$day += $object->HOUR;
            $tcl->update();
        } else {
            $tcl->TIMECARD_UUID = $timecard->TIMECARD_UUID;
            $tcl->$day = $object->HOUR;
            $tcl->PAY_UUID = $paycode->UUID;
            $tcl->WORK_WEEK = $timecard->WORK_WEEK;
            $tcl->create();
        }
        
        /**
         * Create Annotations
         * @var AnnotationModel $annotation
         */
        if ($object->NOTE || $object->DETC) {
            $annotation = new AnnotationModel($this->adapter);
            $annotation->TABLENAME = $timecard->annotations_tablename;
            $annotation->PRIKEY = $timecard->TIMECARD_UUID;
            $annotation->ANNOTATION = sprintf('%s - %s - %s',$object->DATE,$object->DETC,$object->NOTE);
            $annotation->USER = 'SYSTEM';
            $annotation->create();
            unset($annotation);
        }
        
        /**
         * Remove Temporary Object
         */
        $object->delete();
        
        $valid_objects = new Where();
        $valid_objects->equalTo('STATUS', TelestaffModel::VALID_STATUS);
        
        $records = $object->fetchAll($valid_objects);
        $count = count($records);
        
        $response = [
            'count' => $count,
            'total' => 276,
        ];
        
        $view->setVariable('response', $response);
        return $view;
    }
}