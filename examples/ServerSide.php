<?php
/*
 * Surely, it's another class, your model, wich look after your data.
 * For example purpose, we will use directly pdo with a demo data set
 * on the database datatables_demo (you need to create it). The script
 * will load demo data set
 */
$pdo = new PDO('mysql:host=localhost', 'root', 'admin', array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT));
$pdo->exec('USE datatables_demo');
$pdo->exec(file_get_contents('datatables_demo.sql'));
/* ### ### ### ### ### ### ### ### ### ### ### ### ### ### ### ### ### */

include '../DataTable.php';
use rOpenDev\DataTablesPHP\DataTable;

$columns = array(
	'first_name'=> array('title'=>'First Name'),
	'last_name' => array('title'=>'Last Name'),
	'email' 	=> array('title'=>'Email', 'orderable' => false, 'searchable'=>false),
	'office'	=> array('title'=>'Office'),
	'age' 		=> array('title'=>'Age', 'class'=>'right'),
	'sal' 		=> array('title'=>'Salary', 'class'=>'right', 'formatter'=>null, 'sql_name' => 'salary')
);

$unsetColumns = array(
	'id' => array('table'=>'datatables_demo')
);

$ajax = $_SERVER["REQUEST_URI"];
$ajax = array(
	'uri' => $_SERVER["REQUEST_URI"],
	'type'=> 'POST'
);
$dataTable = DataTable::instance('oTable');
$dataTable->setColumns($columns)
          ->setColumnFilterActive()
          ->setServerSide($ajax);


if(isset($_REQUEST['draw'])) {

	$dataTable->setTable('datatables_demo');

	$queries = $dataTable->generateSQLRequest($_REQUEST);

	$q = $pdo->query($queries['data']);
	if($pdo->errorInfo()[0] != '00000')
		$dataTable->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2]);
	$data = $q->fetchAll();

	$q = $pdo->query($queries['recordsFiltered']);
	if($pdo->errorInfo()[0] != '00000')
		$dataTable->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2]);
	$recordsFiltered = $q->fetch()['count'];

	$q = $pdo->query($queries['recordsTotal']);
	if($pdo->errorInfo()[0] != '00000')
		$dataTable->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2]);
	$recordsTotal = $q->fetch()['count'];

	$dataTable->sendData($data, $recordsFiltered, $recordsTotal);
	exit();//Juste au cas où
}
 // <script src="http://jquery-datatables-column-filter.googlecode.com/svn/trunk/media/js/jquery.dataTables.columnFilter.js"></script>
?>
<html>
	<head>
		<title>Server-Side</title>
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


function dbv($mixed, $exit = true){
	echo '<pre>'.CHR(10); var_dump($mixed); echo '</pre>'.CHR(10);
	if($exit) exit;
}
