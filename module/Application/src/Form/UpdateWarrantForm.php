<?php
namespace Application\Form;

use Laminas\Form\Form;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;

class UpdateWarrantForm extends Form
{
    public function init()
    {
        $class = 'btn btn-primary mt-2 me-2 btn-sm';
        
        $this->add([
            'name' => 'WARRANT_NUM',
            'type' => Text::class,
            'attributes' => [
                'id' => 'WARRANT_NUM',
                'class' => 'form-control',
                'required' => 'true',
            ],
            'options' => [
                'label' => 'Warrant Number',
            ],
        ]);
        $this->add(new Csrf('SECURITY'));
        $this->add([
            'name' => 'SUBMIT',
            'type' => Submit::class,
            'attributes' => [
                'value' => 'Search',
                'class' => $class,
                'id' => 'SUBMIT',
            ],
        ],['priority' => 0]);
        
        $this->add([
            'name' => 'ASSOCIATE',
            'type' => Submit::class,
            'attributes' => [
                'value' => 'Associate',
                'class' => $class,
                'id' => 'ASSOCIATE',
            ],
        ],['priority' => 0]);
    }
}