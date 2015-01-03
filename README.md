[DataTablesPHP : PHP class to easily use DataTables.js](http://www.robin-d.fr/DataTablesPHP/)
================================================

[![Build Status](https://travis-ci.org/RobinDev/DataTablesPHP.svg?branch=master)](https://travis-ci.org/RobinDev/DataTablesPHP)

##Table of contents
* [Description](#description)
    * [Features](#features)
* [Installation](#installation)
    * [Packagist](https://packagist.org/packages/ropendev/datatablesphp)
* [Example](#example)
* [Documentation](#documentation)
* [License](#license)
* [Todo](#todo)

##Description

DataTablesPHP generates easily your DataTable Html or Javascript... with Server-Side or not. It enhances the filters's utilisation adding individual column filtering (with or without server-side).

**Compatible with the last version of DataTables (1.10.x).**

###Features
* Generate html table (complexe header)
* Generate the Javascript related (Data can be set in the initial parameters or load via Ajax or Server-Sive)
* Custom search column by column and complex search to optimize SQL queries
* Analyze Servert-Side Request and Generate SQL queries
    * Can handle complexe query (join)
    * Can handle Optimize Query to search value (not only Like %, you can parameter to use =,<,>,<=,>=,BETWEEN...)
* Using all this features in the same time permits to easily handle a dataTable with PHP

Server-side part inspired from [Allan Jardine's Class SSP](https://github.com/DataTables/DataTables/blob/master/examples/server_side/scripts/ssp.class.php). Improve in order to don't trust user input, add the join possibilities and more...

##Installation

You can clone this git, download the class or use Composer :
```bash
composer require ropendev/datatablesphp
```

##Example

See in the `examples` folder.

##Documentation
```php
DataTable::instance('id')
    ->setJsInitParam($key, $value)           // http://datatables.net/reference/option/
    ->setJsInitParams($params)               // To set all params in one time
    ->setDom($dom)                           // Alias for setJsInitParameter('dom', $dom)
    ->setColumn($params, $show = true)       // Add a column and there options to the table:
                                             // - Initialization Javascript Options (see the doc : DataTables.net > Refererences > Column)
                                             //  - PHP Options (parent for complexe header, sFilter, sql_table, sqlFilter... see l.169)
                                             // if($show) will be printed in the table else will only be load via ajax
    ->setColumns($columns)                   // Add columns
    ->setServerSide($ajax)                   // http://datatables.net/reference/option/ajax
    ->setAjax($ajax)                         // Alias for setJsInitParameter('ajax', $ajax)
    ->setFilters($ajax)                      // Set permanent filters for sql queries (where)
    ->setData($data)                         // Permit to set the data in the DataTables Javascript Initialization.
    ->setHeader($bool)                       // To generate thead when you will call getHtml
    ->setFooter($bool)                       // To generate tfoot with th empty when you will call getHtml.
                                             // ... automatically called if you have set individual column filters


DataTable::instance('id')->getJavascript();  // Return javascript string. It is not embeding JS Files from DataTables.js... only it activation
                                            // and individual column filtering stuff

DataTable::instance('id')->getHtml([array('class'=>'my_table_class', 'data-nuclear'=>'bomb')]);        // Return html table in a string

/*** Server-Side Functions ***/
# You can't use server side options if you didn't set Columns

DataTable::instance('id')
    ->setFrom($table)                                                                                                 // Name of the table to query
    ->setJoin('table2', array('table'=>'column', 'table2'=>'column2') [, $join = 'LEFT JOIN', $duplicate = false])    // Table to join
    ->setPdoLink($pdoLink)                                                                                            // Add PHP PDO class link
    ->setCounterActive(false)  // Disabled counters (permits to gain in performanche, think to change your infoFiltered)

DataTable::instance('id')->exec($_REQUEST[, $csv = false]);  // Output the json results
                                                             //or export to csv format (use setInitFilter before if you use Individual column Filters)
DataTable::instance('id')->sendFatal($error);                // Output an error
```

A php array for a column can contain :
* Properties for Initialization Javascript (see `self::$columnParams` or http://datatables.net/reference/option/)
* `parent` (`=>$title`) : To have a complex header with a colspan... Set the same $title to put multiple column under the same th
* `sFilter` (`=>array`) : for the column filtering options (see `self::$columFilteringParams`)
* SQL params (`sql_name` and `sql_table`) : if there is different from data or default table set with setFrom
* `alias` : sql alias (not required)
* `formatter` (`=>function($columnValue,$rowValues,$columnParams)`) : if you want to print your data with a special format, set a function
In a Server-Side Request, if you don't want select a SQL column, just don't set `sql_name` or `data` properties (but set a `formatter` function to print something !).

##License

MIT (see the `LICENSE` file for details)

##Todo
It will come soon :
* AutoGenerate select's options if there is data (l.469)
* Example with export function

