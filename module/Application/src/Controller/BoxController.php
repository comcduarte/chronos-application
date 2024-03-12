<?php
namespace Application\Controller;

use Employee\Model\EmployeeModel;
use Laminas\Box\API\AccessTokenAwareTrait;
use Laminas\Box\API\Role;
use Laminas\Box\API\Resource\ClientError;
use Laminas\Box\API\Resource\Collaboration;
use Laminas\Box\API\Resource\File;
use Laminas\Box\API\Resource\Folder;
use Laminas\Box\API\Resource\MetadataInstance;
use Laminas\Box\API\Resource\Query;
use Laminas\Box\API\Resource\User;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Form\Form;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Settings\Model\SettingsModel;
use Timecard\Model\TimecardLineModel;
use Timecard\Model\TimecardModel;
use Timecard\Model\TimecardSignatureModel;
use Timecard\Model\TimecardStageModel;
use Timecard\Model\Warrant;
use Timecard\Model\Entity\TimecardEntity;

class BoxController extends AbstractActionController
{
    use AdapterAwareTrait;
    use AccessTokenAwareTrait;
    
    public function configAction()
    {
        $view = new ViewModel();
        $view->setTemplate('application/config/index');
        
        $folder = new Folder($this->access_token);
        $folder->get_folder_information('0');
        $view->setVariable('folder', $folder->getResponse());
        
        /**
         * Check if Application Folder is present by name.
         * If so, store the ID in Settings
         */
        $settings = new SettingsModel($this->adapter);
        $settings->read(['MODULE' => 'BOX','SETTING' => 'APP_FOLDER_NAME']);
        if ($settings->VALUE == null) {
            $this->flashMessenger()->addErrorMessage('APP_FOLDER_NAME not present.');
            return $view;
        }
        
        /**
         * If Application Folder is Empty, create folder.
         */
        $items = $folder->list_items_in_folder('0');
        if ($items->total_count == 0) {
            $app_folder = $folder->create_folder('0', $settings->VALUE);
            $settings->read(['MODULE' => 'BOX', 'SETTING' => 'APP_FOLDER_ID']);
            $settings->VALUE = $app_folder->id;
            $settings->update();
        } else {
            $settings->read(['MODULE' => 'BOX','SETTING' => 'APP_FOLDER_ID']);
            $app_folder = $folder->get_folder_information($settings->VALUE);
        }
        $folder->get_folder_information($app_folder->id);
        $view->setVariable('app_folder', $folder->getResponse());
        
        /**
         * Add Collaborators
         * Administrators that will have root level ownership to application folder.
         * @var User $user
         */
        $result = $settings->read(['MODULE' => 'BOX','SETTING' => 'APP_COLLABORATOR']);
        if (!$result) {
            $this->flashmessenger()->addErrorMessage('APP_COLLABORATOR is not present.');
            return $view;
        }
        
        /**
         * Is collaborator already set.
         */
        $collaborations = $app_folder->listFolderCollaborations($app_folder->id);
        $view->setVariable('collaborations', $app_folder->getResponse());
        
        $user = new User($this->access_token);
        $user->login = $settings->VALUE;
        
        /**
         * @var Collaboration $collaboration
         */
        foreach ($collaborations->entries as $collaboration) {
            if ($collaboration->accessible_by['login'] == $user->login) {
                $this->flashMessenger()->addInfoMessage('Collaborator already set.');
                return $view;
            }
        }
        
        $item = $folder->get_folder_information($app_folder->id);
        $role = Role::CO_OWNER;
        
        $collaboration = new Collaboration($this->access_token);
        
        $result = $collaboration->create_collaboration($user, $item, $role);
        if ($result instanceof ClientError) {
            /**
             * @var ClientError $result
             */
            $this->flashmessenger()->addErrorMessage($result->message);
        }
        
        
        return $view;
    }

