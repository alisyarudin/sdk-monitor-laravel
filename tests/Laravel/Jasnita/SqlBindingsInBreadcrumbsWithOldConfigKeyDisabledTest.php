<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

use Illuminate\Config\Repository;

class SqlBindingsInBreadcrumbsWithOldConfigKeyDisabledTest extends JasnitaLaravelTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $config = $app['config']->all();

        $config['jasnita']['breadcrumbs.sql_bindings'] = false;

        $app['config'] = new Repository($config);
    }

    public function testSqlBindingsAreRecordedWhenDisabledByOldConfigKey()
    {
        $this->assertFalse($this->app['config']->get('jasnita')['breadcrumbs.sql_bindings']);

        $this->dispatchLaravelEvent('illuminate.query', [
            $query = 'SELECT * FROM breadcrumbs WHERE bindings = ?;',
            ['1'],
            10,
            'test',
        ]);

        $lastBreadcrumb = $this->getLastBreadcrumb();

        $this->assertEquals($query, $lastBreadcrumb->getMessage());
        $this->assertFalse(isset($lastBreadcrumb->getMetadata()['bindings']));
    }
}
