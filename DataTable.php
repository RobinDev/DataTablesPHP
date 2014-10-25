<?php
/**
 * PHP DataTablesPHP wrapper class for DataTables.js (Html, Javascript & Server Side). http://www.robin-d.fr/DataTablesPHP/
 * Server-side part inspired from [Allan Jardine's Class SSP](https://github.com/DataTables/DataTables/blob/master/examples/server_side/scripts/ssp.class.php)
 *
 * Compatible with DataTables 1.10.x
 *
 * @author     Original Author Robin <contact@robin-d.fr>
 * @link       http://www.robin-d.fr/DataTablesPHP/
 * @link       https://github.com/RobinDev/DataTablesPHP
 * @since      File available since Release 2014.05.01
 */

// formatter : A approfondir avec call_user_func_array

namespace rOpenDev\DataTablesPHP;

use \Exception;

class DataTable {

	/**
	 * footer: Permet de dessiner le footer via html
	 * header: Idem pour le thead
	 */
	protected $footer = false, $header = false;

	/**
	 * Columns's number to print
	 */
	protected $iThead = 0;

	/**
	 * Initialization's Parameters for dataTables.js
	 */
	protected $jsInitParameters = array(), $jsonNotCompatible=array();

	/**
	 * Column to print and sql column to load
	 */
	protected $columns = array(), $unsetColumns=array();

	/**
	 * PDO Link
	 */
	protected $pdoLink;

	/**
	 * @var Contain initial Filters
	 */
	protected $filters = array();

	/**
	 * @var If exactCount == true the filtered line counter will not use the precedent request but do a new.
	 * Utile dans le cas de certains join.
	 */
	 public $exactCount = false;

	 /**
	  * @var Var contain other information to send to the dataTable via Json Server-Side. Eg : sql queries for debug.
	  */
	 protected $toSend = null;

	 /**
	  * @var bool Active the visualization of operators when rendering fields
	  */
	public $renderFilterOperators = true;

	/**
	 * @var bool is Active Individual Column Filtering
	 */
	protected $individualColumnFiltering = false;


	private static $instance = null;

	/**
	 * Correspond to the column options for DataTables javascript initialization (see the doc : DataTables > Refererences > Column)
	 */
	protected static $columnParams = array(
	    'name',			// Did we really care about the name ?  It's for API
	    'data',			// data source string|object ... object pour Sort !! http://datatables.net/examples/ajax/orthogonal-data.html http://datatables.net/reference/option/columns.data
						// if data === null = pas de donnée à afficher
	    'title',
	    'defaultContent',
	    'width',
	    'visible',
	    'type',			// numeric|date
	    'searchable',
	    'render',
	    'orderable',
	    'orderDataType',	// We don't care because the search is did by SQL not JS ?!
	    'orderData',		// Pour trier une colonne en fonction d'autres colonnes
	    'orderSequence',	//
	    'createdCell',		// Callback pour personnaliser la cellule en fonction de la donnée
	    'contentPadding',
	    'className',		 // Alias of class ????!!!!! => A vérifier
	    'cellType'			 // To set TH
	);

	/**
	 * Column options for search
	 */
	static protected $columFilteringParams = array(
		'type'  => array('text', 'number', 'select', 'date'),		// string text|number|number-range|select|date-range
		'regex' => true,	// bool
		'values'=>array(),	// array For Select purpose
		//'operator'=>false,	// bool	 For text purpose (to add an operator Select)
	);

	public static function instance($tableName = 'datatable') {
		 $cls = get_called_class();
		if(!isset(self::$instance[$tableName]))
			self::$instance[$tableName] = new $cls($tableName);
		return self::$instance[$tableName];
	}

    function __construct($tableName) {
		$this->tableName = $tableName;
    }

	/**
	 * Add a dataTables.js Init Param (old name setJsInitParameter)
	 *
	 * @param	str		$key	Contain param's key
	 * @param	str		$value	Contain param's value
	 */
    function setJsInitParam($key, $value) {
		if(is_string($value) && strpos(str_replace(' ', '', $value), 'function(')===0) {
			$this->setJsInitParam($key, $id = md5($value));
			$this->jsonNotCompatible[0][] = '"'.$id.'"';
			$this->jsonNotCompatible[1][] = $value;
		} else {
			$this->jsInitParameters[$key] = $value;
		}
		return $this;
	}

