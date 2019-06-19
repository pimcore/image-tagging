<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\ImageTaggingBundle\Controller;

use Pimcore\Bundle\ImageTaggingBundle\Service\ImageTaggingService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('PimcoreImageTaggingBundle:Default:index.html.twig');
    }

    /**
     * @Route("/list-models")
     * @param ImageTaggingService $service
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function listModelsAction(ImageTaggingService $service) {

        $models = $service->listModels(true);

        $result = [];

        foreach($models as $model) {
            $result[] = [
                'nicename' => $model['name'] . ' v' . $model['version'],
                'name' => $model['name'],
                'version' => $model['version']
            ];
        }

        return $this->json(['success' => true, 'models' => $result]);
    }

    /**
     * @Route("/classify")
     * @param ImageTaggingService $service
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function classifyAction(ImageTaggingService $service, Request $request) {
        $assetId = $request->get('id');
        $model = $request->get('model');
        $version = $request->get('version');

        $service->predict($model, $version, [$assetId]);

        return $this->json(['success' => true]);
    }
}
