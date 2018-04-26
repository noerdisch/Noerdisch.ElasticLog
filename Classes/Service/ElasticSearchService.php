<?php

namespace Noerdisch\ElasticLog\Service;

/*
 * This file is part of the Noerdisch.ElasticLog package.
 *
 * (c) Noerdisch - Digital Solutions www.noerdisch.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

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
    public const DEFAULT_TYPE = 'ElasticLog';

    public const EXCEPTION_TYPE = 'ElasticException';

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
     * @param \Throwable $throwable
     * @param array $context
     * @return void
     * @throws \InvalidArgumentException
     */
    public function logException(\Throwable $throwable, array $context): void
    {
        // set logLevel depending on http status code
        $logLevel = LOG_WARNING;
        if ($throwable instanceof FlowException && $this->getStatusCode($throwable) === 500) {
            $logLevel = LOG_ERR;
        }

        $document = $this->getDocumentFromException($throwable, $context, $logLevel);
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
    public function logMessage($rawMessage, array $messageContext, $logLevel = LOG_INFO): void
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
     * @param int $logLevel
     * @return Document|null
     */
    protected function getDocumentFromLogMessage($rawMessage, array $messageContext, int $logLevel): ?Document
    {
        $document = array(
            'message' => htmlspecialchars(trim($rawMessage)),
            'log_level' => $logLevel,
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
     * @param int $logLevel
     * @return Document|null
     * @throws \InvalidArgumentException
     */
    protected function getDocumentFromException($exception, array $exceptionContext, $logLevel): ?Document
    {
        $statusCode = $this->getStatusCode($exception);
        $document = array(
            'exception' => $exception,
            'additionalInformation' => \is_array($exceptionContext) ? json_encode($exceptionContext) : '',
            'reference_code' => $exception instanceof FlowException ? $exception->getReferenceCode() : null,
            'log_level' => $logLevel,
            'short_message' => sprintf('%d %s', $statusCode, Response::getStatusMessageByCode($statusCode)),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        );

        if ($this->securityContext !== null && $this->securityContext->isInitialized()) {
            $accountInformation = $this->getAccountInformation();
            $document = array_merge($document, $accountInformation);
        }

        if (Bootstrap::$staticObjectManager instanceof ObjectManagerInterface) {
            $requestInformation = $this->getRequestInformation();
            $document = array_merge($document, $requestInformation);
        }

        return new Document('', $document, self::EXCEPTION_TYPE);
    }

    /**
     * Returns information about the currently authenticated user.
     *
     * @return array
     */
    protected function getAccountInformation(): array
    {
        $account = $this->securityContext->getAccount();
        $accountInformation = [];

        if ($account !== null) {
            $accountIdentifier = $this->persistenceManager->getIdentifierByObject($account);
            $accountInformation['authenticated_account'] = $account->getAccountIdentifier();
            $accountInformation['authenticated_account'] .= ' (' . $accountIdentifier . ')';
            $accountInformation['authenticated_roles'] = implode(', ', array_keys($this->securityContext->getRoles()));
            if ($this->objectManager->isRegistered(PartyService::class)) {
                /** @var PartyService $partyService */
                $partyService = $this->objectManager->get(PartyService::class);
                $person = $partyService->getAssignedPartyOfAccount($account);
                $personIdentifier = $this->persistenceManager->getIdentifierByObject($person);
                if ($person instanceof Person) {
                    $accountInformation['authenticated_person'] = (string)$person->getName();
                    $accountInformation['authenticated_person'] .= ' (' . $personIdentifier . ')';
                }
            }
        }

        return $accountInformation;
    }

    /**
     * Returns detail information about the current request if we have an active request handler.
     *
     * @return array
     */
    protected function getRequestInformation(): array
    {
        $bootstrap = Bootstrap::$staticObjectManager->get(Bootstrap::class);
        /* @var Bootstrap $bootstrap */
        $requestHandler = $bootstrap->getActiveRequestHandler();

        $requestInformation = [];
        if ($requestHandler instanceof HttpRequestHandlerInterface) {
            $request = $requestHandler->getHttpRequest();
            $requestInformation = array(
                'request_domain' => $request->getHeader('Host'),
                'request_remote_addr' => $request->getClientIpAddress(),
                'request_path' => $request->getRelativePath(),
                'request_uri' => $request->getUri()->getPath(),
                'request_user_agent' => $request->getHeader('User-Agent'),
                'request_method' => $request->getMethod(),
                'request_port' => $request->getPort()
            );
        }

        return $requestInformation;
    }

    /**
     * Returns the status code of the given exception. If the code is configured in skipStatusCodes, we return just
     * zero.
     *
     * @param FlowException $exception
     * @return int
     */
    protected function getStatusCode($exception): int
    {
        $statusCode = 0;
        $skippedStatusCodes = $this->settings['skipStatusCodes'] ?? [];

        if ($exception instanceof FlowException) {
            $statusCode = $exception->getStatusCode();
        }

        return \in_array($statusCode, $skippedStatusCodes, true) ? 0 : $statusCode;
    }

    /**
     * Returns elastic search index via elastica library.
     *
     * @return Index|null
     */
    public function getElasticIndex(): ?Index
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