	/**
	 * Ajouter les paramêtres d'initialisation via un tableau
	 *
	 * @params	array	$params		Contain dataTables.js Init Params
	 */
	function setJsInitParams($params) {
		foreach($params as $k=>$v) {
			$this->setJsInitParam($k, $v);
		}
		return $this;
	}

	/**
	 * Alias for setJsInitParam('dom', $dom)
	 */
	function setDom($dom) {
		$this->setJsInitParam('sDom', $dom);
		return $this;
	}

	/**
	 * Add a column to the table
	 *
	 * @param array $params 							Can contain :
	 * 					$key		str		$v			- Properties for Initialization Javascript (see self::$columnParams)
	 * 					'parent'	str		$title		- Permit to have a complex header with a colspan...
	 * 													- Set the same $title to put multiple column under the same th
	 * 					'sFilter'	array	   			- For the column filtering options (see self::$columFilteringParams)
	 * 					'sql_table'	str					- Column's sql table
	 * 					'sql_name'	str					- Column's sql name if different from data
	 * 					'sqlFilter'	str					- Initial SQL filter
	 */
	function setColumn($params) {

		$k = isset($params['parent'])?md5($params['parent'], true):++$this->iThead;
		if(isset($params['parent'])) {
			$this->theadChild[] = array('title'=>$params['title'], 'className'=>isset($params['className'])?$params['className']:null);
			$this->thead[$k]['colspan'] = !isset($this->thead[$k]['colspan'])?1:($this->thead[$k]['colspan']+1);
			$this->thead[$k]['title']   = $params['parent'];
			$this->header = true;
		} else {
			if(isset($params['title']))			$this->thead[$k]['title']   = $params['title'];
			if(isset($params['className']))		$this->thead[$k]['className'] = $params['className'];
		}

		if(isset($params['sFilter'])) {
			if(isset($params['sFilter']['type']) && in_array($params['sFilter']['type'], self::$columFilteringParams['type'])) {
				$params['sFilter'] = array_merge(self::$columFilteringParams, $params['sFilter']);
				$this->individualColumnFiltering = true;
				$this->footer = true;
			} else {
				unset($params['sFilter']);
			}
		}

		$this->columns[] = $params;


		return $this;
	}

	function setColumns($columns) {
		foreach($columns as $jsParams)
			$this->setColumn($jsParams);
		return $this;
	}

	function setUnsetColumn($column) {
		$this->unsetColumns[] = $column;
		return $this;
	}

	function setUnsetColumns($columns) {
		foreach($columns as $c)
			$this->unsetColumns[] = $c;
		return $this;
	}

	/**
	 * Initialize loading data via Ajax
	 * /!/ Doesn't yet support Custom data get function !
	 *
	 * @param string|array $ajax
	 */
	function setServerSide($ajax) {
		$this->setJsInitParam('serverSide', true);
		$this->setAjax($ajax);
		return $this;
	}

	/**
	 * Alias for setJsInitParam('ajax', $ajax)
	 * /!/ Doesn't yet support Custom data get function !
	 *
	 * @param string|array $ajax
	 */
	function setAjax($ajax) {
		if(is_string($ajax)) {
			$ajax = array(
				'url' => $ajax,
				'type'=> 'POST'
			);
		}
		$this->setJsInitParam('ajax', is_array($ajax)||is_object($ajax)?(object)$ajax:(string)$ajax);
		return $this;
	}

	/**
	 * Set permanent filters for sql queries (where)
	 */
	function setFilters($filters) {
		$this->filters = $filters;
		/**
		foreach($filters as $k => $v) {
			$this->setInitFilter($this->getColumn($k), $v);
		}
		/**/
		return $this;
	}

	function getColumn($k) {
		foreach($this->columns as $c) {
			if($c['data'] == $k) {
				return $c;
			}
		}
	}

