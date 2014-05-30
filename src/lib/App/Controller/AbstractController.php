<?php

namespace App\Controller;

abstract class AbstractController 
{
    /** @var Silex\Application **/
    protected $_app;
    
    /** @var App\Db\MongoWrapper **/
    protected $_db;
    
    public function __construct($app, $db)
    {
        $this->_app = $app;
        $this->_db = $db;
    }
    
    /**
     * Proxy pattern
     * 
     * @param string $name
     * @return mixed
     */
    public function __get($name) 
    {
        return $this->_app[$name];
    }
    
    /**
     * Proxy pattern
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments) 
    {
        return call_user_method_array($name, $this->_app, $arguments);
    }
    
    /**
     * Proxy pattern (shortcut)
     * 
     * @param type $name
     * @return type
     */
    protected function _getParam($name)
    {
        return $this->_app->escape(
            $this->_app['request']->attributes->get($name)
        );
    }
    
    /**
     * Proxy pattern (shortcut)
     * 
     * @param array $data
     * @return Symfony\Component\Form\Form
     */
    public function form(array $data = array())
    {
        return $this->_app['form.factory']->createBuilder('form', $data);
    }
    
    /**
     * Proxy pattern (shortcut)
     * 
     * @param string $tpl
     * @param array $params
     * @return string
     */
    public function render($tpl, array $params = array())
    {
        return $this->_app['twig']->render($tpl, $params);
    }
    
    /**
     * Proxy pattern (shortcut)
     * 
     * @param string $msg
     * @return string
     */
    public function trans($msg)
    {
        return $this->_app['translator']->trans($msg);
    }
}