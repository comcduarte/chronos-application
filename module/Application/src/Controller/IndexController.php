<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Entity\UserEntity;
use Files\Model\FilesModel;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Http\Request as HttpRequest;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Exception;
use Files\Traits\FilesAwareTrait;

class IndexController extends AbstractActionController
{
    use AdapterAwareTrait;
    use FilesAwareTrait;
    
    public function indexAction()
    {
        $view = new ViewModel();
        return $view;
    }
    
    public function cronAction()
    {
        $request = $this->getRequest();
        $view = new ViewModel();
        
        $num_files = 999999;
        $runtime = 25; // seconds
        
        /******************************
         * Process Files in Queue
         ******************************/
        $fi = new \FilesystemIterator('./data/queue/', \FilesystemIterator::SKIP_DOTS);
        
        while ($fi->valid() && $num_files > 0) {
            $start_time = microtime(TRUE);
            
            $filename = $fi->getFileName();
            $filepath = './data/queue/' . $filename;
            $matches = [];
            
            $user_entity = new UserEntity($this->adapter);
            
            /******************************
             * Filter out W2 Forms
             * i.e.  W2_2018_1_007440_1902111135.pdf
             ******************************/
            preg_match('/.*\_(\d*)\_\d*/', $filename, $matches);
            if (!$user_entity->employee->read(['EMP_NUM' => $matches[1]])) {
                //** Error **//
                $this->flashMessenger()->addErrorMessage('Unable to read by EMP_NUM: ' . $matches[1]);
                $fi->next();
                continue;
            }
            
            if (!$user_entity->getEmployee($user_entity->employee->UUID)) {
                //** Error **//
                $this->flashMessenger()->addErrorMessage('Unable to get Employee: ' . $user_entity->employee->UUID);
                $fi->next();
                continue;
            }
            
            $files = new FilesModel();
            $files->setDbAdapter($this->getFilesAdapter());
            $files->NAME = $filename;
            $files->SIZE = filesize($filepath);
            $files->TYPE = mime_content_type($filepath);
            $files->REFERENCE = $user_entity->employee->UUID;
            $files->BLOB = file_get_contents($filepath);
            
            try {
                $files->create();
            } catch (Exception $e) {
                return FALSE;
            }
            
            unlink($filepath);
            
            $end_time = microtime(TRUE);
            
            if ($num_files == 999999) {
                $num_files = $runtime / ($end_time - $start_time);
                $this->flashMessenger()->addInfoMessage('Recommended number of files to process: ' . $num_files);
            } else {
                $num_files--;
            }
            $fi->next();
        }
        
        if ($request instanceof HttpRequest) {
            $url = $this->getRequest()->getHeader('Referer')->getUri();
            return $this->redirect()->toUrl($url);
        }
        
    }
}
