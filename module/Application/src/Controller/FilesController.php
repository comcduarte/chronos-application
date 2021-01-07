<?php
namespace Application\Controller;

use Files\Form\FilesUploadForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class FilesController extends AbstractActionController
{
    public function uploadAction()
    {
        $view = new ViewModel();
        
        @ini_set('upload_max_filesize', '8K');
        
        $form = new FilesUploadForm();
        $form->init();
        $form->addInputFilter();
        
        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
                );
            $form->setData($post);
            
            if ($form->isValid()) {
                $data = $form->getData();
                $filename = $data['FILE']['tmp_name'];
                
                $zip = new \ZipArchive();
                $res = $zip->open($filename);
                if ($res === TRUE) {
                    $zip->extractTo('./data/queue/');
                    $zip->close();
                    
                    /**
                     * Check to see if files exists and then delete zip file
                     */
                    unlink($filename);
                } else {
                    $this->flashMessenger()->addErrorMessage('Unable to open zip file');
                }
            }
        }
        
        $view->setVariable('form', $form);
        
        
        /**
         * Review how many files are in the queue.
         * Process them accordingly if command is given.
         */
        $fi = new \FilesystemIterator('./data/queue/', \FilesystemIterator::SKIP_DOTS);
        $view->setVariable('num_files', iterator_count($fi));
        
        return $view;
    }
}