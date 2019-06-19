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
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\ImageTaggingBundle\Service;

use Pimcore\Model\Asset;
use Pimcore\Model\Element\Tag;

class ImageTaggingService
{
    private $tensorflowPath = null;
    private $modelPath = null;
    private $labelPath = null;
    private $cachePath = null;

    private $retrainingScriptName = 'retrain.py';
    private $classificationScriptName = 'label_image.py';
    private $standardTrainFlags = null;
    private $standardRetrainFlags = '--testing_percentage 20 --validation_percentage 20 ' .
    '--how_many_training_steps 200';
    private $standardPredictFlags = '--input_layer Placeholder --output_layer final_result';

    private static $instance = null;

    public function __construct()
    {
        $this->tensorflowPath = PIMCORE_PROJECT_ROOT . '/src/tensorflow';

        $tfDataPath = PIMCORE_PRIVATE_VAR . '/tensorflow-data';
        $this->modelPath = $tfDataPath . '/model/';
        $this->labelPath = $tfDataPath . '/labels/';
        $this->cachePath = $tfDataPath . '/cache';
        $this->standardTrainFlags = '--testing_percentage 20 --validation_percentage 20 ' .
            '--how_many_training_steps 500 --bottleneck_dir ' . $tfDataPath . '/bottleneck';
    }

    // helper functions

    private function getGraphFullName(string $modelName, string $modelVersion)
    {
        return $this->modelPath . $modelName . '.v' . $modelVersion;
    }

    private function getLabelFullName(string $modelName, string $modelVersion)
    {
        return $this->labelPath . $modelName . '.v' . $modelVersion;
    }

    private function linkAssetToTrainingLocation(Asset $asset, string $tagDir = null)
    {
        if ($tagDir == null) {
            $tagDir = '"' . $this->cachePath . '/' . $asset->getFilename() . '"';
        }
        exec('ln -s "' . $asset->getFileSystemPath() . '" ' . $tagDir);

        return $tagDir;
    }

    private function createLinksForTagChilds(Tag $child)
    {
        $assets = Tag::getElementsForTag($child, 'asset');
        unset($assets[0]);
        $tagDir = $this->cachePath . '/' . $child->getName();
        exec('mkdir ' . $tagDir);
        foreach ($assets as $asset) {
            $this->linkAssetToTrainingLocation($asset, $tagDir);
        }
    }

    private function linkTrainingDataByTag(int $tagId)
    {
        $parent = new Tag();
        $parent->setId($tagId);
        $children = $parent->getChildren();
        foreach ($children as $child) {
            $this->createLinksForTagChilds($child);
        }
    }

    private function cleanCacheDir()
    {
        exec('rm -rf ' . $this->cachePath . '/*');
    }

    private function getTag(array $tags)
    {
        $result = [[], []];
        $i = 0;
        foreach ($tags as $tag) {
            $pos = strripos($tag, ' ');
            $result[$i]['tag'] = substr($tag, 0, $pos);
            $result[$i]['probability'] = substr($tag, $pos + 1);
            $i++;
        }

        return $result;
    }

    private function getTagWithHighestProbability($tags)
    {
        $max = 0.0;
        $maxTag = null;
        foreach ($this->getTag($tags) as $tag) {
            $probability = floatval($tag['probability']);
            if ($probability > $max) {
                $maxTag = $tag['tag'];
                $max = $probability;
            }
        }

        return $maxTag;
    }

    private function executeClassifierForSingleElement(
        string $modelName,
        string $modelVersion,
        string $assetLinkPath
    )
    {
        $execString =
            'python3 ' . $this->tensorflowPath . '/' . $this->classificationScriptName .
            ' --image ' . $assetLinkPath .
            ' --graph ' . $this->getGraphFullName($modelName, $modelVersion) .
            ' --labels ' . $this->getLabelFullName($modelName, $modelVersion) . ' ' .
            $this->standardPredictFlags;
        exec($execString, $results);
//        foreach ($results as $result) {
//            echo $result . "\n";
//        }

        return $results;
    }

    private function assignTagToAsset($asset, $tagName)
    {
        $tag = new Tag();
        $tag->setId($this->getTagIdByName($tagName));
        Tag::addTagToElement('asset', $asset->getId(), $tag);
    }

