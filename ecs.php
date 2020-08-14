<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('sets', ['clean-code', 'common', 'dead-code', 'php71', 'psr12', 'strict']);

    $parameters->set('paths', [__DIR__ . '/src']);
};
