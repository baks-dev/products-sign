# BaksDev Product Sign

[![Version](https://img.shields.io/badge/version-7.1.0-blue)](https://github.com/baks-dev/products-sign/releases)
![php 8.3+](https://img.shields.io/badge/php-min%208.3-red.svg)

Модуль Честный знак продукции

## Установка

``` bash
$ composer require baks-dev/products-sign
```

## Дополнительно

Установка файловых ресурсов в публичную директорию (javascript, css, image ...):

``` bash
$ php bin/console baks:assets:install
```

Изменения в схеме базы данных с помощью миграции

``` bash
$ php bin/console doctrine:migrations:diff

$ php bin/console doctrine:migrations:migrate
```

Тесты

``` bash
$ php bin/phpunit --group=products-sign
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

