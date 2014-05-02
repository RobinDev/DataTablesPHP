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

namespace rOpenDev\DataTablesPHP;

class DataTable {

	/**
	 * It seems there is no way to generate the tFoot like the thead
	 * To Be compatible with the plugin jquery.dataTables.columnFilter.js we need to generate a footer.
	 * Use the setFooter function.
	 */
	protected $footer = false;

	protected $jsInitParameters = array(), $jsonNotCompatible=array();

	protected $columns = array(), $unsetColumns=array();

	private static $instance = null;

	public static function instance($tableName = 'datatable') {
		 $cls = get_called_class(); //$intance_name = $tableName.$cls;
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

	function setColumn($jsParams, $name = null) {
		/**
		if($name!==null) {
			$this->columns[$name] = (object) $jsParams;
			$this->columns[$name]->mData = $name;
		} else {/**/
			if(isset($name))
				$jsParams['data'] = $name;
			$this->columns[] =(object) $jsParams;
		//}
		return $this;
	}

	function setColumns($columns) {
		foreach($columns as $name => $jsParams)
			$this->setColumn($jsParams, $name);
		return $this;
	}

	function setData($data) {
		$objectize = is_string(array_keys($data[0])[0]) ? true : false;
		for($i=0;$i<count($data);++$i)
			$data[$i] = $objectize ? (object) $data[$i] : $data[$i];
		$this->setJsInitParameter('data',  $data);
		return $this;
	}

	/**
	 *
	 * @param string|array $ajax
	 */
	function setServerSide($ajax, $onDataLoaded = null) {
		//$this->setJsInitParameter('bProcessing', true);
		$this->setJsInitParameter('serverSide', true);
		$this->setJsInitParameter('ajax', $ajax);
		if(isset($onDataLoaded)) {
			$this->setJsInitParameterNC('fnServerData',$onDataLoaded);
		}
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
			if(isset($c->searchable) && $c->searchable === false) {
				$params['aoColumns'][] = null;
				$this->keepAoParams = true;
			} elseif(isset($c->sFilter)) {
				$params['aoColumns'][] = $c->sFilter;
				$this->keepAoParams = true;
			} else {
				$params['aoColumns'][] = '';
			}
		}
		if(!isset($this->keepAoParams)) //empty(array_filter($params['aoColumns'])))
			unset($params['aoColumns']);
		return empty($params) ? '' : json_encode($params);
	}

