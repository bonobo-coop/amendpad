<?php

namespace App\Controller;

use App\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Amendment;

class ApiController extends AbstractController 
{
    public function createAction()
    {
        $uuid = $this->_getParam('uuid');
        
        // Use form to validate data
        $form = $this->form()
            ->add('tid', 'text', array(
                'constraints' => array(new Assert\NotBlank())
            ))
            ->add('body', 'textarea', array(
                'constraints' => array(new Assert\NotBlank())
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

        // Simulate form request structure (adapter pattern)
        $this->request->request->add(array(
            'form' => $this->request->request->all()
        ));
        $form->handleRequest($this->request);
        
        if ($form->isValid()) {
            // Map data
            $data = $form->getData();
            $data['addition'] = (boolean)$data['addition'];        
            // Build amendment
            $amendment = new Amendment($data);
            $amendment->status = Amendment::STATUS_PENDING;
            // Save amendment
            if ($this->_db->create('draft_' . $uuid, $amendment->exportData())) {
                return $this->json(array(
                    'success' => true
                ));
            }
        } else {
            $errors = array();
            foreach ($form->all() as $name => $child) {
                if (count($child->getErrors())) {
                    $errors[$name] = $child->getErrorsAsString();
                }
            }
            $this->abort(400, json_encode($errors));
        }
        
        $this->abort(500, $this->trans('messages.node.notcreated'));
    }    
}