<?php

namespace App\Entity;

abstract class Base 
{    
    /** @var string **/
    public $_id;
    /** @var int **/
    public $created;
    /** @var int **/
    public $updated;
    /** @var int **/
    public $status;
    
    public function __construct($data = array())
    {
        $this->created = $this->updated = time();
        $this->importData($data, FALSE);
    }
    
    public function importData($data, $update = TRUE)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;                
            }
        }
        
        if ($update) {
            $this->updated = time();            
        }
    }
    
    public function exportData()
    {
        return array_filter((array) $this, 'strlen');
    }
}