<?php

declare(strict_types=1);

namespace Levinine\CodeceptSlim4\Module;

use Codeception\Configuration;
use Codeception\Lib\Framework;
use Codeception\TestInterface;
use Levinine\CodeceptSlim4\Lib\Connector\Client as Connector;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

final class Slim4 extends Framework
{
    /**
     * @var App
     */
    public $app;

    /**
     * @var ServerRequestInterface
     */
    public $slimRequest;

    public function _initialize(): void
    {
        require Configuration::projectDir() . 'public/index.php';

        if (isset($app)) {
            $this->app = $app;
        }

        if (isset($request)) {
            $this->slimRequest = $request;
        }

        parent::_initialize();
    }

    public function _before(TestInterface $test): void
    {
        $this->client = new Connector();
        $this->client->setApp($this->app);
        $this->client->setSlimRequest($this->slimRequest);

        parent::_before($test);
    }

    public function _after(TestInterface $test): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        parent::_after($test);
    }
}
