Redis 3.x Cluster Cache, Session and ActiveRecord for Yii 2
===============================================

This extension provides the [redis](http://redis.io/) key-value store support for the [Yii framework 2.0](http://www.yiiframework.com).
It includes a `Cache` and `Session` storage handler and implements the `ActiveRecord` pattern that allows
you to store active records in redis.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/heyanlong/yii2-redis/v/stable.png)](https://packagist.org/packages/heyanlong/yii2-redis)
[![Total Downloads](https://poser.pugx.org/heyanlong/yii2-redis/downloads.png)](https://packagist.org/packages/heyanlong/yii2-redis)
[![Build Status](https://travis-ci.org/heyanlong/yii2-redis.svg?branch=master)](https://travis-ci.org/heyanlong/yii2-redis)


Requirements
------------

At least redis version 3.0 is required for all components to work properly.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist heyanlong/yii2-redis
```

or add

```json
"heyanlong/yii2-redis": "~2.0.0"
```

to the require section of your composer.json.


Configuration
-------------

To use this extension, you have to configure the Connection class in your application configuration:

```php
return [
    //....
    'components' => [
        'redis' => [
            'class' => 'heyanlong\redis\Connection',
            'master' => [
                '10.155.20.169:6379',
                '10.155.20.167:6391',
                '10.155.20.168:6379',
                '10.155.20.167:6380',
//                'localhost:6379',
            ],
            'database' => 0,
        ],


    ]
];
```

Cluster Cache Configuration
---------------------------

By default Redis Cluster doesn't support MGET/MSET queries from several cluster nodes like this:

```
MGET {user10}.name {user10}.email {user10}.pass {user20}.name {user20}.email {user20}.pass someNotHashedKey
```

To implement this feature you can use ClusterCache:

```php
return [
    //....
    'components' => [
		'class' => 'heyanlong\redis\ClusterCache',
        'redis' => [
            'class' => 'heyanlong\redis\Connection',
            'master' => [
                '10.155.20.169:6379',
                '10.155.20.167:6391',
                '10.155.20.168:6379',
                '10.155.20.167:6380',
//                'localhost:6379',
            ],
            'database' => 0,
        ],


    ]
];
```

ClusterCache will group keys by hash, and divide one query to several queries:

```
MGET {user10}.name {user10}.email {user10}.pass
MGET {user20}.name {user20}.email {user20}.pass
MGET someNotHashedKey
```

...execute them, and then merge results.