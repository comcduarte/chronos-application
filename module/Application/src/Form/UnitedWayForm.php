<?php
namespace Application\Form;

use Components\Form\AbstractBaseForm;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Text;

class UnitedWayForm extends AbstractBaseForm
{
    public function init()
    {
        parent::init();
        
        $this->add([
            'name' => 'EMP_UUID',
            'type' => Hidden::class,
            'attributes' => [
                'id' => 'EMP_UUID',
                'class' => 'form-control',
            ],
            'options' => [
                'label' => 'Employee UUID',
            ],
        ],['priority' => 100]);
        
        $this->add([
            'name' => 'USER_UUID',
            'type' => Hidden::class,
            'attributes' => [
                'id' => 'USER_UUID',
                'class' => 'form-control',
            ],
            'options' => [
                'label' => 'Current User UUID',
            ],
        ],['priority' => 100]);
        
        $this->add([
            'name' => 'METHOD',
            'type' => Hidden::class,
            'attributes' => [
                'id' => 'METHOD',
                'class' => 'form-control',
            ],
            'options' => [
                'label' => 'Method',
            ],
        ],['priority' => 100]);
        
        $this->add([
            'name' => 'DEDUCTION',
            'type' => Select::class,
            'attributes' => [
                'id' => 'DEDUCTION',
                'class' => 'form-control',
            ],
            'options' => [
                'label' => 'Per Pay Period Amount',
                'value_options' => [
                    '100' => '100',
                    '50' => '50',
                    '25' => '25',
                    '10' => '10',
                    '5' => '5',
                    '1' => '1',
                ],
            ],
        ],['priority' => 100]);
        
        $this->add([
            'name' => 'OTHER',
            'type' => Text::class,
            'attributes' => [
                'id' => 'OTHER',
                'class' => 'form-control',
            ],
            'options' => [
                'label' => 'Other Amount',
            ],
        ],['priority' => 100]);
        
        $this->add([
            'name' => 'DESIGNATION',
            'type' => Select::class,
            'attributes' => [
                'id' => 'DESIGNATION',
                'class' => 'form-control',
            ],
            'options' => [
                'label' => 'Designation Options',
            ],
        ],['priority' => 100]);
        
    }
}