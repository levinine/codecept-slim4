<?php

declare(strict_types=1);

namespace Levinine\CodeceptSlim4\Lib\Connector;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Slim\App;
use Slim\Psr7\Cookies;
use Slim\Psr7\Environment;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Slim\Psr7\Uri;
use Slim\ResponseEmitter;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;

final class Client extends AbstractBrowser
{
    /**
     * Special HTTP headers that do not have the "HTTP_" prefix
     *
     * @var array
     */
    protected static $special = [
        'CONTENT_TYPE' => 1,
        'CONTENT_LENGTH' => 1,
        'PHP_AUTH_USER' => 1,
        'PHP_AUTH_PW' => 1,
        'PHP_AUTH_DIGEST' => 1,
        'AUTH_TYPE' => 1,
    ];

    /**
     * @var App
     */
    private $app;

    /**
     * @var ServerRequestInterface
     */
    private $slimRequest;

    public function setApp(App $app): void
    {
        $this->app = $app;
    }

    public function setSlimRequest(ServerRequestInterface $slimRequest): void
    {
        $this->slimRequest = $slimRequest;
    }

    public static function createFromString(string $uri): UriInterface
    {
        if (! is_string($uri) && ! method_exists($uri, '__toString')) {
            throw new InvalidArgumentException('Uri must be a string');
        }

        $parts = parse_url($uri);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : '';
        $user = isset($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) ? $parts['pass'] : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? $parts['port'] : null;
        $path = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? $parts['fragment'] : '';

        return new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $pass);
    }

    public static function createFromEnvironment(array $environment): array
    {
        $data = [];
        $environment = self::determineAuthorization($environment);
        foreach ($environment as $key => $value) {
            $key = strtoupper($key);
            if (isset(static::$special[$key]) || strpos($key, 'HTTP_') === 0) {
                if ($key !== 'HTTP_CONTENT_LENGTH') {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    public static function determineAuthorization(array $environment): array
    {
        $authorization = $environment['HTTP_AUTHORIZATION'] ?? null;

        if (! empty($authorization) || ! is_callable('getallheaders')) {
            return $environment;
        }

        $headers = getallheaders();
        if (! is_array($headers)) {
            return $environment;
        }

        $headers = array_change_key_case($headers, CASE_LOWER);
        if (isset($headers['authorization'])) {
            $environment['HTTP_AUTHORIZATION'] = $headers['authorization'];
        }

        return $environment;
    }

    /**
     * @param BrowserKitRequest $request
     */
    protected function doRequest($request): BrowserKitResponse
    {
        $slimRequest = $this->convertRequest($request);

        $slimResponse = $this->app->handle($slimRequest);
        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($slimResponse);

        return new BrowserKitResponse(
            (string) $slimResponse->getBody(),
            $slimResponse->getStatusCode(),
            $slimResponse->getHeaders()
        );
    }

    private function convertRequest(BrowserKitRequest $request): ServerRequestInterface
    {
        $environment = Environment::mock($request->getServer());
        $uri = static::createFromString($request->getUri());
        $headers = static::createFromEnvironment($environment);
        $cookies = Cookies::parseHeader($headers['Cookie'] ?? []);

        $slimRequest = $this->slimRequest;

        $slimRequest = $slimRequest->withMethod($request->getMethod())
            ->withUri($uri)
            ->withUploadedFiles($this->convertFiles($request->getFiles()))
            ->withCookieParams($cookies);

        foreach ($headers as $key => $header) {
            $slimRequest = $slimRequest->withHeader($key, $header);
        }

        if ($request->getContent() !== null) {
            $body = new StreamFactory();
            $body = $body->createStream($request->getContent());
            $slimRequest = $slimRequest
                ->withBody($body);
        }

        $parsed = [];
        if ($request->getMethod() !== 'GET') {
            $parsed = $request->getParameters();
        }

        // make sure we do not overwrite a request with a parsed body
        if (! $slimRequest->getParsedBody()) {
            $slimRequest = $slimRequest
                ->withParsedBody($parsed);
        }

        return $slimRequest;
    }

    private function convertFiles(array $files): array
    {
        $fileObjects = [];
        foreach ($files as $fieldName => $file) {
            if ($file instanceof UploadedFileInterface) {
                $fileObjects[$fieldName] = $file;
            } elseif (! isset($file['tmp_name']) && ! isset($file['name'])) {
                $fileObjects[$fieldName] = $this->convertFiles($file);
            } else {
                $fileObjects[$fieldName] = new UploadedFile(
                    $file['tmp_name'],
                    $file['name'],
                    $file['type'],
                    $file['size'],
                    $file['error']
                );
            }
        }
        return $fileObjects;
    }
}
