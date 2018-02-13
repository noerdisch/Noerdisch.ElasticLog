<?php

namespace Noerdisch\ElasticLog\Service;

use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Response as ElasticResponse;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Exception as FlowException;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Http\Response;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Context;
use Neos\Party\Domain\Model\Person;
use Neos\Party\Domain\Service\PartyService;

/**
 * Class ElasticSearchService
 * @package Noerdisch\ElasticLog\Service
 */
class ElasticSearchService
{
    const DEFAULT_TYPE = 'ElasticLog';

    const EXCEPTION_TYPE = 'ElasticException';

    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @Flow\InjectConfiguration(path="Connection")
     * @var array
     */
    protected $connectionSettings;

    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Converts exception data to an eleastic document and write the document to the elastic index.
     *
     * @param \Exception||\Throwable $exception
     * @param array $exceptionContext
     * @return void
     * @throws \InvalidArgumentException
     */
    public function logException($exception, array $exceptionContext)
    {
        $statusCode = null;
        if ($exception instanceof FlowException) {
            $statusCode = $exception->getStatusCode();
        }

        // skip exceptions with status codes matching "skipStatusCodes" setting
        if (isset($this->settings['skipStatusCodes']) &&
            \in_array($statusCode, $this->settings['skipStatusCodes'], true)
        ) {
            return;
        }

        // set logLevel depending on http status code
        $logLevel = 4; // warning
        if ($statusCode === 500) {
            $logLevel = 3; // error
        }

        $document = $this->getDocumentFromException($exception, $exceptionContext, $logLevel);
        if ($document) {
            $this->writeLogToElastic($document, self::EXCEPTION_TYPE);
        }
    }

    /**
     * Converts raw log message to an eleastic document and write the document to the elastic index.
     *
     * @param $rawMessage
     * @param array $messageContext
     * @param int $logLevel
     */
    public function logMessage($rawMessage, array $messageContext, $logLevel = LOG_INFO)
    {
        $document = $this->getDocumentFromLogMessage($rawMessage, $messageContext, $logLevel);
        if ($document) {
            $this->writeLogToElastic($document, self::DEFAULT_TYPE);
        }
    }

    /**
     * Writes the elastic search document to the elastic search server.
     *
     * @param Document $logDocument
     * @param string $type
     * @return bool
     */
    protected function writeLogToElastic(Document $logDocument, $type): bool
    {
        $index = $this->getElasticIndex();
        if (!($logDocument instanceof Document) || !($index instanceof Index)) {
            return false;
        }

        /** @var ElasticResponse $response */
        $response = $index->getType($type)->addDocument($logDocument);
        return $response->isOk() ? true : false;
    }

    /**
     * Creates a elastica document out of the log message and the additional data.
     *
     * @param string $rawMessage
     * @param array $messageContext
     * @param int $statusCode
     * @return Document|null
     */
    protected function getDocumentFromLogMessage($rawMessage, array $messageContext, int $statusCode)
    {
        $document = array(
            'message' => htmlspecialchars(trim($rawMessage)),
            'response_status' => $statusCode,
            'additionalInformation' => ''
        );

        if (\is_array($messageContext)) {
            $document['additionalInformation'] = json_encode($messageContext);
        }

        return new Document('', $document, self::DEFAULT_TYPE);
    }

    /**
     * Creates a elastica document out of the exception data and the request information.
     *
     * @param \Exception||\Throwable $exception
     * @param array $exceptionContext
     * @param int $statusCode
     * @return Document|null
     * @throws \InvalidArgumentException
     */
    protected function getDocumentFromException($exception, array $exceptionContext, int $statusCode)
    {
        $document = array(
            'exception' => $exception,
            'additionalInformation' => \is_array($exceptionContext) ? json_encode($exceptionContext) : '',
            'reference_code' => $exception instanceof FlowException ? $exception->getReferenceCode() : null,
            'response_status' => $statusCode,
            'short_message' => sprintf('%d %s', $statusCode, Response::getStatusMessageByCode($statusCode)),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        );

        if ($this->securityContext !== null && $this->securityContext->isInitialized()) {
            $account = $this->securityContext->getAccount();
            if ($account !== null) {
                $accountIdentifier = $this->persistenceManager->getIdentifierByObject($account);
                $document['authenticated_account'] = $account->getAccountIdentifier();
                $document['authenticated_account'] .= ' (' . $accountIdentifier . ')';
                $document['authenticated_roles'] = implode(', ', array_keys($this->securityContext->getRoles()));
                if ($this->objectManager->isRegistered(PartyService::class)) {
                    /** @var PartyService $partyService */
                    $partyService = $this->objectManager->get(PartyService::class);
                    $person = $partyService->getAssignedPartyOfAccount($account);
                    $personIdentifier = $this->persistenceManager->getIdentifierByObject($person);
                    if ($person instanceof Person) {
                        $document['authenticated_person'] = (string)$person->getName();
                        $document['authenticated_person'] .= ' (' . $personIdentifier . ')';
                    }
                }
            }
        }

        // prepare request details
        if (Bootstrap::$staticObjectManager instanceof ObjectManagerInterface) {
            $bootstrap = Bootstrap::$staticObjectManager->get(Bootstrap::class);
            /* @var Bootstrap $bootstrap */
            $requestHandler = $bootstrap->getActiveRequestHandler();
            if ($requestHandler instanceof HttpRequestHandlerInterface) {
                $request = $requestHandler->getHttpRequest();
                $requestData = array(
                    'request_domain' => $request->getHeader('Host'),
                    'request_remote_addr' => $request->getClientIpAddress(),
                    'request_path' => $request->getRelativePath(),
                    'request_uri' => $request->getUri()->getPath(),
                    'request_user_agent' => $request->getHeader('User-Agent'),
                    'request_method' => $request->getMethod(),
                    'request_port' => $request->getPort()
                );
                $document = array_merge($document, $requestData);
            }
        }

        return new Document('', $document, 'logMessage');
    }

    /**
     * Returns elastic search index via elastica library.
     *
     * @return Index|null
     */
    public function getElasticIndex()
    {
        $this->getClient();
        if ($this->client === null || !isset($this->connectionSettings['index'])) {
            return null;
        }

        return $this->client->getIndex($this->connectionSettings['index']);
    }

    /**
     * Returns elastic search client via elastica library.
     *
     * @return Client
     */
    protected function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client($this->connectionSettings);
        }

        return $this->client;
    }

}