	function getJavascript() {

		if(!empty($this->columns))
			$this->setJsInitParameter('columns', array_values($this->columns));

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

		if($this->footer === true) {
			$html .= '<tfoot>';
				$html .= '<tr>';
				foreach($this->columns as $k => $v)
					$html .= '<th></th>'; //'.(isset($v->sClass)?' class="'.$v->sClass.'"':'').'
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
	/*
	 * Concept de l'unset_column pour les colonnes non affichées
	 */

	function setTable($table) {
		$this->table = $table;
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

		for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {
			$row = array();

			for ( $j=0, $jen=count($this->columns) ; $j<$jen ; $j++ ) {
				$column = $this->columns[$j];

				// Is there a formatter?
				if ( isset( $column->formatter ) ) {
					$row[ $column->data ] = $column->formatter( $data[$i][ self::fromSQLColumn($column) ], $data[$i] );
				}
				else {
					$row[ $column->data ] = $data[$i][ self::fromSQLColumn($column) ];
				}
			}

			$out[] = $row;
		}

		return $out;
	}

	function sendData($data, $recordsFiltered, $recordsTotal) {
		$toJson = array(
			'draw' => intval($this->request['draw']),
			'recordsTotal' => intval($recordsTotal),
			'recordsFiltered' => intval($recordsFiltered),
			'data' => $this->data_output($data) );
		exit(json_encode($toJson));
	}

	function generateSQLRequest($dtRequest) {
		$this->request = $dtRequest;

		$limit = $this->limit();
		$order = $this->order();
		$where = $this->filter();

		foreach($this->columns as $c)
			$columns[] = self::toSQLColumn($c);

		foreach($this->unsetColumns as $c)
			$columns[] = self::toSQLColumn($c);

		return array(
			'data' 			  => 'SELECT SQL_CALC_FOUND_ROWS '.implode(',',$columns).' FROM '.$this->table.' '.$where.' '.$order.' '.$limit.';',
			'recordsFiltered' => 'SELECT FOUND_ROWS() count;',
			'recordsTotal'	  => 'SELECT COUNT(*) count FROM '.$this->table.';'
		);
	}

	/**
	* Paging
	*
	* Construct the LIMIT clause for server-side processing SQL query
	*
	* @return string SQL limit clause
	*/
	function limit() {
		if (isset($this->request['start']) && $this->request['length'] != -1) {
			return 'LIMIT '.intval($this->request['start']).','.intval($this->request['length']);
		}
	}

	/**
	* Ordering
	*
	* Construct the ORDER BY clause for server-side processing SQL query
	*
	* @return string SQL order by clause
	*/
	function order() {
		$order = '';
		//$columns = $this->getColumns();
		if (isset($this->request['order']) && count($this->request['order'])) {
			$orderBy = array();
			for ($i=0,$ien=count($this->request['order']);$i<$ien;$i++) {
				//$columnIdx = $columns['order'][$i]['key']; //$requestColumn = $request['columns'][$i];
				//$requestColumn = $this->columns[$key];

				//dbv($this->request['order']);
				$columnIdx = intval($this->request['order'][$i]['column']);
				$requestColumn = $this->request['columns'][$columnIdx];
				//$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $this->columns[$columnIdx];

				if (!isset($column->orderable) || $column->orderable === true) {
					$orderBy[] = self::toSQLColumn($column, true).' '.($this->request['order'][$i]['dir'] === 'asc' ? 'ASC' : 'DESC');
				}
			}
			$order = 'ORDER BY '.implode(', ', $orderBy);
		}
		return $order;
	}

	/**
	* Searching / Filtering
	*
	* Construct the WHERE clause for server-side processing SQL query.
	*/
	function filter () {
		$globalSearch = array();
		$columnSearch = array();

		// Global Search
		if ( isset($this->request['search']) && !empty($this->request['search']['value'])) {

			for ( $i=0, $ien=count($this->request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $this->request['columns'][$i];
				//$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $this->columns[$i];

				if (!isset($column['searchable']) || $column['searchable'] === true) {//$requestColumn['searchable'] == 'true' ) { // Don't trust user input !
					$globalSearch[] = self::toSQLColumn($column, true).' LIKE '.self::quote($this->request['search']['value'], '%');
				}
			}
		}

		// Individual column filtering
		if(isset($this->columnFilterParams)) {
			$sRangeSeparator = isset($this->columnFilterParams['sRangeSeparator']) ? $this->columnFilterParams['sRangeSeparator'] : '~'; // See jquery.dataTables.columnFilter.js doc
			for ( $i=0, $ien=count($this->columns) ; $i<$ien ; $i++ ) {
				$requestColumn = $this->request['columns'][$i];
				//$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $this->columns[$i];

				if ((!isset($column->searchable) || $column->searchable === true) && !empty($this->request['columns'][$i]['search']['value'])) {
					$search_value = trim($this->request['columns'][$i]['search']['value']);
					$key = self::toSQLColumn($column, true);

					if(preg_match("/^\[(<=|>=|=|<|>)\](.+)/i", $search_value, $matches)) {
						$columnSearch[] = $key.' '.$match[1].' \'%'.self::quote($matches[2]).'%\'';
					}
					elseif(preg_match('/(.*)'.$sRangeSeparator.'(.*)/i', $search_value, $matches)) {

						if(!empty($matches[1]) || !empty($matches[2])) {
							// TODO : use type to format the search value (eg STR_TO_DATE)
							$columnSearch[] = $key.' BETWEEN '.self::quote($matches[1]).' AND .'; //\'%'.addcslashes($matches[2], '%_\'').'%\'';
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

	static function toSQLColumn2($column, $onlyAlias = false) {
		return $onlyAlias && isset($column['alias']) ? $column['alias'] :
				'`'.(isset($column['sql_table']) ? $column['sql_table'] : $this->table).'`.'
			   .'`'.(isset($column['sql_name']) ? $column['sql_name'] : $column['data']).'`'
			   .(!$onlyAlias && isset($column['alias']) ? ' AS '.$column['alias'] : '');
	}

	function toSQLColumn($column, $onlyAlias = false) {
		return $onlyAlias && isset($column->alias) ? $column->alias :
				'`'.(isset($column->sql_table) ? $column->sql_table : $this->table).'`.'
			   .'`'.(isset($column->sql_name) ? $column->sql_name : $column->data).'`'
			   .(!$onlyAlias && isset($column->alias) ? ' AS '.$column->alias : '');
	}

	static function fromSQLColumn($column) {
		return isset($column->alias) ? $column->alias : (isset($column->sql_name) ? $column->sql_name : $column->data);
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
