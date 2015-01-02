<?php
namespace rOpenDev\DataTablesPHP\Test;

use rOpenDev\DataTablesPHP\DataTable;
use PHPUnit_Framework_Assert;

class DatatableTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that true does in fact equal true
     */
    public function setUp()
    {
        $this->table = DataTable::instance('phpunit');
    }

    public function testSettingJSParamsAndAlias()
    {
        $table = $this->table;

        $table->setJsInitParam('autoWidth', true);
        $this->assertSame(['autoWidth'=>true], PHPUnit_Framework_Assert::readAttribute($table, 'jsInitParameters'));

        $table->setJsInitParams([
            'deferRender' => false,
            'data'        => [
                ["Tiger Nixon", "System Architect", "$3,120", "2011/04/25", "Edinburgh", 5421],
            ],
        ]);
        $this->assertContains(false, PHPUnit_Framework_Assert::readAttribute($table, 'jsInitParameters'));


        $dom = '<\'row dtfRow\'<\'col-xs-4\'i><\'col-xs-4\'r><\'col-xs-4\'l>>t<\'row\'<\'col-xs-12\'p>>';
        $table->setDom($dom);
        $this->assertSame($dom, PHPUnit_Framework_Assert::readAttribute($table, 'jsInitParameters')['dom']);

        $table->setServerSide([
            'uri' => '/ajax',
            'type'=> 'POST',
        ]);

        $table->setAjax('/ajax');

        $table->setData([
            ["Tiger Nixon Clone 1", "System Architect", "$3,120", "2011/04/25", "Edinburgh", 5421],
        ]);

        $this->assertTrue($table->isServerSide() === true);
    }

    public function testSettingColumns()
    {
        $table = $this->table;

        $table->setColumn(['title' => 'Name']);

        $this->assertTrue('Name' == PHPUnit_Framework_Assert::readAttribute($table, 'columns')[0]['title']);

        $table->setColumns([
            ['title' => 'Work'],
            ['title' => 'Salary'],
            ['title' => 'Date (of Birth)'],
            ['title' => 'City'],
            ['title' => 'Ninja Power'],
        ]);
        $this->assertTrue('Ninja Power' == PHPUnit_Framework_Assert::readAttribute($table, 'columns')[5]['title']);
    }

}