	/**
	 * Alias for setJsInitParam('data', $data);
	 * Permit to set the data in the DataTables Javascript Initialization.
	 *
	 * @param array $data
	 */
	function setData($data) {
		$k = array_keys($data[0]); // Check the first key  from the first line
		$objectize = is_string($k[0]) ? true : false;
		for($i=0;$i<count($data);++$i)
			$data[$i] = $objectize ? (object) $data[$i] : $data[$i];
		$this->setJsInitParam('data',  $data);
		$this->renderFilterOperators = false;
		return $this;
	}

	/**
	 * To generate header in html
	 */
	function setHeader($bool) {
		$this->header = $bool;
		return $this;
	}

	/**
	 * To generate Footer in html
	 */
	function setFooter($bool) {
		$this->footer = $bool;
		return $this;
	}

	/**
	 * Is it configured to load data from server-side ?
	 *
	 * @return bool
	 */
	function isServerSide() {
		return isset($this->jsInitParameters['ajax']) ? true : false;
	}


	/**
	 * Return columns for js with only JS parameters (remove the other parameters we use in PHP)
	 */
	protected function getColumnsForJs() {
		foreach($this->columns as $c) {
			$c = self::getColumnForJs($c);
			if($c!==null) {
				$rColumns[] = $c;
			}
		}
		return isset($rColumns)?$rColumns:null;
	}

	/**
	 * Format column for javascript : only Keep the required options for columns and convert to object.
	 *
	 * @param array $column
	 * @return object|null
	 */
	static protected function getColumnForJs($column) {
		foreach($column as $k=>$v) {
			if(in_array($k, self::$columnParams)) {
				$rColumn[$k] = $v;
			}
		}
		return isset($rColumn) ? (object) $rColumn : null;
	}

	/**
	 * Return javascript string to activate table
	 */
	function getJavascript() {

		if(!empty($this->columns))
			$this->setJsInitParam('columns', $this->getColumnsForJs());

		$js = 'var '.$this->tableName.' = $("#'.$this->tableName.'").dataTable('."\n"
				.(!empty($this->jsInitParameters) ? json_encode((object) $this->jsInitParameters,  JSON_PRETTY_PRINT) : '')."\n"
			.')';

		/** We set the javascript function in the intial parameters **/
		if(isset($this->jsonNotCompatible[0]))
			$js = str_replace($this->jsonNotCompatible[0], $this->jsonNotCompatible[1], $js);

		$js .= ';'."\n";

		if($this->individualColumnFiltering) {
			$js .= ''
			.'var '.$this->tableName.'Api = '.$this->tableName.'.api(true);'."\n"
			.'$("#'.$this->tableName.' tfoot th").each( function(colI) {'."\n"
				.'$(".sSearch", this).on("keyup change", function (e) {'."\n"
				.($this->isServerSide() ? '	if( e.keyCode == 13 ) {'."\n" : '')
				.'		'.$this->tableName.'Api.column( colI ).search('
				.'			'.($this->renderFilterOperators ? '( this.tagName != "INPUT" ||  this.value == \'\' ? \'\' : $(this).prev().find("select").val()) + this.value ' : ' this.value ')
				.'		).draw();'."\n"
				.($this->isServerSide() ? '	}'."\n" : '')
				.'});'."\n"
			.'});'."\n";
		}

		return $js;
	}

