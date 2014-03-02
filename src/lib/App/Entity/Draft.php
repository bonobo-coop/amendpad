<?php

namespace App\Entity;

use App\Entity\Base;

class Draft extends Base 
{
    /** @var string **/
    public $publicKey;
    /** @var string **/
    public $privateKey;
    /** @var string **/
    public $title;
    /** @var string **/
    public $body;
}