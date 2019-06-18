<?php

namespace Pimcore\ImageTaggingBundle\Command;

use Pimcore\ImageTaggingBundle\Service;
use Pimcore\Console\AbstractCommand;
use Pimcore\Console\Dumper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PimcoreTensorflowCommand extends AbstractCommand
{
    private $params;
	 private $tensorflowService;

    protected function configure()
    {
        $this->tensorflowService = Service\ImageTaggingService::getInstance();
        $this
            ->setName('pimcore:tensorflow')
            ->setDescription('Makes use of tensorflow retrain.py')
            ->addArgument(
                'option',
                InputOption::VALUE_REQUIRED,
                'what to do (train / predict / retrain / listModels)'
            )
            ->addOption(
                'modelName',
                'N',
                InputOption::VALUE_OPTIONAL,
                'model name'
            )
            ->addOption(
                'modelVersion',
                'm',
                InputOption::VALUE_OPTIONAL,
                'model version'
            )
            ->addoption(
                'assetId',
                'i',
                inputoption:: VALUE_OPTIONAL | inputoption::VALUE_IS_ARRAY,
                'asset id to be classified'
            )
            ->addOption(
                'parentTag',
                't',
                InputOption::VALUE_OPTIONAL,
                'parent tag (child tags are used as classes)'
            )
            ->addoption(
                'path',
                'p',
                inputoption::VALUE_OPTIONAL,
                'path (to parent folder) of the data to be classified'
            );
    }

    // commands

    private function listModels(OutputInterface $output) {
        $this->dump('option listModels');
		  foreach ($this->tensorflowService->listModels() as $model)
			  $output->writeln($model);
    }

    private function train() {
        $this->dump("training network");
		  $this->tensorflowService->train(
			  $this->params["modelName"],
			  $this->params["modelVersion"],
			  $this->params["path"],
			  $this->params["parentTag"]
		  );
    }

    private function retrain() {
        $this->dump("retraining network");
		  $this->tensorflowService->retrain(
			  $this->params["modelName"],
			  $this->params["modelVersion"],
			  $this->params["path"],
			  $this->params["instance"]
		  );
    }

    private function predict() {
        $this->dump("option predict");
		  $this->tensorflowService->predict(
			  $this->params["modelName"],
			  $this->params["modelVersion"],
			  $this->params["assetId"]
		  );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $option = $input->getArgument('option');
        $this->params = $input->getOptions();
        if ($option === 'listModels') $this->listModels($output);
        else if ($option === 'train') $this->train();
        else if ($option === 'retrain') $this->retrain();
        else if ($option === 'predict') $this->predict();
        else $output->writeln('option ' . $option . ' unsupported');
    }

}