    public function viewAction()
    {
        $this->layout('files_layout');
        
        $file_id = $this->params()->fromRoute('id', 0);
        if (! $file_id) {
            $this->flashmessenger()->addErrorMessage('Did not pass identifier.');
            
            // -- Return to previous screen --//
            $url = $this->getRequest()->getHeader('Referer')->getUri();
            return $this->redirect()->toUrl($url);
        }
        
        $view = new ViewModel();
        $view->setTemplate('files_view');
        
        $file = new File($this->getAccessToken());
        $content = $file->download_file($file_id);
        $view->setVariable('data', $content->getContent());
        
        /**
         *
         * @var File $info
         */
        $info = $file->get_file_information($file_id);
        $view->setVariable('TYPE', $info->type);
        $view->setVariable('NAME', $info->name);
        $view->setVariable('SIZE', $info->size);
        
        return $view;
    }

    /**
     * Associate Box Files to Timecards
     * @return \Laminas\View\Model\ViewModel
     */
    public function associateAction()
    {
        $view = new ViewModel();
        $employee = new EmployeeModel($this->adapter);
        
        /****************************************
         * Generate Form
         ****************************************/
        $form = new Form();
        $form->add([
            'name' => 'EMP_NUM',
            'type' => Text::class,
            'attributes' => [
                'id' => 'EMP_NUM',
                'class' => 'form-control',
                'required' => 'true',
            ],
            'options' => [
                'label' => 'Employee Number',
            ],
        ]);
        $form->add(new Csrf('SECURITY'));
        $form->add([
            'name' => 'SUBMIT',
            'type' => Submit::class,
            'attributes' => [
                'value' => 'Search',
                'class' => 'btn btn-primary mt-2 me-2',
                'id' => 'SUBMIT',
            ],
        ],['priority' => 0]);
        
        $form->add([
            'name' => 'ASSOCIATE',
            'type' => Submit::class,
            'attributes' => [
                'value' => 'Associate',
                'class' => 'btn btn-primary mt-2 me-2',
                'id' => 'ASSOCIATE',
            ],
        ],['priority' => 0]);
        
        $view->setVariable('form', $form);
        
        /****************************************
         * Employee Model
         ****************************************/
        $view->setVariable('employee', $employee);
        
        /****************************************
         * Process Submission
         ****************************************/
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = array_merge_recursive(
                $request->getPost()->toArray(),
                );
            
            $form->setData($data);
            
            if ($form->isValid()) {
                $employee->read(['EMP_NUM' => $data['EMP_NUM']]);
                
                switch (true) {
                    case isset($data['ASSOCIATE']):
                        $this->flashmessenger()->addInfoMessage('Associated');
                        break;
                }
            } else {
                $this->flashmessenger()->addErrorMessage("Form is Invalid.");
            }
        } else {
            return $view;
        }
        
        /****************************************
         * Find Employee Box Folder
         ****************************************/
//         $scope = 'enterprise_563960266';
//         $template_key = 'webappReference';
        
        $settings = new SettingsModel($this->adapter);
        $settings->read(['MODULE' => 'BOX','SETTING' => 'APP_FOLDER_ID']);
        $app_folder_id = $settings->VALUE;
        
        $query = new Query();
        $query->limit = 1000;
        
        $folder = new Folder($this->access_token);
        $items = $folder->list_items_in_folder($app_folder_id, $query);
        $view->setVariable('folders', $items);
        
        $emp_folder_id = false;
        foreach ($items->entries as $entry) {
            if ($entry['name'] != $employee->EMP_NUM) {
                continue;
            }
            $emp_folder_id = $entry['id'];
            break;
        }
        $view->setVariable('emp_folder_id', $emp_folder_id);
        
        /****************************************
         * List Files in PAYSTUBS
         ****************************************/
        $items = $folder->list_items_in_folder($emp_folder_id, $query);
        
        $paystub_folder_id = false;
        foreach ($items->entries as $entry) {
            if ($entry['name'] != 'PAYSTUBS') {
                continue;
            }
            $paystub_folder_id = $entry['id'];
            break;
        }
        
        $items = $folder->list_items_in_folder($paystub_folder_id, $query);
        
        $view->setVariable('num_files', $items->total_count);
        
