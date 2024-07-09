<?php
declare(strict_types=1);

namespace Application\Model;

use Components\Model\AbstractBaseModel;
use Laminas\Db\Adapter\Adapter;

class TelestaffModel extends AbstractBaseModel
{
    const VALID_STATUS = 3;
    const INVALID_STATUS = 4;
    
    public $NAME;
    public $PYID;
    public $EMID;
    public $CODE;
    public $HOUR;
    public $DATE;
    public $ACCT;
    public $DETC;
    public $NOTE;
    
    public function __construct(Adapter $adapter = NULL)
    {
        parent::__construct($adapter);
        $this->setTableName('update_telestaff');
    }
}