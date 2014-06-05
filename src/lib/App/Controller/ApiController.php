<?php

namespace App\Controller;

use App\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Amendment;
use App\Entity\Vote;

class ApiController extends AbstractController 
{
    public function indexAction()
    {
        $uuid = $this->_getParam('uuid');
        
        return $this->json(array(
            'success' => true,
            'data' => $this->_db->find('draft_' . $uuid, array(), array(
                'votes' => 0,
            ))
        ));
    }
    
    protected function _validateForm($form)
    {
        // Simulate form request structure (adapter pattern)
        $this->request->request->add(array(
            'form' => $this->request->request->all()
        ));
        $form->handleRequest($this->request);
        
        // Validate fields
        return $form->isValid();
    }
    
    protected function _getFormErrors($form)
    {
        $errors = array();
        foreach ($form->all() as $name => $child) {
            if (count($child->getErrors())) {
                $errors[$name] = $child->getErrorsAsString();
            }
        }
        return $errors;
    }
    
    public function createAction()
    {
        $uuid = $this->_getParam('uuid');
        
        // Use form to validate data
        $form = $this->form()
            ->add('tid', 'text', array(
                'constraints' => array(new Assert\NotBlank())
            ))
            ->add('body', 'textarea', array(
                // 'constraints' => array(new Assert\NotNull())
            ))
            ->add('reason', 'textarea', array(
                'constraints' => array(new Assert\NotBlank())
            ))
            ->add('uid', 'text', array(
                'constraints' => array(new Assert\NotBlank())
            ))
            ->add('addition', 'text', array(
                // 'constraints' => array(new Assert\NotNull())
            ))
            ->getForm();
        
        if ($this->_validateForm($form)) {
            // Map data
            $data = $form->getData();
            $data['addition'] = (boolean)$data['addition'];
            // Build amendment
            $amendment = new Amendment($data);
            $amendment->status = Amendment::STATUS_PENDING;
            // Save amendment
            $_id = $this->_db->create('draft_' . $uuid, $amendment->exportData());
            if ($_id) {
                return $this->json(array(
                    'success' => true,
                    '_id' => $_id
                ));
            }
        } else {
            $errors = $this->_getFormErrors($form);
            $this->abort(400, json_encode($errors));
        }
        
        $this->abort(500, $this->trans('messages.node.notcreated'));
    }    
    
    protected function _getClientIp()
    {
        return filter_var(
            !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] :
            !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] :
            !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', 
            FILTER_SANITIZE_STRING, FILTER_VALIDATE_IP/*, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE*/
        );
    }
    
    public function voteAction()
    {
        $uuid = $this->_getParam('uuid');
        $id = $this->_getParam('id');
        $collection = 'draft_' . $uuid;
        $doc = $this->_db->read($collection, $id);
        
        if (empty($doc)) {
            $this->abort(404, $this->trans('messages.node.notfound'));
        } else {
            $amendment = new Amendment($doc);
        }
        
        // Validate client IP too (adapter pattern)
        $ip = $this->_getClientIp();
        $this->request->request->add(array('ip' => $ip));
        
        // Use form to validate data
        $form = $this->form()
            ->add('ip', 'text', array(
                'constraints' => array(
                    new Assert\NotBlank(), 
                    new Assert\Ip()
                )
            ))
            ->add('option', 'number', array(
                'constraints' => array(
                    new Assert\NotBlank(), 
                    new Assert\Choice(Vote::getAllowedOptions())
                )
            ))
            ->getForm();
        
        if ($this->_validateForm($form)) {
            // Map data
            $data = $form->getData();
            // Remember last vote (simplifies UX issues)
            $data['ip'] = str_replace('.', '-', $data['ip']);
            $lastVote = $amendment->getVote($data['ip']);
            // Update amendment
            $amendment->addVote(new Vote($data));
            if ($this->_db->update($collection, $amendment->exportData())) {
                return $this->json(array(
                    'success'  => true,
                    'lastVote' => $lastVote
                ));
            }
        } else {
            $errors = $this->_getFormErrors($form);
            $this->abort(400, json_encode($errors));
        }
        
        $this->abort(500, $this->trans('messages.node.notcreated'));
    }
}