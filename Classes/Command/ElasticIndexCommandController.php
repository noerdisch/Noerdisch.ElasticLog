<?php

namespace Noerdisch\ElasticLog\Command;

use Elastica\Index;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Noerdisch\ElasticLog\Service\ElasticSearchService;


/**
 * Command controller for indexing information for the search.
 *
 * @package Noerdisch\ErsteNachhilfe\Platform\Command
 */
class ElasticIndexCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var ElasticSearchService
     */
    protected $elasticSearchService;

    /**
     * @Flow\InjectConfiguration(path="Connection.index")
     * @var string
     */
    protected $indexName;

    /**
     * Sets up the index and correctly configures all the types mappings
     *
     * @return void
     */
    public function setupCommand()
    {
        $createdIndex = $this->emptyElasticIndex();
        if ($createdIndex) {
            $this->outputLine('Create empty elastic index "%s".', [$this->indexName]);
        }
    }

    /**
     * Create empty elastic search index. the index is configured in the Settings.yaml
     *
     * @return bool
     */
    protected function emptyElasticIndex(): bool
    {
        $elasticIndex = $this->elasticSearchService->getElasticIndex();
        if (!($elasticIndex instanceof Index) || trim($this->indexName) === '') {
            return false;
        }

        $success = true;
        try {
            $elasticIndex->create([], true);
        } catch (\Exception $exception) {
            $this->outputLine(
                'Could not create empty elastic index %s, caused by "%s".',
                [$this->indexName, $exception->getMessage()]
            );
            $success = false;
        }

        return $success;
    }

}
