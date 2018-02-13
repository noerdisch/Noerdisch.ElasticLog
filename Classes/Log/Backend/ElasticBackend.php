<?php

namespace Noerdisch\ElasticLog\Log\Backend;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Backend\AbstractBackend;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\Flow\ObjectManagement\DependencyInjection\DependencyProxy;
use Noerdisch\ElasticLog\Service\ElasticSearchService;

/**
 * Class ElasticBackend
 * Backend that can be used for Logger that implement Neos\Flow\Log\LoggerInterface
 *
 * @package Noerdisch\ElasticLog\Log\Backend
 */
class ElasticBackend extends AbstractBackend
{

    /**
     * @Flow\Inject
     * @var ElasticSearchService
     */
    protected $elasticSearchService;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $systemLogger;

    /**
     * An array of severity labels, indexed by their integer constant
     * @var array
     */
    protected $severityLabels;

    /**
     * @var bool
     */
    protected $alsoLogWithSystemLogger;

    /**
     * This method will send a message to our elastic search service
     *
     * @param string $message The message to log
     * @param integer $severity One of the LOG_* constants
     * @param mixed $additionalData A variable containing more information about the event to be logged
     * @param string $packageKey Key of the package triggering the log (determined automatically if not specified)
     * @param string $className Name of the class triggering the log (determined automatically if not specified)
     * @param string $methodName Name of the method triggering the log (determined automatically if not specified)
     * @return void
     */
    public function append(
        $message,
        $severity = LOG_INFO,
        $additionalData = null,
        $packageKey = null,
        $className = null,
        $methodName = null
    )
    {
        if ($severity > $this->severityThreshold) {
            return;
        }

        $ipAddress = ($this->logIpAddress === true) ? str_pad($_SERVER['REMOTE_ADDR'] ?? '', 15) : '';
        $severityLabel = $this->severityLabels[$severity] ?? 'UNKNOWN  ';

        $output = $severityLabel . ': ' . $message;

        $messageContext = [
            'packageKey' => $packageKey ?? '',
            'className' => $className ?? '',
            'methodName' => $methodName ?? '',
            'additionalData' => $additionalData ?? '',
            'ipAddress' => $ipAddress ?? '',
            'severityLabel' => $severityLabel ?? '',
        ];
        $this->elasticSearchService->logMessage($output, $messageContext, $severity);

        if ($this->alsoLogWithSystemLogger && $this->systemLogger instanceof SystemLoggerInterface) {
            $this->systemLogger->log($output, $severity, $additionalData, $packageKey, $className, $methodName);
        }
    }

    /**
     * Called when this backend is added to a logger
     *
     * @return void
     */
    public function open()
    {
        $this->severityLabels = [
            LOG_EMERG => 'EMERGENCY',
            LOG_ALERT => 'ALERT    ',
            LOG_CRIT => 'CRITICAL ',
            LOG_ERR => 'ERROR    ',
            LOG_WARNING => 'WARNING  ',
            LOG_NOTICE => 'NOTICE   ',
            LOG_INFO => 'INFO     ',
            LOG_DEBUG => 'DEBUG    ',
        ];
    }

    /**
     * Called when this backend is removed from a logger
     *
     * @return void
     */
    public function close()
    {
        // nothing to do here
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

    /**
     * @param bool $alsoLogWithSystemLogger
     * @return void
     */
    public function setAlsoLogWithSystemLogger(bool $alsoLogWithSystemLogger)
    {
        $this->alsoLogWithSystemLogger = $alsoLogWithSystemLogger;
    }
}
