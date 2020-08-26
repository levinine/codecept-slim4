# Codeception Slim 4 Module

This module allows you to run tests inside [Slim 4 Microframework](http://www.slimframework.com/).

## Install

Via commandline:

```shell
composer require --dev levinine/codecept-slim4
```

## Config

Make sure to have `config.test.yml` file containing your test configuration in the root of your Slim project.

Enable module with `depends`, eg in `tests/functional.suite.yml`

```yaml
modules:
  enabled:
    - \Helper\Functional
    - REST:
        depends: \Levinine\CodeceptSlim4\Module\Slim4
```

## Develop

If on commit ECS check fails, execute:

```shell
vendor/bin/ecs check src --fix
```
