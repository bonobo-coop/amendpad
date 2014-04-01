<?php

namespace App\Controller;

use App\Controller\AbstractController;
use App\Entity\Draft;
use App\Db\UUID;

class DraftController extends AbstractController 
{
    public function createAction()
    {
        // Create case
        $draft = new Draft();
        $draft->privateKey = UUID::v4();
        $draft->publicKey  = UUID::v4();
        
        $success = $this->_db->create('drafts', $draft->exportData(), array(
            'privateKey', 
            'publicKey'
        ));

        if ($success) {
            return $this->redirect('/draft/' . $draft->privateKey);        
        } else {
            $this->abort(500, $this->trans('messages.node.notcreated'));
        }
    }
    
    protected function _getDraft($uuid, $keyType) 
    {
        // Load document
        $draft = new Draft();
        
        if (UUID::isValid($uuid)) {
            $doc = $this->_db->findOne('drafts', array(
                $keyType => $uuid
            ));
            if ($doc) {
                $draft->importData($doc);
                if ($draft->status !== 1) {
                    // Document not published
                    $this->abort(403, $this->trans('messages.node.notpublished'));
                }
                return $draft;
            }
        }
        // Wrong UUID or document not found
        $this->abort(404, $this->trans('messages.node.notfound'));
    }
    
    public function privateAction()
    {
        $uuid = $this->_getParam('uuid');
        
        // Load document
        $draft = $this->_getDraft($uuid, 'privateKey');
        
        // Build form
        $form = $this->form($draft->exportData())
            ->add('title')
            ->add('body', 'textarea')
            ->getForm();
        
        $form->handleRequest($this->request);
        
        if ($form->isValid()) {
            // Update draft
            $draft->importData($form->getData());
            $this->_db->update('drafts', $draft->exportData());
        }

        return $this->render('draft.twig', array(
            'draft' => $draft,
            'form'  => $form->createView()
        ));
    }
    
    public function publicAction()
    {
        $uuid = $this->_getParam('uuid');
        
        // Load document
        $draft = $this->_getDraft($uuid, 'publicKey');
        
        return $this->render('doc.twig', array(
            'draft' => $draft
        ));        
    }
}