	/**
	 * Return html table (min <table id=datatable></table>)
	 *
	 * @params	array	$tAttributes	To add attributes to the <table> eg. : class=>table-green
	 */
	function getHtml($tAttributes = array()) {

		$tAttributes['id'] = $this->tableName.(isset($tAttributes['id']) ? ' '.$tAttributes['id'] : '');
		$html = '<table'.self::mapAttributes($tAttributes).'>';

		if($this->header === true) {
			$html .= '<thead>';
			$html .= '<tr>';
			foreach($this->thead as $v) {
				$html .= '<th'.(isset($v['className'])?' class="'.$v['className'].'"':'').' '.(isset($v['colspan'])?'colspan='.$v['colspan']:'rowspan=2').'>'
							.(isset($v->title)?$v->title:(isset($v['title']) ? $v['title'] : ''))
						 .'</th>';
			}
			$html .= '</tr>';
			if(isset($this->theadChild)) {
				$html .= '<tr>';
				foreach($this->theadChild as $v) {
					$html .= '<th'.(isset($v['className'])?' class="'.$v['className'].'"':'').'>'.(isset($v->title)?$v->title:'').'</th>';
				}
				$html .= '</tr>';
			}
			$html .= '</thead>';
		}

		if($this->footer === true) {
			$html .= '<tfoot>';
				$html .= '<tr>';
				foreach($this->columns as $k => $v) {
					$html .= '<th>'.(isset($v['sFilter'])?$this->renderFilter($v):'').'</th>'; //'.(isset($v->className)?' class="'.$v->className.'"':'').'
					// Ajouter un input si $v contient un array search ///// A détailler
				}
				$html .= '</tr>';
			$html .= '</tfoot>';
		}

		$html .= '</table>';

		return $html;
	}

	function formatOption($name, $value, $label) {
		return '<option value="' . $value .'"'
					.(isset($this->filters[$name]) && strpos($this->filters[$name], $value) === 0 ? ' selected' : '')
				.'>'.(isset($label) ? $label : $value).'</option>';
	}

	function renderFilter($column) {

		$sFilter = $column['sFilter'];

		switch($sFilter['type']) {
			case 'text'   :
				$r = '<div class="input-group"><div class="input-group-addon">'
						.($this->renderFilterOperators ?
						'<select class=form-control>'
							.'<option></option>'
							.$this->formatOption($column['data'], '[!]', '!')
							.$this->formatOption($column['data'], '[=]', '=')
							.$this->formatOption($column['data'], '[!=]', '!=')
							.$this->formatOption($column['data'], 'reg:', 'REGEXP')
						.'</select>':'').'</div>'
						.'<input class="form-control sSearch" type="text" value="'.(isset($column['data']) && isset($this->filters[$column['data']]) ? preg_replace('/^(\[(!|<=|>=|=|<|>|<>|!=)\]|reg:|in:)/i', '', $this->filters[$column['data']]) : '').'">'
					.'</div></div>';
				return $r;
				break;
			case 'number' : case 'date' :
				$r = '<div class="input-group"><div class="input-group-addon">'
						.($this->renderFilterOperators ?
						'<select class=form-control>'
							.'<option></option>'
							.$this->formatOption($column['data'], '[=]', '=')
							.$this->formatOption($column['data'], '[!=]', '!=')
							.$this->formatOption($column['data'], '[<=]', '<=')
							.$this->formatOption($column['data'], '[<]', '<')
							.$this->formatOption($column['data'], '[>=]', '>=')
							.$this->formatOption($column['data'], '[>]', '>')
							.$this->formatOption($column['data'], 'in:', 'IN(int,int,int...)')
							.$this->formatOption($column['data'], 'reg:', 'REGEXP')
						.'</select>':'').'</div>'
						.'<input class="form-control sSearch" type="text" value="'.(isset($this->filters[$column['data']]) ? preg_replace('/^(\[(<=|>=|=|<|>|<>|!=)\]|reg:|in:)/i', '', $this->filters[$column['data']]) : '').'">'
					.'</div></div>';
				return $r;
				break;
			case 'select' :
				$o='';
				if(isset($sFilter['values'])) {
					foreach($sFilter['values'] as $k=>$v) {
						$o .= '<option value="'.$k.'"'.(isset($column['data']) && isset($this->filters[$column['data']]) && $this->filters[$column['data']] == $k ? ' selected':'').'>'.$v.'</option>';
					}
				}
				/** Auto-generate options if the data are loaded **/
				elseif(isset($this->jsInitParameters['data'])) {
					// TODO
				}
				return '<select class="form-control sSearch"><option value=""></option>'.$o.'</select>';
				break;
		}
	}

	/**
	 * html render function
	 */
	static protected function mapAttributes($attributes) {
		return ' '.join(' ', array_map(
		function($sKey) use ($attributes) {
			return is_bool($attributes[$sKey]) ? ($attributes[$sKey]?$sKey:'') : $sKey.'="'.$attributes[$sKey].'"';
		}, array_keys($attributes)));
	}




