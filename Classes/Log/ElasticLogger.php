<?php

namespace Noerdisch\ElasticLog\Log;

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
use Neos\Flow\Log\Logger;
use Neos\Flow\ObjectManagement\DependencyInjection\DependencyProxy;
use Noerdisch\ElasticLog\Service\ElasticSearchService;

/**
 * Class ElasticLogger
 * @package Noerdisch\ElasticLog\Log
 */
class ElasticLogger extends Logger implements ElasticLoggerInterface
{
    /**
     * @Flow\Inject
     * @var ElasticSearchService
     */
    protected $elasticSearchService;

    /**
     * Writes information about the given exception to elastic search including the stacktrace.
     *
     * @param object $error \Exception or \Throwable
     * @param array $additionalData Additional data to log
     * @return void
     * @throws \InvalidArgumentException
     */
    public function logError($error, array $additionalData = [])
    {
        $this->getElasticSearchService()->logException($error, $additionalData);
        parent::logError($error, $additionalData);
    }

    /**
     * Returns an instance of the injected ElasticSearchService (including a fallback to a manually instantiated
     * instance if Dependency Injection is not (yet) available)
     *
     * @return ElasticSearchService
     */
    protected function getElasticSearchService(): ElasticSearchService
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
