<?php
namespace Application\Controller;

use Employee\Model\DepartmentModel;
use Employee\Model\EmployeeModel;
use Laminas\Box\API\AccessTokenAwareTrait;
use Laminas\Box\API\MetadataQuery;
use Laminas\Box\API\Resource\ClientError;
use Laminas\Box\API\Resource\File;
use Laminas\Box\API\Resource\Folder;
use Laminas\Box\API\Resource\Items;
use Laminas\Box\API\Resource\MetadataInstance;
use Laminas\Box\API\Resource\MetadataQuerySearchResults;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Identical;
use Laminas\View\Model\ViewModel;
use Leave\Controller\LeaveController;
use Settings\Model\SettingsModel;
use Timecard\Model\TimecardLineModel;
use Timecard\Model\TimecardModel;
use Timecard\Model\TimecardSignatureModel;
use Timecard\Model\TimecardStageModel;
use Timecard\Model\Warrant;
use Timecard\Model\Entity\TimecardEntity;
use Exception;

class CronController extends AbstractActionController
{
    use AccessTokenAwareTrait;
    
    public $employee_adapter;
    public $timecard_adapter;
    
    /**
     * 
     * @var Logger
     */
    public $logger;
    
    public function cronAction()
    {
        /**
         * Parse Files Queue Folder
         */
        $this->parseFiles();
        
        $this->forward()->dispatch(LeaveController::class, ['action' => 'cron']);
        
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
            'SHIFT_CODE' => 'SHIFT_CODE',
            'SHIFT_CODE_DESC' => 'SHIFT_CODE_DESC'
        ]);
        
        
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultSet = new ResultSet();
        
        try {
            $results = $statement->execute();
            $resultSet->initialize($results);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            $this->logger->err($e->getMessage());
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
                        $this->logger->err($e->getMessage());
                    }
                }
                
            } else {
                /**
                 * New Employee
                 */
                foreach ($record as $field => $value) {
                    if ($field == 'DEPT' ) {
                        if ( $department->read(['CODE' => str_pad($value, 5, 0, STR_PAD_RIGHT)]) ) {
                            $value = $department->UUID;
                        } else {
                            continue;
                        }
                    }
                    $employee->$field = $value;
                }
                
                $message = sprintf('Create new user %s %s [%s]', $employee->FNAME, $employee->LNAME, $employee->EMP_NUM);
                $messages[] = $message;
                $this->logger->info($message);
                
                try {
                    $employee->create();
                } catch (Exception $e) {
                    $messages[] = $e->getMessage();
                    $this->logger->err($e->getMessage());
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
                $this->logger->err($e->getMessage());
            }
        }
        
        $view->setVariable('messages', json_encode($messages));
        return $view;
    }

    /**
     * Function will read all file names in queue folder (specified) and move to appropriate subfolders,
     * attaching metadata in the process.
     */
    public function parseFiles()
    {
        /**
         * Global Variables
         */
        $scope = 'enterprise_563960266';
        $template_key = 'webappReference';
        
        $settings = new SettingsModel($this->timecard_adapter);
        $settings->read(['MODULE' => 'BOX','SETTING' => 'QUEUE_FOLDER_ID']);
        $queue_folder_id = $settings->VALUE;
        
        $settings->read(['MODULE' => 'BOX','SETTING' => 'APP_FOLDER_ID']);
        $app_folder_id = $settings->VALUE;
        
        $folder = new Folder($this->access_token);
        
        /**
         * Get list of Employee Folders
         * @var Items $app_items
         */
        $app_items = $folder->list_items_in_folder($app_folder_id);
        
        /**
         * Get list of Files in Queue
         * @var Items $items
         */
        $items = $folder->list_items_in_folder($queue_folder_id);
        foreach ($items->entries as $item) {
            if ($item['type'] != "file") {
                /**
                 * Skip Folders
                 */
                continue;
            }
            $file = new File($this->access_token);
            $file->get_file_information($item['id']);
            
            $matches = [];
            if (preg_match('/^CITZ(\d{6})(\d{7})(\d{6})/', $file->name, $matches)) {
                $warrant_num = $matches[1];
                $emp_num = $matches[3];
                
                
                
                /**
                 * Search
                 * Iteration only goes through 100 entries max.
                 *
                $search = new Search($this->access_token);
                $search->query("$emp_num&type=folder");
                $search_results = $search->search_for_content();
                
                foreach ($search_results->entries as $emp_folder) {
                    if ($emp_folder['name'] == $emp_num) {
                        /**
                         * Found Employee Folder ID
                         *
                         $emp_folder_id = $emp_folder['id'];
                         $this->logger->info(sprintf('Found folder for %s [%s]', $emp_num, $emp_folder_id));
                         break;
                     } 
                }*/       
                
                /**
                 * Metadata Search
                 * Find employee folder based on employee uuid.
                 */
                $emp_folder_id = null;
                
                $employee = new EmployeeModel($this->employee_adapter);
                $employee->read(['EMP_NUM' => $emp_num]);
                
                $metadata_query = new MetadataQuery($this->access_token);
                $metadata_query_search_results = $metadata_query->metadata_query(
                    (string) $app_folder_id,
                    'enterprise_563960266.webappReference',
                    "referenceUuid = :uuid",
                    ['uuid' => $employee->UUID],
                    );
                if ($metadata_query_search_results instanceof ClientError) {
                    /**
                     * @var ClientError $metadata_query_search_results
                     */
                    $this->logger->err(sprintf('[%s] %s (%s)', $metadata_query_search_results->status, $metadata_query_search_results->message, $emp_num));
                } 
                
                /**
                 * @var MetadataQuerySearchResults $metadata_query_search_results
                 * @var File|Folder $item
                 */
                foreach ($metadata_query_search_results->entries as $item) {
                    if ($item['type'] == 'folder' && $item['name'] == $emp_num) {
                        $emp_folder_id = $item['id'];
                    }
                }
                
                
                $paystub_folder_id = null;
                if (is_null($emp_folder_id)) {
                    /**
                     * Create new Employee Folder
                     */
                    $folder = $folder->create_folder($app_folder_id,$emp_num);
                    $emp_folder_id = $folder->id;
                    
                    $metadata = new MetadataInstance($this->access_token);
                    $data = [
                        'referenceUuid' => $employee->UUID,
                    ];
                    $result = $metadata->create_metadata_instance_on_folder($emp_folder_id, $scope, $template_key, $data);
                    
                    if ($result instanceof ClientError) {
                        $this->logger->err(sprintf('[%s] %s (%s)', $result->status, $result->message, $emp_num));
                    }
                    
                    /**
                     * Create PAYSTUB Folder
                     */
                    $folder = $folder->create_folder($emp_folder_id, 'PAYSTUBS');
                    $paystub_folder_id = $folder->id;
                    
                    $this->logger->info(sprintf('Created folder structure for %s [%s]', $emp_num, $emp_folder_id));
                } else {
                    $folder = $folder->get_folder_information($emp_folder_id);
                    foreach ($folder->item_collection['entries'] as $x) {
                        if ($x['name'] == 'PAYSTUBS') {
                            $paystub_folder_id = $x['id'];
                            break;
                        }
                    }
                    
                    if (is_null($paystub_folder_id)) {
                        $folder = $folder->create_folder($emp_folder_id, 'PAYSTUBS');
                        $paystub_folder_id = $folder->id;
                        $this->logger->info(sprintf('Created Paystub folder for %s [%s]', $emp_num, $paystub_folder_id));
                    }
                }
                
                /**
                 * Assign Metadata Reference
                 */
                $warrant = new Warrant($this->timecard_adapter);
                if (! $warrant->read(['WARRANT_NUM' => $warrant_num])) {
                    /**
                     * Leave item in Queue if warrant is not entered.
                     */
                    $this->logger->err(sprintf('Unable to retrieve warrant %s.', $warrant_num));
                    continue;
                }
                
                /**
                 * Move PDF to PAYSTUB folder
                 * @todo file turns into error, delete file if dup
                 */
                $retval = $file->move_file($file->id, $paystub_folder_id);
                if ($retval instanceof ClientError) {
                    $this->logger->err(sprintf($retval->message . ' [%s]', $emp_num));
                    if ($retval->item_status = '409') {
                        $file->delete_file($file->id);
                        $this->logger->info(sprintf('Deleted duplicate file id [%s', $file->id));
                    }
                    continue;
                }
                
                $timecard = new TimecardEntity();
                $timecard->setDbAdapter($this->timecard_adapter);
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
                    $timecard_model = new TimecardModel($this->timecard_adapter);
                    $timecard_model->read(['UUID' => $timecard->TIMECARD_UUID]);
                    $timecard_model->STATUS = $timecard_model::COMPLETED_STATUS;
                    $timecard_model->update();
                    unset ($timecard_model);
                    
                    $line = new TimecardLineModel($this->timecard_adapter);
                    $line->read(['TIMECARD_UUID' => $timecard->TIMECARD_UUID]);
                    $line->STATUS = $line::COMPLETED_STATUS;
                    $line->update();
                    unset($line);
                    
                    
                    /****************************************
                     * GET TIMECARD STAGE
                     ****************************************/
                    $stage = new TimecardStageModel($this->timecard_adapter);
                    $stage->read(['SEQUENCE' => TimecardModel::COMPLETED_STATUS]);
                    
                    /****************************************
                     * SET TIMECARD SIGNATURE
                     ****************************************/
                    $signature = new TimecardSignatureModel($this->timecard_adapter);
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
}