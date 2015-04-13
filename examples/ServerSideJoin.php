<?php
/*
 * Surely, it's another class, your model, wich look after your data.
 * For example purpose, we will use directly pdo with a demo data set
 * on the database datatables_demo (you need to create it). The script
 * will load demo data set
 */
$pdo = new PDO('mysql:host=localhost', 'root', 'admin', array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT));
$pdo->exec('USE datatables_demo');
$pdo->exec(file_get_contents('datatables_demo_join.sql'));
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
    ),
    array(
        'data' => 'last_name',
        'title' => 'Last Name',
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
        'sql_table' => 'datatables_demo_join_salary',
        'sql_name' => 'salary',
        'title' => 'Salary',
        'className' => 'right',
    ),
    array(
        'title' => 'Delete',
        'formatter' => function ($data) {return '[X='.$data['id'].']';},
        'orderable' => false,
        'searchable' => false,
    ),
    [
        'data' => 'id',
        'show' => false,
    ],
);

$ajax = array(
    'uri' => $_SERVER["REQUEST_URI"],
    'type' => 'GET',
);
$dataTable = DataTable::instance('oTable');
$dataTable->setColumns($columns)
          ->setServerSide($ajax);

if (isset($_REQUEST['draw'])) {
    $dataTable->setPdoLink($pdo);
    $dataTable->setFrom('datatables_demo_join');
    $dataTable->setJoin('datatables_demo_join_salary', array('datatables_demo_join' => 'id', 'datatables_demo_join_salary' => 'id'));
    //$dataTable->setCounterActive(false);

    $dataTable->exec($_REQUEST);
}
 // <script src="http://jquery-datatables-column-filter.googlecode.com/svn/trunk/media/js/jquery.dataTables.columnFilter.js"></script>
?>
<html>
	<head>
		<title>Server-Side Join</title>
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
