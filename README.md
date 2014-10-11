DataTablesPHP : PHP class to easily use DataTables.js
================================================

##Table of contents
* [Description](#description)
	* [Features](#features)
* [Todo](#todo)
* [Installation](#installation)
    * [Packagist](https://packagist.org/packages/ropendev/datatablesphp)
* [Example](#example)
* [Documentation](#documentation)
* [DataTablesPHP](http://www.robin-d.fr/DataTablesPHP/)

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

##Todo
It will come soon :
* Add compatibility with Doctrine
* Make best documentation
* AutoGenerate select's options if there is data (l.469)
* Example with export function

##Installation

You can clone this git, download the class or use Composer :
```json
{
    "require": {
        "ropendev/datatablesphp": "dev-master"
    }
}
```

##Example

```php
<?php

include '../DataTable.php';

use rOpenDev\DataTablesPHP\DataTable;

$columns = array(
	array('title'=>'Rendering Engine'),
	array('title'=>'Browser'),
	array('title'=>'Platform'),
	array(
		'title'=>'Engine Version',
		'className'=>'right'
	),
	array(
		'title'=>'Css Grade',
		'className'=>'right',
		'sFilter' => array('type'=>'text')
	)
);

$data = array(
	array('Trident', 'Internet Explorer 4.0', 'Win 95+', '5', 'c'),
	array('Trident', 'Internet Explorer 4.0', 'Win 95+', '5', 'c'),
	array('Trident', 'Internet Explorer 5.0', 'Win 99+', '6', 'd'),
	array('Trident', 'Internet Explorer 5.0', 'Win 99+', '6', 'd'),
	array('Trident', 'Internet Explorer 6.0', 'Win 98+', '4', 'e'),
	array('Trident', 'Internet Explorer 6.0', 'Win 98+', '4', 'e'),
	array('Trident', 'Internet Explorer 7.0', 'Win 97+', '3', 'f'),
	array('Trident', 'Internet Explorer 7.0', 'Win 97+', '3', 'f'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 14.10', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 14.10', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 14.10', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 13.10', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 13.10', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 13.10', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 12.04', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 12.04', '2', 'c'),
	array('Gecko', 'Mozilla Firefox 27.0', 'Ubuntu 12.04', '2', 'c')
);

// You can use Plug-in too : http://datatables.net/plug-ins/i18n/#How-to-use
$language = array(
	'emptyTable' => 'Aucune donnée à afficher',
	'info' => '_START_ à _END_ sur _TOTAL_ lignes',
	'infoEmpty' => '(Aucune donnée à afficher)',
	'infoFiltered' => '(filtré de _MAX_ éléments au total)',
	'infoPostFix' => '',
	'thousands' => ' ',
	'lengthMenu' => '_MENU_ lignes par page',
	'loadingRecords' => 'Chargement en cours...',
	'processing' => 'Traitement en cours...',
	'search' => 'Rechercher&nbsp;:',
	'zeroRecords' => 'Aucune donnée à afficher.',
	'paginate' => array(
		'first'   =>'Premier',
		'last'    =>'Dernier',
		'next'    =>'Suivant',
		'previous'=>'Précédent'
	),
	'aria' => array(
		'sortAscending'  => ': activer pour trier la colonne par ordre croissant',
		'sortDescending' => ': activer pour trier la colonne par ordre décroissant'
	)
);

$dataTable = DataTable::instance('example');
$dataTable->setJsInitParam('language', $language)
          ->setColumns($columns)
          ->setData($data);

?>
<head>
	<title>Basic Configuration Example</title>
	<script src="http://code.jquery.com/jquery-2.1.1.min.js"></script>
	<link href="//cdn.datatables.net/1.10.3/css/jquery.dataTables.css" rel="stylesheet">
	<script src="//cdn.datatables.net/1.10.3/js/jquery.dataTables.js"></script>
	<script>
	$(document).ready(function() {
		<?php echo $dataTable->getJavascript(); ?>
	});
	</script>
	<style>
	.right { text-align:right; }
	</style>
</head>
<?php echo $dataTable->getHtml(); ?>
```

##Documentation
```php
DataTable::instance('id')
    ->setJsInitParam($key, $value)			// http://datatables.net/reference/option/
    ->setJsInitParams($params)       		// To set all params in one time
    ->setDom($dom)                     		// Alias for setJsInitParameter('dom', $dom)
    ->setColumn($params)					  // Add a column and there options to the table:
											 // - Initialization Javascript Options (see the doc : DataTables.net > Refererences > Column)
											//  - PHP Options (parent for complexe header, sFilter, sql_table, sqlFilter... see l.169)
    ->setColumns($columns)             		// Add columns
    ->setUnsetColumns($unsetColumns)		// To add in the SQL select columns wich are not print directly as table column (and use their data with formatter)
    ->setUnsetColumn($uColumn)				// Same for only one
    ->setServerSide($ajax)               	// http://datatables.net/reference/option/ajax
    ->setAjax($ajax)               		 	// Alias for setJsInitParameter('ajax', $ajax)
    ->setFilters($ajax)               	 	// Set permanent filters for sql queries (where)
    ->setData($data)                    	// Permit to set the data in the DataTables Javascript Initialization.
    ->setHeader($bool)               		// To generate thead when you will call getHtml
    ->setFooter($bool)               		// To generate tfoot with th empty when you will call getHtml.
											// ... automatically called if you have set individual column filters


DataTable::instance('id')->getJavascript();  // Return javascript string. It is not embeding JS Files from DataTables.js... only it activation
											// and individual column filtering stuff

DataTable::instance('id')->getHtml([array('class'=>'my_table_class', 'data-nuclear'=>'bomb')]);        // Return html table in a string

/*** Server-Side Functions ***/
# You can't use server side options if you didn't set Columns

DataTable::instance('id')
	->setFrom($table)																								// Name of the table to query
	->setJoin('table2', array('table'=>'column', 'table2'=>'column2') [, $join = 'LEFT JOIN', $duplicate = false])	// Table to join
	->setPdoLink($pdoLink)																							// Add PHP PDO class link

DataTable::instance('id')->exec($_REQUEST[, $csv = false]);  // Output the json results
															//or export to csv format (use setInitFilter before if you use Individual column Filters)
DataTable::instance('id')->sendFatal($error);     			// Output an error
```

A php array for a column can contain :
* Properties for Initialization Javascript (see `self::$columnParams` or http://datatables.net/reference/option/)
* `parent` (`=>$title`) : To have a complex header with a colspan... Set the same $title to put multiple column under the same th
* `sFilter` (`=>array`) : for the column filtering options (see `self::$columFilteringParams`)
* SQL params (`sql_name` and `sql_table`) : if there is different from data or default table set with setFrom
* `alias` : sql alias (not required)
* `formatter` (`=>function($columnValue,$rowValues,$columnParams)`) : if you want to print your data with a special format, set a function
In a Server-Side Request, if you don't want select a SQL column, just don't set `sql_name` or `data` properties (but set a `formatter` function to print something !).
