<p align="center">
    <a href="https://www.noerdisch.de" target="_blank">
        <img src="https://cdn.rawgit.com/markusguenther/abe70d34f4a4621aed0ef504c5d0192b/raw/5bf0f3df328e58ba7aad067a56cbd1c15ef69491/logo_full.svg" width="300">
    </a>
</p>

[![Packagist](https://img.shields.io/packagist/l/noerdisch/elasticlog.svg?style=flat-square)](https://packagist.org/packages/noerdisch/elasticlog)
[![Packagist](https://img.shields.io/packagist/v/noerdisch/elasticlog.svg?style=flat-square)](https://packagist.org/packages/noerdisch/elasticlog)
[![Maintainability](https://api.codeclimate.com/v1/badges/bc9a4fe4b5c15d103e89/maintainability)](https://codeclimate.com/github/noerdisch/Noerdisch.ElasticLog/maintainability)
[![Twitter Follow](https://img.shields.io/twitter/follow/noerdisch.svg?style=social&label=Follow&style=flat-square)](https://twitter.com/noerdisch)


# Nœrdisch ElasticLog

The Noerdisch.ElasticLog Flow package logs exceptions and single messages to a configured elastic search server. This
package also provides a backend to log message of Flows Logger classes to a elastic search server.

## Installation & configuration

Just add "noerdisch/elasticlog" as dependency to your composer.json and run a "composer update" in your project's root folder
or simply execute:
```
composer require noerdisch/elasticlog
```
from your project's root.

Configure your Elastic Server:
```yaml
Noerdisch:
  ElasticLog:
    Connection:
        host: '127.0.0.1'
        port: 9200
        index: 'myIndex42'
```

We provide a command controller to setup you elastic search index. You can use it to create the index or to
reset the logger.

```bash
./flow elasticindex:setup
```


### Manual logging


If you wish to log normal log messages to your elastic server just use the provided `ElasticLoggerInterface`:

```php
use Neos\Flow\Annotations as Flow;
use Noerdisch\ElasticLog\Log\ImportLoggerInterface;

class SomeClass 
{
    /**
     * @Flow\Inject
     * @var ImportLoggerInterface
     */
    protected $logger;

    public function yourMethod()
    {
      $this->logger->log('Your Message')
    }
}

```

By default messages will also be logged to the `SystemLoggerInterface` when Flow runs in `Development` context. You
can enable or disable this function with a setting:

```yaml
Noerdisch:
  ElasticLog:
    Logger:
      backendOptions:
        alsoLogWithSystemLogger: true
```


### Logging backend

To configure ElasticBackend as the default logging backend, put this in your Settings.yaml:

```
Neos:
  Flow:
    log:
      systemLogger:
        backend: Noerdisch\ElasticLog\Log\Backend\ElasticBackend
      securityLogger:
        backend: Noerdisch\ElasticLog\Log\Backend\ElasticBackend
      sqlLogger:
        backend: Noerdisch\ElasticLog\Log\Backend\ElasticBackend
      i18nLogger:
        backend: Noerdisch\ElasticLog\Log\Backend\ElasticBackend
```

### Log exceptions


Activate the exception handler and configure the connection to your elastic search server in your Settings.yaml:

```yaml
Neos:
  Flow:
    error:
      exceptionHandler:
        className: 'Noerdisch\ElasticLog\Error\ElasticLogExceptionHandler'
```

Now all Exceptions that are shown to the Web or CLI are logged to elastic.

*Note:* For `Development` context, the `Neos.Flow` package overrides this setting. Make sure to add this configuration
in the right context Settings.yaml.

If you want to log additionally *all* Exceptions to elastic search you should replace the systemLogger as well.
This will log all errors that are logged with the SystemLogger to ElasticLog as well to the disk.
By default Flow will only log a single line to the system log aka "See also ... .txt".
The ElasticLogger will also log the full Exception.

```yaml
Neos:
  Flow:
    log:
      systemLogger:
        logger: Noerdisch\ElasticLog\Log\ElasticLogger
```


#### Filter exceptions

To skip certain exceptions from being logged you can either use the `skipStatusCodes` setting:

```yaml
Noerdisch:
  ElasticLog:
     # don't log any exceptions that would result in a HTTP status 403 (access denied) / 404 (not found)
    skipStatusCodes: [403, 404]
```

### Thanks

The package was build on the Graylog package from [Yeebase](https://github.com/yeebase/Yeebase.Graylog).
Thanks to the nice people from Yeebase for sharing it. Checkout there repositories on github. They also ❤️ Neos and
the Neos flow framework.

We did not use Graylog and wanted to use elastic without the man in the middle.