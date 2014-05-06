DataTablesPHP : PHP class for easily use DataTables.js
================================================

##Table of contents
* [Description](#description)
	* [Features](#features)
* [Todo](#todo)
* [Installation](#installation)
    * [Packagist](https://packagist.org/packages/ropendev/datatablesphp)
* [Examples](#examples)
* [Documentation](#documentation)
* [DataTablesPHP](http://www.robin-d.fr/DataTablesPHP/)

##Description

DataTablesPHP generates easily your DataTable Html or Javascript... with Server-Side or not. It enhances the filters's utilisation with the [jquery.dataTables.columnFilter.js (unofficial plugin)](https://github.com/RobinDev/jquery.dataTables.columnFilter.js).

**Compatible with the last version of DataTables (1.10.x).**

###Features
* Generate html table (complexe header)
* Generate the Javascript related (Data can be set in the initial parameters or load via Ajax or Server-Sive)
* Custom search column by column
* Analyze Servert-Side Request and Generate SQL queries
    * Can handle complexe query (join)
    * Can handle Optimize Query to search value (not only Like %, you can parameter to use =,<,>,<=,>=,BETWEEN...)
* Using all this features in the same time permits to easily handle a dataTable with PHP

Server-side part inspired from [Allan Jardine's Class SSP](https://github.com/DataTables/DataTables/blob/master/examples/server_side/scripts/ssp.class.php). Improve in order to don't trust user input, add the join possibilities and more...

##Todo
It will come soon :
* Leave columnFilters plugin and replace it with PHP (soon)
* Add compatibility with Doctrine
* Clean the code
* Make documentation

##Installation

You can clone this git, download the class or use Composer :
```json
{
    "require": {
        "ropendev/datatablesphp": "dev-master"
    }
}
```

##Examples

There is examples in the example directory.

```php
<?php
include '../DataTable.php';
use rOpenDev\DataTablesPHP\DataTable;

$columns = array(
	array('title'=>'Rendering Engine'),
	array('title'=>'Browser'),
	array('title'=>'Platform'),
	array('title'=>'Engine Version', 'class'=>'right'),
	array('title'=>'Css Grade', 'class'=>'right')
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

$dataTable = DataTable::instance('exmample');
$dataTable->setJsInitParameter('language', $language)
          ->setColumns($columns)
          ->setColumnFilterActive()
          ->setData($data);

?>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.1-rc2/jquery.min.js"></script>
<link href="//cdn.datatables.net/1.10.0-rc.1/css/jquery.dataTables.css" rel="stylesheet">
<script src="//cdn.datatables.net/1.10.0-rc.1/js/jquery.dataTables.js"></script>
<script src="https://raw.githubusercontent.com/RobinDev/jquery.dataTables.columnFilter.js/master/jquery.dataTables.columnFilter.js"></script>
<script>
$(document).ready(function() {
	<?php echo $dataTable->getJavascript(); ?>
});
</script>
<?php echo $dataTable->getHtml(); ?>
```

##Documentation
```php
DataTable::instance('id')
    ->setJsInitParameter($key, $value)   // http://datatables.net/reference/option/
    ->setJsInitParameterNC($key, $value) // For set in the value a javascript function
    ->setJsInitParameters($params)       // To set all params in one time
    ->setDom($dom)                       // Alias for setJsInitParameter('dom', $dom)
    ->setColumn($jsParams[, $name])      // Add a column and there options to the table
    ->setColumns($columns)               // Add columns
    ->setUnsetColumns($unsetColumns)	 // To add in the SQL select columns wich are not print directly as table column
    ->setData($data)                     // For not server-side table wich have data in a php array
    ->setServerSide($ajax)               // http://datatables.net/reference/option/ajax
    ->setAjax($ajax)               		 // Alias for setJsInitParameter('ajax', $ajax)
    ->setFooter($bool)                   // To generate tfoot with th empty when you will call getHtml
    ->setColumnFilterActive($bool);      // Active the use of https://github.com/RobinDev/jquery.dataTables.columnFilter.js

DataTable::instance('id')->getJavascript();  // Return javascript string. It is not embeding JS Files from DataTables.js... only it activation

DataTable::instance('id')->getHtml([array('class'=>'my_table_class', 'data-nuclear'=>'bomb')]);        // Return html table in a string

/*** Server-Side Functions ***/
# You can't use server side options if you didn't set Columns

DataTable::instance('id')->setFrom($table); // Name of the table to query

DataTable::instance('id')->setJoin('table2', array('table'=>'column', 'table2'=>'column2') [, $join = 'LEFT JOIN']); // Table to join

DataTable::instance('id')->generateSQLRequest($request);                     // Generate 3 SQL queries to execute
DataTable::instance('id')->sendData($data, $recordsFiltered, $recordsTotal); // Output the json results
DataTable::instance('id')->sendFatal($error);      // Output an error
```

A php array for a column can contain :
* Properties for Initialization Javascript (see `self::$columnParams` or http://datatables.net/reference/option/)
* `parent` (`=>$title`) : To have a complex header with a colspan... Set the same $title to put multiple column under the same th
* `sFilter` (`=>array`) : for the column filtering options (see `self::$columFilteringParams`)
* SQL params (`sql_name` and `sql_table`) : if there is different from data or default table set with setFrom
* `formatter` (`=>function([$column,]$row)`) : if you want to print your data with a special format, set a function
In a Server-Side Request, if you don't want select a SQL column, just don't set `sql_name` or `data` properties (but set a `formatter` function to print something !).
