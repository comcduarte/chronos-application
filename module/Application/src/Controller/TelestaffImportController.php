<?php
namespace Application\Controller;

use Annotation\Model\AnnotationModel;
use Components\Controller\AbstractConfigController;
use Components\Form\UploadFileForm;
use Employee\Model\EmployeeModel;
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
        $importForm = new UploadFileForm('TELESTAFF');
        $importForm->init();
        $view->setVariable('importForm', $importForm);
        $view->setTemplate('telestaff/config');
        return $view;
    }
    
    public function importAction()
    {
        ini_set('auto_detect_line_endings',TRUE);
        $this->logger->info('Started Telestaff Import');
        
        /****************************************
         * Column Descriptions
         ****************************************/
        //$NAME = 0;
        //$PYID = 1;
        $EMID = 2;
        $CODE = 3;
        $HOUR = 4;
        $DATE = 5;
        //$ACCT = 6;
        $DETC = 7;
        $NOTE = 8;
        
        $dow = ['SUN','MON','TUE','WED','THU','FRI','SAT'];
        
        /****************************************
         * Generate Form
         ****************************************/
        $request = $this->getRequest();
        
        $form = new UploadFileForm();
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
                    while (($record = fgetcsv($handle, NULL, ",")) !== FALSE) {
                        /****************************************
                         * Corrections
                         ****************************************/
                        if ($record[$CODE] == 'HOL') { $record[$CODE] = '001'; }
                        
                        $pc = new PaycodeModel($this->timecard_adapter);
                        $respc = $pc->read(['CODE' => $record[$CODE]]);
                        if (!$respc) {
                            $this->logger->err("Paycode does not exist: " . $record[$CODE]);
                            continue;
                        }
                        
                        if ($pc->ACCRUAL || $record[$CODE] == '001') {
                            if ($record[$HOUR] == 8.5) { $record[$HOUR] = 8; }
                        }
                        
                        /****************************************
                         * Employees
                         ****************************************/
                        $emp = new EmployeeModel($this->employee_adapter);
                        $result = $emp->read(['EMP_NUM' => sprintf('%06d', $record[$EMID])]);
                        if ($result === FALSE) {
                            //-- Unable to Find Employee
                            $this->flashmessenger()->addErrorMessage("Unable to find employee " . $record[$EMID]);
                            $this->logger->err("Unable to find employee " . $record[$EMID]);
                            continue;
                        } else {
                            //-- Found Employee
                        }
                        
                        /****************************************
                         * Timecard
                         ****************************************/
                        $timecard = new TimecardEntity();
                        $timecard->setDbAdapter($this->timecard_adapter);
                        $timecard->EMP_UUID = $emp->UUID;
                        $timecard->WORK_WEEK = $this->getEndofWeek($record[$DATE]);
                        if (!$timecard->getTimecard()) {
                            $timecard->createTimecard();
                            $timecard->getTimecard();
                        }
                        
                        if ($timecard->STATUS >= TimecardModel::SUBMITTED_STATUS) {
                            //-- Do not make modifications to Timecards that have already been submitted, or reviewed. --//
                            $message = sprintf('Timecard for %s already has a status of %s and cannot be updated.', $record[$EMID], TimecardModel::retrieveStatus($timecard->STATUS));
                            $this->flashmessenger()->addErrorMessage($message);
                            $this->logger->err($message);
                            continue;
                        }
                        
                        /****************************************
                         * Timecard Lines
                         ****************************************/
                        $day = $dow[date('w', strtotime($record[$DATE]))];
                        
                        $tcl = new TimecardLineModel($this->adapter);
                        $restcl = $tcl->read(['PAY_UUID' => $pc->UUID, 'TIMECARD_UUID' => $timecard->TIMECARD_UUID]);
                        if ($restcl) {
                            $tcl->$day += $record[$HOUR];
                            $tcl->update();
                        } else {
                            $tcl->TIMECARD_UUID = $timecard->TIMECARD_UUID;
                            $tcl->$day = $record[$HOUR];
                            $tcl->PAY_UUID = $pc->UUID;
                            $tcl->WORK_WEEK = $timecard->WORK_WEEK;
                            $tcl->create();
                        }
                        
                        /****************************************
                         * Annotations
                         ****************************************/
                        if ($record[$NOTE] || $record[$DETC]) {
                            $annotation = new AnnotationModel($this->timecard_adapter);
                            $annotation->TABLENAME = $timecard->annotations_tablename;
                            $annotation->PRIKEY = $timecard->TIMECARD_UUID;
                            $annotation->ANNOTATION = sprintf('%s - %s - %s',$record[$DATE],$record[$DETC],$record[$NOTE]);
                            $annotation->USER = 'SYSTEM';
                            $annotation->create();
                            unset($annotation);
                        }
                        
                        
                        unset ($tcl);
                        unset ($timecard);
                        unset ($emp);
                    }
                    fclose($handle);
                    unlink($data['FILE']['tmp_name']);
                } 
                $this->flashMessenger()->addSuccessMessage("Successfully imported employees.");
            } else {
                $this->flashmessenger()->addErrorMessage("Form is Invalid.");
            }
            
            $this->logger->info('Stopped Telestaff Import');
            
            $url = $this->getRequest()->getHeader('Referer')->getUri();
            return $this->redirect()->toUrl($url);
        }
    }
    
    public function clearDatabase()
    {}

    public function createDatabase()
    {}

}