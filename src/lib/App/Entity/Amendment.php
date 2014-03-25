<?php

namespace App\Entity;

use App\Entity\Base;

class Amendment extends Base 
{
    const STATUS_PENDING  = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    
    /** @var string **/
    public $tid;
    /** @var string **/
    public $body;
    /** @var string **/
    public $reason;
    /** @var string **/
    public $uid;
    /** @var int **/
    public $status;
    /** @var boolean **/
    public $addition;
    
    /**
     * Node has a valid status type
     * @return boolean
     */
    public function hasValidStatus()
    {
        return in_array($this->status, array(
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED
        ));
    }
}