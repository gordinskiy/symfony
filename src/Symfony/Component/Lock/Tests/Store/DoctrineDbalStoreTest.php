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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

class_exists(\Doctrine\DBAL\Platforms\PostgreSqlPlatform::class);

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_sqlite
 */
class DoctrineDbalStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_lock');

        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        $store = new DoctrineDbalStore(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config));
        $store->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 1000000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(): PersistingStoreInterface
    {
        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        return new DoctrineDbalStore(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile], $config));
    }

    public function testAbortAfterExpiration()
    {
        $this->markTestSkipped('Pdo expects a TTL greater than 1 sec. Simulating a slow network is too hard');
    }

    /**
     * @dataProvider provideDsnWithSQLite
     */
    public function testDsnWithSQLite(string $dsn, string $file = null)
    {
        $key = new Key(uniqid(__METHOD__, true));

        try {
            $store = new DoctrineDbalStore($dsn);

            $store->save($key);
            $this->assertTrue($store->exists($key));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public static function provideDsnWithSQLite()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        yield 'SQLite file' => ['sqlite://localhost/'.$dbFile.'1', $dbFile.'1'];
        yield 'SQLite3 file' => ['sqlite3:///'.$dbFile.'3', $dbFile.'3'];
        yield 'SQLite in memory' => ['sqlite://localhost/:memory:'];
    }

    /**
     * @requires extension pdo_pgsql
     *
     * @group integration
     */
    public function testDsnWithPostgreSQL()
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $key = new Key(uniqid(__METHOD__, true));

        try {
            $store = new DoctrineDbalStore('pgsql://postgres:password@'.$host);

            $store->save($key);
            $this->assertTrue($store->exists($key));
        } finally {
            $pdo = new \PDO('pgsql:host='.$host.';user=postgres;password=password');
            $pdo->exec('DROP TABLE IF EXISTS lock_keys');
        }
    }

    /**
     * @dataProvider providePlatforms
     */
    public function testCreatesTableInTransaction(string $platform)
    {
        $conn = $this->createMock(Connection::class);

        $series = [
            [$this->stringContains('INSERT INTO'), $this->createMock(TableNotFoundException::class)],
            [$this->matches('create sql stmt'), 1],
            [$this->stringContains('INSERT INTO'), 1],
        ];

        $conn->expects($this->atLeast(3))
            ->method('executeStatement')
            ->willReturnCallback(function ($sql) use (&$series) {
                if ([$constraint, $return] = array_shift($series)) {
                    $constraint->evaluate($sql);
                }

                if ($return instanceof \Exception) {
                    throw $return;
                }

                return $return ?? 1;
            })
        ;

        $conn->method('isTransactionActive')
            ->willReturn(true);

        $platform = $this->createMock($platform);
        $platform->method(method_exists(AbstractPlatform::class, 'getCreateTablesSQL') ? 'getCreateTablesSQL' : 'getCreateTableSQL')
            ->willReturn(['create sql stmt']);

        $conn->method('getDatabasePlatform')
            ->willReturn($platform);

        $store = new DoctrineDbalStore($conn);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
    }

    public static function providePlatforms(): \Generator
    {
        yield [\Doctrine\DBAL\Platforms\PostgreSQLPlatform::class];

        // DBAL < 4
        if (class_exists(\Doctrine\DBAL\Platforms\PostgreSQL94Platform::class)) {
            yield [\Doctrine\DBAL\Platforms\PostgreSQL94Platform::class];
        }

        yield [\Doctrine\DBAL\Platforms\SqlitePlatform::class];
        yield [\Doctrine\DBAL\Platforms\SQLServerPlatform::class];

        // DBAL < 4
        if (class_exists(\Doctrine\DBAL\Platforms\SQLServer2012Platform::class)) {
            yield [\Doctrine\DBAL\Platforms\SQLServer2012Platform::class];
        }
    }

    public function testTableCreationInTransactionNotSupported()
    {
        $conn = $this->createMock(Connection::class);

        $series = [
            [$this->stringContains('INSERT INTO'), $this->createMock(TableNotFoundException::class)],
            [$this->stringContains('INSERT INTO'), 1],
        ];

        $conn->expects($this->atLeast(2))
            ->method('executeStatement')
            ->willReturnCallback(function ($sql) use (&$series) {
                if ([$constraint, $return] = array_shift($series)) {
                    $constraint->evaluate($sql);
                }

                if ($return instanceof \Exception) {
                    throw $return;
                }

                return $return ?? 1;
            })
        ;

        $conn->method('isTransactionActive')
            ->willReturn(true);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method(method_exists(AbstractPlatform::class, 'getCreateTablesSQL') ? 'getCreateTablesSQL' : 'getCreateTableSQL')
            ->willReturn(['create sql stmt']);

        $conn->expects($this->atLeast(2))
            ->method('getDatabasePlatform');

        $store = new DoctrineDbalStore($conn);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
    }

    public function testCreatesTableOutsideTransaction()
    {
        $conn = $this->createMock(Connection::class);

        $series = [
            [$this->stringContains('INSERT INTO'), $this->createMock(TableNotFoundException::class)],
            [$this->matches('create sql stmt'), 1],
            [$this->stringContains('INSERT INTO'), 1],
        ];

        $conn->expects($this->atLeast(3))
            ->method('executeStatement')
            ->willReturnCallback(function ($sql) use (&$series) {
                if ([$constraint, $return] = array_shift($series)) {
                    $constraint->evaluate($sql);
                }

                if ($return instanceof \Exception) {
                    throw $return;
                }

                return $return ?? 1;
            })
        ;

        $conn->method('isTransactionActive')
            ->willReturn(false);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method(method_exists(AbstractPlatform::class, 'getCreateTablesSQL') ? 'getCreateTablesSQL' : 'getCreateTableSQL')
            ->willReturn(['create sql stmt']);

        $conn->method('getDatabasePlatform')
            ->willReturn($platform);

        $store = new DoctrineDbalStore($conn);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
    }
}
