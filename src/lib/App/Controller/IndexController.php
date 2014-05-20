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
    
    public function faqAction()
    {
        return $this->render('faq.twig', array(
            'questions'  => range(1, 8)
        ));
    }
    
    public function cookiesAction()
    {
        return $this->render('cookies.twig');
    }
}