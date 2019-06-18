<?php

namespace Pimcore\ImageTaggingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('PimcoreImageTaggingBundle:Default:index.html.twig');
    }
}
