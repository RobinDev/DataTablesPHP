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

	protected $footer = false, $header = false, $iThead = 0;

	protected $jsInitParameters = array(), $jsonNotCompatible=array();

	protected $columns = array(), $unsetColumns=array();

	private static $instance = null;

	/**
	 * Correspond to the column options for DataTables javascript initialization (see the doc : DataTables > Refererences > Column)
	 * /!/ Tmp //Comment => Will be deleted
	 */
	protected static $columnParams = array(
	    'name',			// Did we really care about the name ?  It's fort API
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
	 * Column options for the jquery.dataTables.columnfilter.js
	 * /!/ Useless for the code because the class don't check and remove bad parameters... Only for remind so.
	 */
	static protected $columFilteringParams = array(
		'type',		// string text|number|number-range|select|date-range
		'bRegex',	// bool
		'bSmart',	// bool
		'values'	// array For Select purpose
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

    function setJsInitParameter($key, $value) {
		$this->jsInitParameters[$key] = $value;
		return $this;
	}

	/**
	 * Set a Javascript function like value
	 */
	function setJsInitParameterNC($key, $value) {
		$this->setJsInitParameter($key, $id = md5($value) );
		$this->jsonNotCompatible[0][] = '"'.$id.'"';
		$this->jsonNotCompatible[1][] = $value;
		return $this;
	}

    function setJsInitParameters($params) {
		$this->jsInitParameters = array_merge($params, $this->jsInitParameters);
		return $this;
	}

	/**
	 * Alias for setJsInitParameter('dom', $dom)
	 */
	function setDom($dom) {
		$this->setJsInitParameter('sDom', $dom);
		return $this;
	}

	/**
	 * Add a column to the table
	 *
	 * @param array $params Can contain :
	 *                      * Properties for Initialization Javascript (see self::$columnParams)
	 *                      * key:parent => string $title Permit to have a complex header with a colspan... Set the same $title to put
	 * 						  multiple column under the same th
	 * 						* sFilter arrray : for the column filtering options (see self::$columFilteringParams)
	 *						* SQL params : TO CONSTRUCT !!
	 */
	function setColumn($params) {
		$this->columns[] = $params;

		$k = isset($params['parent'])?md5($params['parent'], true):++$this->iThead;
		if(isset($params['parent'])) {
			$this->theadChild[] = array('title'=>$params['title'], 'className'=>isset($params['className'])?$params['className']:null);
			$this->thead[$k]['colspan'] = !isset($this->thead[$k]['colspan'])?1:($this->thead[$k]['colspan']+1);
			$this->thead[$k]['title']   = $params['parent'];
			$this->header = true;
		} else
			if(isset($params['title']))		$this->thead[$k]['title']   = $params['title'];
		if(isset($params['className']))		$this->thead[$k]['className'] = $params['className'];

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
	 * Alias for setJsInitParameter('data', $data);
	 * Permit to set the data in the DataTables Javascript Initialization.
	 *
	 * @param array $data
	 */
	function setData($data) {
		$k = array_keys($data[0]); // We check the first key  from the first line
		$objectize = is_string($k[0]) ? true : false;
		for($i=0;$i<count($data);++$i)
			$data[$i] = $objectize ? (object) $data[$i] : $data[$i];
		$this->setJsInitParameter('data',  $data);
		return $this;
	}

	/**
	 * /!/ Doesn't yet support Custom data get function !
	 *
	 * @param string|array $ajax
	 */
	function setServerSide($ajax) {
		$this->setJsInitParameter('serverSide', true);
		$this->setAjax($ajax);
		return $this;
	}

	/**
	 * Alias for setJsInitParameter('ajax', $ajax)
	 * /!/ Doesn't yet support Custom data get function !
	 *
	 * @param string|array $ajax
	 */
	function setAjax($ajax) {
		$this->setJsInitParameter('ajax', is_array($ajax)||is_object($ajax)?(object)$ajax:(string)$ajax);
		return $this;
	}

	function setFooter($bool) {
		$this->footer = $bool;
		return $this;
	}

	function setColumnFilterActive($params = array()) {
		$this->columnFilterParams = $params;
		$this->footer = true;
		return $this;
	}

	function getColumFilterParams() {
		$params = $this->columnFilterParams;
		$params['aoColumns'] = array();
		foreach($this->columns as $c) {
			if(isset($c['searchable']) && $c['searchable'] === false) {
				$params['aoColumns'][] = null;
				$this->keepAoParams = true;
			} elseif(isset($c['sFilter'])) {
				$params['aoColumns'][] = $c['sFilter'];
				$this->keepAoParams = true;
			} else {
				$params['aoColumns'][] = '';
			}
		}
		if(!isset($this->keepAoParams)) //empty(array_filter($params['aoColumns'])))
			unset($params['aoColumns']);
		return empty($params) ? '' : json_encode($params);
	}


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

	function getJavascript() {

		if(!empty($this->columns))
			$this->setJsInitParameter('columns', $this->getColumnsForJs());

		$js = 'var '.$this->tableName.' = $("#'.$this->tableName.'").dataTable('.(!empty($this->jsInitParameters) ? json_encode((object) $this->jsInitParameters) : '').')';

		if(isset($this->columnFilterParams)) {
			$js .= '.columnFilter('.$this->getColumFilterParams().')';
		}

		$js .= ';';

		if(isset($this->jsonNotCompatible[0]))
			$js = str_replace($this->jsonNotCompatible[0], $this->jsonNotCompatible[1], $js);

		return $js;
	}

	function getHtml($tAttributes = array()) {

		$html = '<table id="'.$this->tableName.'" '.self::mapAttributes($tAttributes).'>';

		if($this->header === true) {
			$html .= '<thead>';
			$html .= '<tr>';
			foreach($this->thead as $v) {
				$html .= '<th'.(isset($v['className'])?' class="'.$v['className'].'"':'').' '.(isset($v['colspan'])?'colspan='.$v['colspan']:'rowspan=2').'>'
							.(isset($v->title)?$v->title:'')
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
				foreach($this->columns as $k => $v)
					$html .= '<th>'.(isset($v['title'])?$v['title']:'').'</th>'; //'.(isset($v->sClass)?' class="'.$v->sClass.'"':'').'
				$html .= '</tr>';
			$html .= '</tfoot>';
		}

		$html .= '</table>';

		return $html;
	}

	static function mapAttributes($attributes) {
		return join(' ', array_map(
		function($sKey) use ($attributes) {
			return is_bool($attributes[$sKey]) ? ($attributes[$sKey]?$sKey:'') : $sKey.'="'.$attributes[$sKey].'"';
		}, array_keys($attributes)));
	}


	/*** Server Side ***/

	function setFrom($table) {
		$this->table = $table;
		return $this;
	}

	/**
	 * Join data from other tables
	 *
	 * @param string $table  .
	 * @param array  $on     Must contains two elements like key:sql_table => value:sql_column
	 * @param string $join   .
	 */
	function setJoin($table, $on, $join = 'LEFT JOIN') {
		$on2 = array_keys($on);
		$this->join[] = $join.' `'.$table.'` ON `'.key($on).'`.`'.current($on).'` = `'.next($on2).'`.`'.next($on).'`';
		//$this->join[] = $join.' `'.$table.'` ON `'.$table.'`.`'.$on[$table].'` = `'.$this->table.'`.`'.$on[$this->table].'`';
		return $this;
	}

	/**
	* Create the data output array for the DataTables rows
	*
	* @param array $data Data from the SQL get
	* @return array Formatted data in a row based format
	*/
	function data_output($data) {
		$out = array();

		for ( $i=0, $ien=count($data) ; $i<$ien ; ++$i ) {
			$row = array();

			for ( $j=0, $jen=count($this->columns) ; $j<$jen ; ++$j ) {
				$column = $this->columns[$j];

				if ( isset( $column['formatter'] ) ) {
					if(isset($column['data'])||isset($column['sql_name']))
						$row[ isset($column['data'])?$column['data']:$j ] = $column['formatter']( $data[$i][ self::fromSQLColumn($column) ], $data[$i] );
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
	 * Send the json encoded result for DataTables.js
	 *
	 * @param array $data
	 * @param int   $recordsFiltered
	 * @param int   $recordsTotal
	 * @return json output
	 */
	function sendData($data, $recordsFiltered, $recordsTotal) {
		$toJson = array(
			'draw' => intval($this->request['draw']),
			'recordsTotal' => intval($recordsTotal),
			'recordsFiltered' => intval($recordsFiltered),
			'data' => $this->data_output($data) );
		exit(json_encode($toJson));
	}

	/**
	 * Generate the SQL queries (optimized for MariaDB/MySQL) to execute.
	 *
	 * @param  array $dtRequest Request send by DataTables.js ($_GET or $_POST)
	 * @return array SQL queries to execute (keys: data, recordsFiltered, recordsTotal)
	 */
	function generateSQLRequest($dtRequest) {
		$this->request = $dtRequest;

		$limit = $this->limit();
		$order = $this->order();
		$where = $this->filter();

		foreach($this->columns as $c) {
			if(isset($c['data'])||isset($c['sql_name']))
			$columns[] = self::toSQLColumn($c);
		}

		foreach($this->unsetColumns as $c)
			$columns[] = self::toSQLColumn($c);

		$join = isset($this->join) ? ' '.implode(' ', $this->join) : '';

		return array(
			'data' 			  => 'SELECT SQL_CALC_FOUND_ROWS '.implode(',',$columns).' FROM '.$this->table.$join.' '.$where.' '.$order.' '.$limit.';',
			'recordsFiltered' => 'SELECT FOUND_ROWS() count;',
			'recordsTotal'	  => 'SELECT COUNT(*) count FROM '.$this->table.';'
		);
	}

	/**
	 * Paging : Construct the LIMIT clause for server-side processing SQL query
	 *
	 * @return string SQL limit clause
	 */
	function limit() {
		if (isset($this->request['start']) && $this->request['length'] != -1) {
			return 'LIMIT '.intval($this->request['start']).','.intval($this->request['length']);
		}
	}

	/**
	 * Ordering : Construct the ORDER BY clause for server-side processing SQL query
	 *
	 * @return string SQL order by clause
	 */
	function order() {
		$order = '';
		if (isset($this->request['order']) && count($this->request['order'])) {
			$orderBy = array();
			for ($i=0,$ien=count($this->request['order']);$i<$ien;$i++) {
				$columnIdx = intval($this->request['order'][$i]['column']);
				$column = $this->columns[$columnIdx];
				if (!isset($column['orderable']) || $column['orderable'] === true) {
					$orderBy[] = self::toSQLColumn($column, true).' '.($this->request['order'][$i]['dir'] === 'asc' ? 'ASC' : 'DESC');
				}
			}
			$order = 'ORDER BY '.implode(', ', $orderBy);
		}
		return $order;
	}

	/**
	* Searching/Filtering : Construct the WHERE clause for server-side processing SQL query.
	*/
	function filter() {
		$globalSearch = array();
		$columnSearch = array();

		// Global Search
		if ( isset($this->request['search']) && !empty($this->request['search']['value'])) {

			for ( $i=0, $ien=count($this->request['columns']) ; $i<$ien ; $i++ ) {
				$column = $this->columns[$i];
				if (!isset($column['searchable']) || $column['searchable'] === true) {
					$globalSearch[] = self::toSQLColumn($column, true).' LIKE '.self::quote($this->request['search']['value'], '%');
				}
			}
		}

		// Individual column filtering
		if(isset($this->columnFilterParams)) {
			$sRangeSeparator = isset($this->columnFilterParams['sRangeSeparator']) ? $this->columnFilterParams['sRangeSeparator'] : '~'; // See jquery.dataTables.columnFilter.js doc
			for ( $i=0, $ien=count($this->columns) ; $i<$ien ; $i++ ) {
				$column = $this->columns[$i];
				if ((!isset($column['searchable']) || $column['searchable'] === true) && !empty($this->request['columns'][$i]['search']['value'])) {
					$search_value = trim($this->request['columns'][$i]['search']['value']);
					$key = self::toSQLColumn($column, true);

					if(preg_match("/^\[(<=|>=|=|<|>|<>|!=)\](.+)/i", $search_value, $match)) {
						$columnSearch[] = $key.' '.$match[1].' '.self::quote($match[2]);
					}
					elseif(preg_match('/(.*)(!?'.$sRangeSeparator.')(.*)/i', $search_value, $matches)) {

						if(!empty($matches[1]) || !empty($matches[3])) {
							// TODO : use type to format the search value (eg STR_TO_DATE)
							$columnSearch[] = $key.($matches[2][0]=='!'?' NOT':'')
								.' BETWEEN '.self::quote($matches[1]).' AND '.self::quote($matches[3]); //\'%'.addcslashes($matches[2], '%_\'').'%\'';
						}

					}
					else {
						$columnSearch[] = $key.' LIKE '.self::quote($search_value, '%');
					}
				}
			}
		}

		// Combine the filters into a single string
		$where = '';

		if ( count( $globalSearch ) ) {
			$where = '('.implode(' OR ', $globalSearch).')';
		}

		if ( count( $columnSearch ) ) {
			$where = $where === '' ? implode(' AND ', $columnSearch) : $where .' AND '. implode(' AND ', $columnSearch);
		}

		return $where = $where !== '' ? 'WHERE '.$where : '';
	}

	/**
	 * Quote and Protect string for sql query
	 *
	 * @param string $str String to quote
	 * @param string $encapsuler Encapsulate the string without being protected (for Like % eg)
	 */
    static function quote($str, $encapsuler = '') {
		if(empty($encapsuler) && (is_int($str) || ctype_digit($str)))
			return $str;
		return '\''.$encapsuler.addcslashes($str, '%_\'').$encapsuler.'\'';
	}

	function toSQLColumn($column, $onlyAlias = false) {
		if(!isset($column['sql_name']) && !isset($column['data']))
			self::sendFatal('Houston, we have a problem with one of your column : can\'t draw it SQL name because it don\'t have data or sql_name define.');
		return $onlyAlias && isset($column['alias']) ? $column['alias'] :
				'`'.(isset($column['sql_table']) ? $column['sql_table'] : $this->table).'`.'
			   .'`'.(isset($column['sql_name']) ? $column['sql_name'] : $column['data']).'`'
			   .(!$onlyAlias && isset($column['alias']) ? ' AS '.$column['alias'] : '');
	}

	static function fromSQLColumn($column) {
		return isset($column['alias']) ? $column['alias'] : (isset($column['sql_name']) ? $column['sql_name'] : $column['data']);
	}

	/**
	* Throw a fatal error.
	*
	* This writes out an error message in a JSON string which DataTables will
	* see and show to the user in the browser.
	*
	* @param string $error Message to send to the client
	*/
	static function sendFatal($error) {
		exit(json_encode( array( "error" => $error ) ));
	}

}
