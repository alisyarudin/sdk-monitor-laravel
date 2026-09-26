<?php // Dihasilkan tools/rebrand.php dari hulu sdk-laravel — jangan diubah manual.

namespace Jasnita\Monitor\Laravel\Tests;

class SqlQueriesInBreadcrumbsTest extends JasnitaLaravelTestCase
{
    public function testSqlQueriesAreRecordedWhenEnabled()
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.sql_queries' => true,
        ]);

        $this->assertTrue($this->app['config']->get('jasnita.breadcrumbs.sql_queries'));

        $this->dispatchLaravelEvent('illuminate.query', [
            $query = 'SELECT * FROM breadcrumbs WHERE bindings = ?;',
            ['1'],
            10,
            'test',
        ]);

        $lastBreadcrumb = $this->getLastBreadcrumb();

        $this->assertEquals($query, $lastBreadcrumb->getMessage());
    }

    public function testSqlQueriesAreRecordedWhenDisabled()
    {
        $this->resetApplicationWithConfig([
            'jasnita.breadcrumbs.sql_queries' => false,
        ]);

        $this->assertFalse($this->app['config']->get('jasnita.breadcrumbs.sql_queries'));

        $this->dispatchLaravelEvent('illuminate.query', [
            'SELECT * FROM breadcrumbs WHERE bindings <> ?;',
            ['1'],
            10,
            'test',
        ]);

        $this->assertEmpty($this->getCurrentBreadcrumbs());
    }
}