	/*** ########## Server Side ########## ***/


	/**
	 * Add the pdo link
	 */
	function setPdoLink($link) {
		$this->pdoLink = $link;
		return $this;
	}

	/**
	 * Set the mysql table to request
	 */
	function setFrom($table, $alias = null) {
		$this->table = $table;
		$this->aliasTable = $alias === null ? $table : $alias;
		return $this;
	}

	/**
	 * Join data from other tables
	 *
	 * @param string $table  .
	 * @param array  $on     Must contains two elements like key:sql_table => value:sql_column
	 * @param string $join   .
	 * @param array  $duplicate
	 * 					0	Contain a column id... Generally the same that on ON
	 * 					1 	Contain column or columns
	 */
	function setJoin($table, $on, $join = 'LEFT JOIN', $duplicate = false) {
		$on2 = array_keys($on);
		$this->join[] = $join.' '.$table.' ON `'.key($on).'`.`'.current($on).'` = `'.next($on2).'`.`'.next($on).'`';
		//$this->join[] = $join.' `'.$table.'` ON `'.$table.'`.`'.$on[$table].'` = `'.$this->table.'`.`'.$on[$this->table].'`';
		if($duplicate !== false) {
			$this->patchDuplicateRow['pKey'] = $duplicate[0];
			if(is_array($duplicate[1])) {
				$this->patchDuplicateRow['columns'] = isset($this->patchDuplicateRow['columns']) ? array_merge($this->patchDuplicateRow['columns'], $duplicate[1]) : $duplicate[1];
			} else {
				$this->patchDuplicateRow['columns'][] = $duplicate[1];
			}
		}
		return $this;
	}

