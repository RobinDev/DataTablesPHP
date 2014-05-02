DataTablesPHP : PHP class for easily use DataTables.js
================================================

##Table of contents
* [Description](#description)
* [Todo](#todo)
* [Installation](#installation)
    * [Packagist](https://packagist.org/packages/ropendev/datatablesphp)
* [Examples](#examples)
* [DataTablesPHP](http://www.robin-d.fr/DataTablesPHP/)

##Description

DataTablesPHP generates easily your DataTable Html or Javascript... or the twice. More, this class generates the SQL queries to execute and if you return the results to the class, it will output at the json format. Enhance the filters's utilisation with the [jquery.dataTables.columnFilter.js (unofficial plugin)](https://github.com/RobinDev/jquery.dataTables.columnFilter.js).

**Compatible with the last version of DataTables (1.10.x).**

Server-side part inspired from [Allan Jardine's Class SSP](https://github.com/DataTables/DataTables/blob/master/examples/server_side/scripts/ssp.class.php). Improve in order to don't trust user input.

## Todo
It will come in the next days :
* **Add compatibility with JOIN in sql request** => Now you can !
* Add compatibility with Doctrine
* Clean the code
* Make documentation

##Installation

[Composer](http://getcomposer.org) is recommended for installation.
```json
{
    "require": {
        "ropendev/datatablesphp": "dev-master"
    }
}
```
```
composer update
```

##Examples

There is examples in the example directory.

###Simple Usage
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

### All the options
```php
DataTable::instance('TableId')->setJsInitParameter($key, $value)   // http://datatables.net/reference/option/
                              ->setJsInitParameterNC($key, $value) // For set in the value a javascript function
                              ->setJsInitParameters($params)       // To set all params in one time
                              ->setDom($dom)                       // Alias for setJsInitParameter('dom', $dom)
                              ->setColumn($jsParams[, $name])      // Add a column and there options to the table
                              ->setColumns($columns)               // Add columns
                              ->setData($data)                     // For not server-side table wich have data in a php array
                              ->setServerSide($ajax)               // http://datatables.net/reference/option/ajax
                              ->setFooter($bool)                   // To generate tfoot with th empty when you will call getHtml
                              ->setColumnFilterActive($bool);      // Active the use of https://github.com/RobinDev/jquery.dataTables.columnFilter.js

DataTable::instance('TableId')->getJavascript();  // Return javascript string. It is not embeding JS Files from DataTables.js... only it activation

DataTable::instance('TableId')->getHtml();        // Return html table in a string

/*** Server-Side Functions ***/
# You can't use server side options if you didn't set Columns

DataTable::instance('TableId')->setTable($table); // Name of the table to query

DataTable::instance('TableId')->setJoin($table, array('table'=>'column', 'table'=>'column') [, $join = 'LEFT JOIN'); // Table to join

DataTable::instance('TableId')->generateSQLRequest($request);                     // Generate 3 SQL queries to execute
DataTable::instance('TableId')->sendData($data, $recordsFiltered, $recordsTotal); // Output the json results
DataTable::instance('TableId')->sendFatal($error);                                // Output an error

```
