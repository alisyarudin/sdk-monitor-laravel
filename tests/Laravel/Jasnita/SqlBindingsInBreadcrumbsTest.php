<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

class SqlBindingsInBreadcrumbsTest extends JasnitaLaravelTestCase
{
    public function testSqlBindingsAreRecordedWhenEnabled()
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.sql_bindings' => true,
        ]);

        $this->assertTrue($this->app['config']->get('jasnita.breadcrumbs.sql_bindings'));

        $this->dispatchLaravelEvent('illuminate.query', [
            $query = 'SELECT * FROM breadcrumbs WHERE bindings = ?;',
            $bindings = ['1'],
            10,
            'test',
        ]);

        $lastBreadcrumb = $this->getLastBreadcrumb();

        $this->assertEquals($query, $lastBreadcrumb->getMessage());
        $this->assertEquals($bindings, $lastBreadcrumb->getMetadata()['bindings']);
    }

    public function testSqlBindingsAreRecordedWhenDisabled()
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.sql_bindings' => false,
        ]);

        $this->assertFalse($this->app['config']->get('jasnita.breadcrumbs.sql_bindings'));

        $this->dispatchLaravelEvent('illuminate.query', [
            $query = 'SELECT * FROM breadcrumbs WHERE bindings <> ?;',
            ['1'],
            10,
            'test',
        ]);

        $lastBreadcrumb = $this->getLastBreadcrumb();

        $this->assertEquals($query, $lastBreadcrumb->getMessage());
        $this->assertFalse(isset($lastBreadcrumb->getMetadata()['bindings']));
    }
}
