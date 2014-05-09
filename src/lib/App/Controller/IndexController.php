<?php

namespace App\Controller;

use App\Controller\AbstractController;

class IndexController extends AbstractController 
{
    public function indexAction()
    {
        return $this->render('home.twig', array(
            'form'  => $this->form()->getForm()->createView()
        ));
    }
}