        /****************************************
         * Find Files with no Metadata
         ****************************************/
        $with = 0;
        $without = 0;
        foreach ($items->entries as $entry) {
            $file_id = $entry['id'];
            $metadata_instance = new MetadataInstance($this->access_token);
            $metadata_instances = $metadata_instance->list_metadata_instances_on_file($file_id);
            if (sizeof($metadata_instances->entries)) {
                $with++;
                if (isset($data['REASSOCIATE'])) {
                    /****************************************
                     * Find / Create Timecard
                     ****************************************/
                } 
            } else {
                $without++;
                
                if (isset($data['ASSOCIATE'])) {
                    $this->associate($file_id);
                }
            }
        }
        
        $view->setVariables([
            'with' => $with,
            'without' => $without,
        ]);
        
        return $view;
    }
    
    private function associate($file_id)
    {
        $file = new File($this->access_token);
        $file->get_file_information($file_id);
        
        $matches = [];
        if (preg_match('/^CITZ(\d{6})(\d{7})(\d{6})/', $file->name, $matches)) {
            $warrant_num = $matches[1];
            $emp_num = $matches[3];
            
            /**
             * Assign Metadata Reference
             */
            $warrant = new Warrant($this->adapter);
            if (! $warrant->read(['WARRANT_NUM' => $warrant_num])) {
                /**
                 * Leave item in Queue if warrant is not entered.
                 */
                $this->logger->err(sprintf('Unable to retrieve warrant %s.', $warrant_num));
            }
            
            $employee = new EmployeeModel($this->adapter);
            $employee->read(['EMP_NUM' => $emp_num]);
            
            $timecard = new TimecardEntity();
            $timecard->setDbAdapter($this->adapter);
            $timecard->WORK_WEEK = $warrant->WORK_WEEK;
            $timecard->EMP_UUID = $employee->UUID;
            if (! $timecard->getTimecard() ) {
                /**
                 * Original timesheet was never created. Create blank timesheet to reference paystub.
                 */
                $timecard->createTimecard();
                $timecard->getTimecard();
                
                /**
                 * Complete Timecard
                 */
                $timecard_model = new TimecardModel($this->adapter);
                $timecard_model->read(['UUID' => $timecard->TIMECARD_UUID]);
                $timecard_model->STATUS = $timecard_model::COMPLETED_STATUS;
                $timecard_model->update();
                unset ($timecard_model);
                
                $line = new TimecardLineModel($this->adapter);
                $line->read(['TIMECARD_UUID' => $timecard->TIMECARD_UUID]);
                $line->STATUS = $line::COMPLETED_STATUS;
                $line->update();
                unset($line);
                
                
                /****************************************
                 * GET TIMECARD STAGE
                 ****************************************/
                $stage = new TimecardStageModel($this->adapter);
                $stage->read(['SEQUENCE' => TimecardModel::COMPLETED_STATUS]);
                
                /****************************************
                 * SET TIMECARD SIGNATURE
                 ****************************************/
                $signature = new TimecardSignatureModel($this->adapter);
                $signature->TIMECARD_UUID = $timecard->TIMECARD_UUID;
                $signature->USER_UUID = 'SYSTEM';
                $signature->STAGE_UUID = $stage->UUID;
                $signature->create();
                
                $this->logger->info(sprintf('Completed timecard for %s for week ending %s', $emp_num, $warrant->WORK_WEEK));
            }
            
            $data = [
                'referenceUuid' => $timecard->TIMECARD_UUID,
            ];
            
            $template_key = 'webappReference';
            
            $metadata_instance = new MetadataInstance($this->getAccessToken());
            $retval = $metadata_instance->create_metadata_instance_on_file($file->id, $this->getAccessToken()->box_subject_type . '_' . $this->getAccessToken()->box_subject_id, $template_key, $data);
            
            if ($retval instanceof ClientError) {
                $this->logger->err(sprintf($retval->message . ' [%s] [%s] context-info: %s', $file->name, $emp_num, $retval->context_info));
            }
        }
    }
    
}