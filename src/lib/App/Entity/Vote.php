<?php

namespace App\Entity;

use App\Entity\Base;

class Vote extends Base
{
    const OPTION_LIKE    = 1;
    const OPTION_DISLIKE = 2;
    const OPTION_DOUBT   = 3;
    
    /** @var string **/
    public $ip;
    /** @var int **/
    public $option;
    
    /**
     * Get allowed node options
     * @return boolean
     */
    static public function getAllowedOptions()
    {
        return array(
            self::OPTION_LIKE,
            self::OPTION_DISLIKE,
            self::OPTION_DOUBT
        );
    }
}