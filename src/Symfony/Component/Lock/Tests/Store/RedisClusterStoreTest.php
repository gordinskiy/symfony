<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use PHPUnit\Framework\SkippedTestSuiteError;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension redis
 *
 * @group integration
 */
class RedisClusterStoreTest extends AbstractRedisStoreTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\RedisCluster::class)) {
            throw new SkippedTestSuiteError('The RedisCluster class is required.');
        }
        if (!getenv('REDIS_CLUSTER_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_CLUSTER_HOSTS env var is not defined.');
        }
    }

    /**
     * @return \RedisCluster
     */
    protected function getRedisConnection(): object
    {
        return new \RedisCluster(null, explode(' ', getenv('REDIS_CLUSTER_HOSTS')));
    }
}