    private function predictItem(string $modelName, string $modelVersion, string $assetId)
    {
        $asset = Asset::getById($assetId);
        $this->cleanCacheDir();
        $this->assignTagToAsset(
            $asset,
            $this->getTagWithHighestProbability(
                $this->executeClassifierForSingleElement(
                    $modelName,
                    $modelVersion,
                    $this->linkAssetToTrainingLocation($asset)
                )
            )
        );
        $this->cleanCacheDir();
    }

    private function predictBatch(string $modelName, string $modelVersion, array $assetIds)
    {
        $flatAssetIdList = [];
        foreach ($assetIds as $assetId) {
            $asset = Asset::getById($assetId);
            if ($asset instanceof Asset\Folder) {
                foreach ($asset->getChildren() as $child) {
                    if ($child instanceof Asset\Image) {
                        $flatAssetIdList[] = $child->getId();
                    }
                }
            } elseif ($asset instanceof Asset\Image) {
                $flatAssetIdList[] = $asset->getId();
            }
        }
        foreach ($flatAssetIdList as $asset) {
            $this->predictItem($modelName, $modelVersion, $asset);
        }
    }

    private function getTagIdByName(string $name)
    {
        $name = str_replace(' ', '-', $name);
        $db = \Pimcore\Db::get();
        $statement = 'select id from tags where name = ?';
        $params = [0 => $name];
        $types = [0 => 'string'];
        $result = $db->fetchAll($statement, $params, $types);
        if (sizeof($result) != 1) {
            throw new \Exception('tagname not unique or not existing');
        }

        return $result[0]['id'];
    }

    private function createLinks(Tag $child)
    {
        $pictures = Tag::getElementsForTag($child, 'asset');
        unset($pictures[0]);
        $tagDir = $this->cachePath . '/' . $child->getName();
        exec('mkdir ' . $tagDir);
        foreach ($pictures as $picture) {
            $assetPath = $picture->getFileSystemPath();
            if ($assetPath != '') {
                exec('ln ' . $assetPath . ' ' . $tagDir);
            }
        }
    }

    private function linkTrainingData(int $tagId)
    {
        $parent = new Tag();
        $parent->setId($tagId);
        $children = $parent->getChildren();
        foreach ($children as $child) {
            $this->createLinks($child);
        }
    }

    private function cleanup()
    {
        exec('rm -rf ' . $this->cachePath . '/*');
    }

    // commands

    public function listModels($extended = false)
    {
        exec('ls ' . $this->modelPath, $result);

        if($extended) {

            $extendedResult = [];
            foreach($result as $item) {

                $parts = explode('.v', $item);
                $extendedResult[] = [
                    'name' => $parts[0],
                    'version' => $parts[1]
                ];
            }

            return $extendedResult;

        } else {
            return $result;
        }


    }

    /**
     * @param string $modelName
     * @param string $modelVersion
     * @param string $path
     * @param string $parentTag
     * @throws \Exception
     */
    public function train(
        string $modelName,
        string $modelVersion,
        string $path,
        string $parentTag
    )
    {
        if ($path == null) {
            $path = $this->cachePath;
        }
        $graphName = $this->getGraphFullName($modelName, $modelVersion);
        $this->cleanCacheDir();
        $this->linkTrainingDataByTag($this->getTagIdByName($parentTag));
        $execString =
            'python3 ' . $this->tensorflowPath . '/' . $this->retrainingScriptName .
            ' --image_dir ' . $path .
            ' --output_graph ' . $graphName .
            ' --output_labels ' . $this->getLabelFullName($modelName, $modelVersion) . ' ' .
            $this->standardTrainFlags;
        exec($execString);
        $this->cleanCacheDir();
    }

    public function retrain(
        string $modelName,
        string $modelVersion,
        string $path,
        int $instance
    )
    {
        if ($path == null) {
            $path = $this->cachePath;
        }
        $graphName = $this->getGraphFullName($modelName, $modelVersion);
        $this->linkTrainingDataByTag($this->getTagIdByName($instance));
        $execString =
            'python3 ' . $this->tensorflowPath . '/' . $this->retrainingScriptName .
            ' --image_dir ' . $path .
            ' --output_graph ' . $graphName .
            ' --output_labels ' . $this->getLabelFullName($modelName, $modelVersion) . ' ' .
            $this->standardTrainFlags;
        exec($execString);
        $this->cleanCacheDir();
    }

    public function predict(string $modelName, string $modelVersion, array $assetId)
    {
        $this->predictBatch($modelName, $modelVersion, $assetId);
    }

}
