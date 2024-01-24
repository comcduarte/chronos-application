<?php
namespace Application\Controller;

use Laminas\Box\API\AccessTokenAwareTrait;
use Laminas\Box\API\Role;
use Laminas\Box\API\Resource\ClientError;
use Laminas\Box\API\Resource\Collaboration;
use Laminas\Box\API\Resource\File;
use Laminas\Box\API\Resource\Folder;
use Laminas\Box\API\Resource\User;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Settings\Model\SettingsModel;

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
}