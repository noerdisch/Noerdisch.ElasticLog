<p align="center">
    <img src="https://cdn.rawgit.com/markusguenther/abe70d34f4a4621aed0ef504c5d0192b/raw/5bf0f3df328e58ba7aad067a56cbd1c15ef69491/logo_full.svg" width="300">
</p>

[![Packagist](https://img.shields.io/packagist/l/noerdisch/elasticlog.svg?style=flat-square)](https://packagist.org/packages/noerdisch/elasticlog)
[![Packagist](https://img.shields.io/packagist/v/noerdisch/elasticlog.svg?style=flat-square)](https://packagist.org/packages/noerdisch/elasticlog)
[![Twitter Follow](https://img.shields.io/twitter/follow/noerdisch.svg?style=social&label=Follow&style=flat-square)](https://twitter.com/noerdisch)


# Nœrdisch ElasticLog

The Noerdisch.ElasticLog Flow package logs exceptions and single messages to a configured elastic search server. This
package also provides a backend to log message of Flows Logger classes to a elastic search server.

The package was build on the Graylog package from Yeebase. Thanks to the nice people from Yeebase for sharing it.
We did not use Graylog and wanted to use elastic without the man in the middle.

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
