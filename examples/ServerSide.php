<?php


$pdo = new PDO('mysql:host=localhost', 'root', 'admin', array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT));
$pdo->exec('USE datatables_demo');
$pdo->exec(file_get_contents('datatables_demo.sql'));
/* ### ### ### ### ### ### ### ### ### ### ### ### ### ### ### ### ### */

include '../vendor/autoload.php';

use rOpenDev\DataTablesPHP\DataTable;

$columns = array(
    array(
        'data' => 'first_name',
        'title' => 'First Name',
        'sFilter'    => array(
            'type' => 'text',
        ),
        'parent' => 'Name details',
    ),
    array(
        'data' => 'last_name',
        'title' => 'Last Name',
        'parent' => 'Name details',
    ),
    array(
        'data' => 'email',
        'title' => 'Email',
        'orderable' => false,
        'searchable' => false,
    ),
    array(
        'data' => 'office',
        'title' => 'Office',
    ),
    array(
        'data' => 'age',
        'title' => 'Age',
        'className' => 'right',
    ),
    array(
        'data' => 'sal',
        'title' => 'Salary',
        'className' => 'right',
        'formatter' => null,
        'sql_name' => 'salary',
    ),
    array(
        'data' => 'id',
        'show' => false,
    ),
);

$ajax = array(
    'uri' => $_SERVER["REQUEST_URI"],
    'type' => 'POST',
);
$dataTable = DataTable::instance('oTable');
$dataTable->setColumns($columns)
          ->setServerSide($ajax);
$dataTable->renderFilterOperators = false;

if (isset($_REQUEST['draw'])) {
    $dataTable->setFrom('datatables_demo')->setPdoLink($pdo);
    $dataTable->exec($_REQUEST);
}
?>
<html>
	<head>
		<title>Server-Side</title>
		<script src="http://code.jquery.com/jquery-2.1.1.min.js"></script>
		<link href="//cdn.datatables.net/1.10.3/css/jquery.dataTables.css" rel="stylesheet">
		<script src="//cdn.datatables.net/1.10.3/js/jquery.dataTables.js"></script>
		<script>
		$(document).ready(function() {
			<?php echo $dataTable->getJavascript(); ?>
		});
		</script>
	</head>
	<body>

<?php echo $dataTable->getHtml();