	/**
	* Create the data output array for the DataTables rows
	*
	* @param array $data Data from the SQL get
	* @return array Formatted data in a row based format
	*/
	protected function data_output($data) {
		$out = array();
		$data=array_values($data); // Reset keys
		for ( $i=0, $ien=count($data) ; $i<$ien ; ++$i ) {
			$row = array();

			for ( $j=0, $jen=count($this->columns) ; $j<$jen ; ++$j ) {
				$column = $this->columns[$j];

				if ( isset( $column['formatter'] ) ) {
					if(isset($column['data'])||isset($column['sql_name']))
						$row[ isset($column['data'])?$column['data']:$j ] = $column['formatter']( $data[$i][ self::fromSQLColumn($column) ], $data[$i], $column );
					else
						$row[ $j ] = $column['formatter']( $data[$i] );
				}
				else {
					// Compatibility with the json .
					// if preg_match('#\.#', $column['data']) explode ('.', $colum['data'])...
					if(isset($column['data'])||isset($column['sql_name']))
						$row[ isset($column['data'])?$column['data']:$j ] = $data[$i][ self::fromSQLColumn($column) ];
					else
						$row[ $j ] = '';
				}

			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Generate the SQL queries (optimized for MariaDB/MySQL) to execute.
	 *
	 * @param  array $dtRequest Request send by DataTables.js ($_GET or $_POST)
	 * @return array SQL queries to execute (keys: data, recordsFiltered, recordsTotal)
	 */
	protected function generateSQLRequest($dtRequest) {
		$this->request = $dtRequest;

		$limit = $this->limit();
		$order = $this->order();
		$where = $this->filter();

		foreach($this->columns as $c) {
			if(isset($c['data'])||isset($c['sql_name'])) {
				$columns[] = $this->toSQLColumn($c);
			}
		}

		foreach($this->unsetColumns as $c)
			$columns[] = $this->toSQLColumn($c);

		$join = isset($this->join) ? ' '.implode(' ', $this->join) : '';

		return array(
			'data' 			  => 'SELECT '
									.($this->exactCount === false ? 'SQL_CALC_FOUND_ROWS ':'')
									.implode(',',$columns).' FROM '
									.$this->table.($this->table != $this->aliasTable ? ' '.$this->aliasTable : '')
									.$join
									.' '.$where.' '.$order.' '.$limit.';',
			'recordsFiltered' => $this->exactCount === false ?
									'SELECT FOUND_ROWS() count;'
									: 'SELECT COUNT(1) as count FROM '
									.$this->table.($this->table != $this->aliasTable ? ' '.$this->aliasTable : '')
									.$join.' '.$where.' '.$order,
			'recordsTotal'	  => 'SELECT COUNT(*) count FROM '.$this->table.';'
		);
	}

	/**
	 * Paging : Construct the LIMIT clause for server-side processing SQL query
	 *
	 * @return string SQL limit clause
	 */
	protected function limit() {
		if (isset($this->request['start']) && $this->request['length'] != -1) {
			return 'LIMIT '.intval($this->request['start']).','.intval($this->request['length']);
		}
	}

	/**
	 * Ordering : Construct the ORDER BY clause for server-side processing SQL query
	 *
	 * @return string SQL order by clause
	 */
	protected function order() {
		$order = '';
		if ((!isset($this->request['draw']) || (isset($this->request['draw']) && $this->request['draw'] != 1)) && isset($this->request['order']) && count($this->request['order'])) {
			$orderBy = array();
			for ($i=0,$ien=count($this->request['order']);$i<$ien;$i++) {
				$columnIdx = intval($this->request['order'][$i]['column']);
				$column = $this->columns[$columnIdx];
				if (!isset($column['orderable']) || $column['orderable'] === true) {
					$orderBy[] = $this->toSQLColumn($column, 2).' '.($this->request['order'][$i]['dir'] === 'asc' ? 'ASC' : 'DESC');
				}
			}
			$order = 'ORDER BY '.implode(', ', $orderBy);
		}
		return $order;
	}

	function setInitFilter($column, $filter) {
		$this->initColumnSearch[] = $this->generateSQLColumnFilter($this->toSQLColumn($column, 2, true), $filter);
	}

	/**
	 * Searching/Filtering : Construct the WHERE clause for server-side processing SQL query.
	 */
	protected function filter() {
		$initColumnSearch = isset($this->initColumnSearch) ? $this->initColumnSearch : array();
		$globalSearch = array();
		$columnSearch = array();


		// Individual column initial filtering
		foreach($this->columns as $c) {
			if(isset($c['sqlFilter'])) {
				$initColumnSearch[] = $this->generateSQLColumnFilter($this->toSQLColumn($c, 2), $c['sqlFilter']);
			}
		}

		// Global Search
		if ( isset($this->request['search']) && !empty($this->request['search']['value'])) {

			for ( $i=0, $ien=count($this->request['columns']) ; $i<$ien ; $i++ ) {
				$column = $this->columns[$i];
				if (!isset($column['searchable']) || $column['searchable'] === true) {
					$globalSearch[] = $this->toSQLColumn($column, 2).' LIKE '.$this->pdoLink->quote('%'.$this->request['search']['value'].'%');
				}
			}
		}

		// Individual column filtering
		if(isset($this->individualColumnFiltering)) {
			$this->sRangeSeparator = isset($this->sRangeSeparator) ? $this->sRangeSeparator : '~';
			for ( $i=0, $ien=count($this->columns) ; $i<$ien ; $i++ ) {
				$column = $this->columns[$i];
				if ((!isset($column['searchable']) || $column['searchable'] === true) && !empty($this->request['columns'][$i]['search']['value'])) {
					$search_value = trim($this->request['columns'][$i]['search']['value']);
					$key = $this->toSQLColumn($column, 2, true);
					$columnSearch[] = $this->generateSQLColumnFilter($key, $search_value);
				}
			}
		}


		// Combine the filters into a single string
		$where = '';

		if ( count( $initColumnSearch ) ) {
			$where .= implode(' AND ', $initColumnSearch);
		}

		if ( count( $globalSearch ) ) {
			$where .=  ($where === '' ? '' : ' AND ').'('.implode(' OR ', $globalSearch).')';
		}

		if ( count( $columnSearch ) ) {
			$where .= ($where === '' ?  '' : ' AND '). implode(' AND ', $columnSearch);
		}

		return $where = $where !== '' ? 'WHERE '.$where : '';
	}

	/**
	 * Generate the filter for a column
	 * Why static ? Because i needed to use the same function in a other project.
	 */
	static function _generateSQLColumnFilter($column, $search_value, $pdoLink, $sRangeSeparator = '~') {

		if(preg_match("/^\[(=|!=)\]$/i", $search_value, $match)) {
			return $column.' '.$match[1].' '.$pdoLink->quote('');
		}
		if(preg_match("/^\[(!|<=|>=|=|<|>|<>|!=)\](.+)/i", $search_value, $match)) {

			if($match[1] == '!=' && $match[2] == 'null') {
				return $column.' IS NOT NULL';
			}
			elseif($match[1] == '=' && $match[2] == 'null') {
				return $column.' IS NULL';
			}
			elseif($match[1] == '!') {
				return $column.' NOT LIKE '.$pdoLink->quote('%'. $match[2] .'%');
			}
			else {
				return $column.' '.$match[1].' '.$pdoLink->quote($match[2] == '-empty-' ? '' : $match[2]);
			}
		}

		elseif(strpos($search_value, 'reg:') === 0) { //&& $column['sFilter']['regex'] === true) {
			return $column.' REGEXP '.$pdoLink->quote(substr($search_value, strlen('Reg:')));
		}

		elseif(preg_match('/(.*)(!?'.$sRangeSeparator.')(.*)/i', $search_value, $matches) && !empty($matches[1]) && !empty($matches[3])) {
				// TODO : use type to format the search value (eg STR_TO_DATE)
				return $column.($matches[2][0]=='!'?' NOT':'')
					.' BETWEEN '.$pdoLink->quote($matches[1]).' AND '.$pdoLink->quote($matches[3]);

		}

		elseif(strpos($search_value, 'in:')===0) {
			$search_value = substr($search_value, strlen('in:'));
			return $column .' IN('.$search_value.')';
		}
		elseif(strpos($search_value, '!in:')===0) {
			$search_value = substr($search_value, strlen('in:'));
			return $column .' NOT IN('.$search_value.')';
		}

		else {
			return $column.' LIKE '.$pdoLink->quote('%'.$search_value.'%');
		}
	}


	protected function generateSQLColumnFilter($column, $search_value) {
		return self::_generateSQLColumnFilter($column, $search_value, $this->pdoLink, isset($this->sRangeSeparator) ? $this->sRangeSeparator : '~');
	}

	/**
	 * Give a column's sql name
	 *
	 * @param	$column		array
	 * @param	$onlyAlias	0 => return sql_table.sql_name AS alias
	 * 						1 => return alias
	 * 						2 => return sql_table.sql_name
	 * @param	$filter		Regarder
	 */
	protected function toSQLColumn($column, $onlyAlias = 0, $filter = false) {

		if(!isset($column['sql_name']) && !isset($column['data']))
			self::sendFatal('Houston, we have a problem with one of your column : can\'t draw it SQL name because it don\'t have data or sql_name define.'."\n".json_encode($column));

		if($filter && isset($column['sFilter']) && isset($column['sFilter']['sql_name'])) {
			return $this->toSQLColumn($column['sFilter'], $onlyAlias, false);
		}

		$quote = !isset($column['protect_sql']) || $column['protect_sql']  ? '`' : '';
		// Alias ne correspondrait pas à Name ?!!!!
		$table = isset($column['sql_table']) ? $column['sql_table'] : $this->aliasTable;
		return $onlyAlias === 1 && isset($column['alias']) ?
					$column['alias'] :
					$quote.$table.$quote.(empty($table) ? '' : '.')
						.$quote.(isset($column['sql_name']) ? $column['sql_name'] : $column['data']).$quote
					.($onlyAlias === 0 && isset($column['alias']) ? ' AS '.$column['alias'] : '');
	}

	/**
	 * Similar to toSQLColumn but don't return table... Only the column's name or alias
	 */
	static protected function fromSQLColumn($column) {
		return isset($column['alias']) ? $column['alias'] : (isset($column['sql_name']) ? $column['sql_name'] : $column['data']);
	}

	/**
	 * To avoid duplicate row when LEFT JOIN
	 */
	function patchDuplicateRow($d, $pKey, $columns) {
		$id=$d[$pKey];
		if(isset($rData[$id])) {
			foreach($columns as $c => $separator) {
				$this->rData[$id][$c] .= $separator.$data[$i][$c];
			}
		} else {
			$this->rData[$id] = $d;
		}
	}

	/**
	 * Execute the ajax request and send the result in json
	 */
	function exec($request, $csv = false) {

		if($csv) {
			$this->request['length'] = -1;
		}
		$queries = $this->generateSQLRequest($request);

		$this->addToSend('sqlQuery', $queries['data']);

		//dbv($queries['data']);

		$pdo = $this->pdoLink;

		try {
			$q = $pdo->query($queries['data']);
			$this->rData=array();
			// PATCH : Annoying JOIN wich duplicate row if there is different tag
			while($d=$q->fetch()) {
				if(isset($this->patchDuplicateRow)) {
					$this->patchDuplicateRow($d, $this->patchDuplicateRow['pKey'], $this->patchDuplicateRow['columns']);
				} else {
					$this->rData[] = $d;
				}
			}
		}
		catch( Exception $Exception ) {
			$this->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2].chr(10).$queries['data'], $this->toSend);
		}

		if($csv) {
			return self::sendCsv($this->rData);
		}


		try {
			$q = $pdo->query($queries['recordsFiltered']);
		}
		catch( Exception $Exception ) {
			$this->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2].chr(10).$queries['recordsFiltered'], $this->toSend);
		}
		$recordsFiltered = $q->fetch()['count'];


