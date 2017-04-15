<?php

use Codeception\Lib\Driver\Db;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationExtension extends \Codeception\Extension
{

    protected $enabled = false;

    /**
     * @var Db
     */
    protected $driver;

    // list events to listen to
    public static $events
        = [
            'suite.before' => 'beforeSuite',
            'suite.after'  => 'afterSuite',
            'test.before'  => 'beforeTest',
        ];

    public function beforeSuite(\Codeception\Event\SuiteEvent $e)
    {
        $settings = $e->getSettings();
        if ( ! isset($settings['modules']['config']['Migration'])
             || ! $settings['modules']['config']['Migration']['enabled']
        ) {
            $this->enabled = false;

            return;
        }
        if ( ! $this->hasDbConnection()) {
            $this->writeln('Cannot use Migration module without Db module');
            $this->enabled = false;

            return;
        }

        if ($this->config['type'] === 'sqlite') {
            $this->dropDatabase();
        }

        $this->connect($settings['modules']['config']['Db']['dsn'], $settings['modules']['config']['Db']['user'],
            $settings['modules']['config']['Db']['password']);

        // Drop all tables and recreate migration
        if ($this->config['type'] === 'mysql') {
            $this->dropTables();
        }
        $this->runMigrations();
    }

    public function beforeTest(\Codeception\Event\TestEvent $e)
    {
        if ( ! $this->enabled) {
            return;
        }
        $this->truncateTables();
        $this->seedDatabase();
        $this->disconnect();
    }

    public function afterSuite(\Codeception\Event\SuiteEvent $e)
    {
        if ( ! $this->enabled) {
            return;
        }
    }

    protected function hasDbConnection()
    {
        return $this->getModule('Db') instanceof \Codeception\Module\Db;
    }

    protected function connect($dsn, $user, $password)
    {
        $this->driver = \Codeception\Lib\Driver\Db::create($dsn, $user, $password);
    }

    protected function disconnect()
    {
        $pdo          = $this->getConnection();
        $pdo          = null;
        $this->driver = null;
    }

    /**
     * Returns database connection
     *
     * @return PDO
     * @throws \Codeception\Exception\ModuleRequireException
     */
    protected function getConnection()
    {
        return $this->driver->getDbh();
    }

    /**
     * Drops all tables
     */
    protected function dropTables()
    {
        $this->writeln('Removing existing tables');
        $this->getConnection()->exec('SET foreign_key_checks = 0');
        $sth    = $this->getConnection()->query('SHOW TABLES');
        $tables = [];
        while ($table = $sth->fetchColumn()) {
            $tables[] = "`{$table}`";
        }
        if ($tables) {
            $tables = implode(',', $tables);
            $this->getConnection()->exec("DROP TABLE IF EXISTS {$tables}");
        }

        $this->getConnection()->exec('SET foreign_key_checks = 1');
    }

    /**
     * Deletes sqlite database
     */
    protected function dropDatabase()
    {
        $path = $this->config['path'];
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Runs migration script
     * Done once at start of test suite
     */
    protected function runMigrations()
    {
        $this->writeln('Running migrations');
        $phinx         = new PhinxApplication();
        $command       = $phinx->find('migrate');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command'       => $command->getName(),
            '--environment' => $this->config['environment'],
        ]);
        if ($this->config['verbose']) {
            $display = $commandTester->getDisplay();
            $this->write($display);
        }
    }

    /**
     * Seeds database with fixture data
     * Done after tables are truncated before each test
     */
    protected function seedDatabase()
    {
        if ($this->config['populate']) {
            $this->writeln('Seeding test data');
        }
    }

    /**
     * Truncates all tables
     * Done before each test
     */
    protected function truncateTables()
    {
        if ($this->config['cleanup']) {
            $this->writeln('Clearing out existing test data');
            $this->getConnection()->exec('SET foreign_key_checks = 0');
            $sth = $this->getConnection()->query('SHOW TABLES');
            while ($table = $sth->fetchColumn()) {
                $this->getConnection()->exec("TRUNCATE `{$table}`");
            }
            $this->getConnection()->exec('SET foreign_key_checks = 1');
        }

    }


}
