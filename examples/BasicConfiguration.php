<?php
/**
 * Generate Javascript & Html + set Initial Parameters for datatables.js
 */

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
<html>
	<head>
		<title>Basic Configuration : Generate Html and Javascript</title>
		<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.1-rc2/jquery.min.js"></script>
		<link href="//cdn.datatables.net/1.10.0-rc.1/css/jquery.dataTables.css" rel="stylesheet">
		<script src="//cdn.datatables.net/1.10.0-rc.1/js/jquery.dataTables.js"></script>
		<script src="https://raw.githubusercontent.com/RobinDev/jquery.dataTables.columnFilter.js/master/jquery.dataTables.columnFilter.js"></script>
		<script>
		$(document).ready(function() {
			<?php echo $dataTable->getJavascript(); ?>
		});
		</script>
	</head>
	<body>

<?php echo $dataTable->getHtml();
