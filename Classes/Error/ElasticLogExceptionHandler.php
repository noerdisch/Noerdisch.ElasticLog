<?php

namespace Noerdisch\ElasticLog\Error;

/*
 * This file is part of the Noerdisch.ElasticLog package.
 *
 * (c) Noerdisch - Digital Solutions www.noerdisch.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Error\ProductionExceptionHandler;
use Neos\Flow\ObjectManagement\DependencyInjection\DependencyProxy;
use Noerdisch\ElasticLog\Service\ElasticSearchService;

/**
 * Production Exception handler that reports exceptions to a elastic search server.
 *
 * @package Noerdisch\ElasticLog\Error
 */
class ElasticLogExceptionHandler extends ProductionExceptionHandler
{

    /**
     * @Flow\Inject
     * @var ElasticSearchService
     */
    protected $elasticSearchService;

    /**
     * @param \Throwable $exception
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function echoExceptionWeb($exception)
    {
        if (!empty($this->renderingOptions['logException'])) {
            $this->getElasticSearchService()->logException($exception, []);
        }

        parent::echoExceptionWeb($exception);
    }

    /**
     * @param \Throwable $exception
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function echoExceptionCli(\Throwable $exception)
    {
        if (!empty($this->renderingOptions['logException'])) {
            $this->getElasticSearchService()->logException($exception, []);
        }

        parent::echoExceptionCli($exception);
    }

    /**
     * Returns an instance of the injected ElasticSearchService (including a fallback to a manually instantiated
     * instance if Dependency Injection is not (yet) available)
     *
     * @return ElasticSearchService
     */
    private function getElasticSearchService(): ElasticSearchService
    {
        if ($this->elasticSearchService instanceof ElasticSearchService) {
            return $this->elasticSearchService;
        }

        if ($this->elasticSearchService instanceof DependencyProxy) {
            return $this->elasticSearchService->_activateDependency();
        }

        return new ElasticSearchService();
    }
}