		try {
			$q = $pdo->query($queries['recordsTotal']);
		}
		catch( Exception $Exception ) {
			$this->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2].chr(10).$queries['recordsTotal'], $this->toSend);
		}
		$recordsTotal = $q->fetch()['count'];

		$this->sendData($this->rData, $recordsFiltered, $recordsTotal);
	}

	function addToSend($k, $v) {
		$this->toSend[$k] = $v;
	}

	/**
	 * Send the json encoded result for DataTables.js
	 *
	 * @param array $data
	 * @param int   $recordsFiltered
	 * @param int   $recordsTotal
	 * @return json output
	 */
	protected function sendData($data, $recordsFiltered, $recordsTotal) {
		$toJson = array(
			'draw' => intval($this->request['draw']),
			'recordsTotal' => intval($recordsTotal),
			'recordsFiltered' => intval($recordsFiltered),
			'data' => $this->data_output($data) );
		if(isset($this->toSend)) {
			$toJson = array_merge($this->toSend, $toJson);
		}
		exit(json_encode($toJson));
	}

	static function sendCsv($data) {
		header("Content-Disposition: attachment; filename=" . uniqid('dataExpl').'.csv');
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Description: File Transfer");
		exit(self::arrayToCsv($data));
	}

	/**
	* Throw a fatal error.
	*
	* This writes out an error message in a JSON string which DataTables will
	* see and show to the user in the browser.
	*
	* @param string $error  Message to send to the client
	* @param array  $toSend Others informations to transfer
	*/
	static function sendFatal($error, $toSend = null) {
		$toJson = array( "error" => $error );
		if(isset($toSend)) {
			$toJson = array_merge($toSend, $toJson);
		}
		exit(json_encode( $toJson ));
	}

	static function arrayToCsv($array, $header_row = true, $col_sep = ",", $row_sep = "\n", $qut = '"')  {

		if (!is_array($array) or !isset($array[0]) or !is_array($array[0])) return false;
		$output = '';
		if ($header_row) {
			foreach ($array[0] as $key => $val) {
				$key = str_replace($qut, "$qut$qut", $key);
				$output .= "$col_sep$qut$key$qut";
			}
			$output = substr($output, 1)."\n";
		}
		foreach ($array as $key => $val) {
			$tmp = '';
			foreach ($val as $cell_key => $cell_val) {
				$cell_val = str_replace($qut, "$qut$qut", $cell_val);
				$tmp .= "$col_sep$qut$cell_val$qut";
			}
			$output .= substr($tmp, 1).$row_sep;
		}
		return $output;
	}
}
