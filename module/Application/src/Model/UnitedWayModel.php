<?php
namespace Application\Model;

use Components\Model\AbstractBaseModel;

class UnitedWayModel extends AbstractBaseModel
{
    public $EMP_UUID;
    public $USER_UUID;
    public $DEDUCTION;
    public $DESIGNATION;
    public $METHOD;
    public $OTHER;
    
    public function __construct($adapter = NULL)
    {
        parent::__construct($adapter);
        $this->setTableName('unitedway');
    }
}