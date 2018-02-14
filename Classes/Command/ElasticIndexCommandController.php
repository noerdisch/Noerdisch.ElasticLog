<?php

namespace Noerdisch\ElasticLog\Command;

/*
 * This file is part of the Noerdisch.ElasticLog package.
 *
 * (c) Noerdisch - Digital Solutions www.noerdisch.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Elastica\Index;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Noerdisch\ElasticLog\Service\ElasticSearchService;

/**
 * Command controller for setup the logger index in the elastic search.
 *
 * @package Noerdisch\ElasticLog\Command
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
     * Sets up the index
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
