<?php
namespace rOpenDev\DataTablesPHP;

use \Exception;

/**
 * PHP DataTablesPHP wrapper class for DataTables.js (Html, Javascript & Server Side). http://www.robin-d.fr/DataTablesPHP/
 * Server-side part inspired from [Allan Jardine's Class SSP](https://github.com/DataTables/DataTables/blob/master/examples/server_side/scripts/ssp.class.php)
 *
 * Compatible with DataTables 1.10.x
 *
 * @author     Original Author Robin <contact@robin-d.fr> http://www.robin-d.fr/
 * @link       http://www.robin-d.fr/DataTablesPHP/
 * @link       https://github.com/RobinDev/DataTablesPHP
 * @since      File available since Release 2014.05.01
 */
// formatter : A approfondir avec call_user_func_array
class DataTable
{

    /**
     * Contain the table's id
     * @var string
     */
    protected $tableName;

    /**
     * footer: Permet de dessiner le footer via html
     * header: Idem pour le thead
     * @var bool $footer
     * @var bool $header
     */
    protected $footer = false, $header = false;

    /**
     * Columns's number to print
     * @var int
     */
    protected $iThead = 0;

    /**
     * @var array $theadChild
     * @var array $thead
     */
    protected $theadChild = [], $thead = [];

    /**
     * Initialization's Parameters for dataTables.js
     * @var array $jsInitParameters
     * @var array $jsonNotCompatible
     */
    protected $jsInitParameters = [], $jsonNotCompatible=[];

    /**
     * Column to print and sql column to load
     * @var array $columns
     * @var array $unsetColumns
     */
    protected $columns = [], $unsetColumns=[];

    /**
     * PDO Link
     * @var object
     */
    protected $pdoLink;

    /**
     * Contain initial Filters
     * @var array
     */
    protected $filters = [];

    /**
     * If exactCount == true the filtered line counter will not use the precedent request but do a new.
     * Utile dans le cas de certains join.
     * @var bool
     */
     public $exactCount = false;

     /**
      * Var contain other information to send to the dataTable via Json Server-Side. Eg : sql queries for debug.
      * @var array
      */
     protected $toSend;

     /**
      * Active the visualization of operators when rendering fields
      * @var bool
      */
    public $renderFilterOperators = true;

    /**
     * Active Individual Column Filtering
     * @var bool
     */
    protected $individualColumnFiltering = false;


    private static $instance;

    /** SQL Part **/

    /**
     * @var string $table               .
     * @var string $aliasTable          .
     * @var array  $join                .
     * @var array  $patchDuplicateRow   .
     * @var string $groupBy             .
     * @var array  $request             .
     * @var array  $initColumnSearch    .
     * @var string $sRangeSeparator     .
     * @var array  $rData               .
     * @var array  $data                .
     */
     protected $table, $aliasTable, $join, $patchDuplicateRow, $groupBy, $request, $initColumnSearch, $sRangeSeparator, $rData, $data;

    /**
     * Correspond to the column options for DataTables javascript initialization (see the doc : DataTables > Refererences > Column)
     * @var array
     */
    protected static $columnParams = [
        'name',             // Did we really care about the name ?  It's for API
        'data',             // data source string|object ... object pour Sort !! http://datatables.net/examples/ajax/orthogonal-data.html http://datatables.net/reference/option/columns.data
                            // if data === null = pas de donnée à afficher
        'title',
        'defaultContent',
        'width',
        'visible',
        'type',             // numeric|date
        'searchable',
        'render',
        'orderable',
        'orderDataType',    // We don't care because the search is did by SQL not JS ?!
        'orderData',        // Pour trier une colonne en fonction d'autres colonnes
        'orderSequence',    //
        'createdCell',      // Callback pour personnaliser la cellule en fonction de la donnée
        'contentPadding',
        'className',        // Alias of class ????!!!!! => A vérifier
        'cellType'          // To set TH
    ];

    /**
     * Column options for search
     * @var array
     */
    protected static $columFilteringParams = [
        'type'  => array('text', 'number', 'select', 'date'),        // string text|number|number-range|select|date-range
        'regex' => true,    // bool
        'values'=>[],    // array For Select purpose
        //'operator'=>false,    // bool     For text purpose (to add an operator Select)
    ];

    /**
     * Get a table initialized if exist, else initialize one
     *
     * @param string $id Id wich permit to identify the table
     */
    public static function instance($id = 'datatable')
    {
        $cls = get_called_class();
        if (!isset(self::$instance[$id])) {
            self::$instance[$id] = new $cls($id);
        }
        return self::$instance[$id];
    }

    /**
     * Initialize a new table
     *
     * @param string $id Id wich permit to identify the table
     */
    public function __construct($id)
    {
        $this->tableName = $id;
    }

    /**
     * Add a dataTables.js Init Param
     *
     * @param str   $key   Contain param's key
     * @param mixed $value Contain param's value
     *
     * @return self
     */
    public function setJsInitParam($key, $value)
    {
        if (is_string($value) && strpos(str_replace(' ', '', $value), 'function(')===0) {
            $this->setJsInitParam($key, $id = md5($value));
            $this->jsonNotCompatible[0][] = '"'.$id.'"';
            $this->jsonNotCompatible[1][] = $value;
        }
        else {
            $this->jsInitParameters[$key] = $value;
        }
        return $this;
    }

    /**
     * Add dataTables.js Init Params
     *
     * @param array $params Contain dataTables.js Init Params
     *
     * @return self
     */
    public function setJsInitParams($params)
    {
        foreach($params as $k=>$v) {
            $this->setJsInitParam($k, $v);
        }
        return $this;
    }

    /**
     * Alias for setJsInitParam('dom', $dom)
     *
     * @param string $dom
     *
     * @return self
     */
    public function setDom($dom)
    {
        $this->setJsInitParam('sDom', $dom);
        return $this;
    }

    /**
     * Add a column to the table
     *
     * @param array $params Can contain :
     *                          $key        str        $v            - Properties for Initialization Javascript (see self::$columnParams)
     *                          'parent'    str        $title        - Permit to have a complex header with a colspan...
     *                                                               - Set the same $title to put multiple column under the same th
     *                          'sFilter'   array                    - For the column filtering options (see self::$columFilteringParams)
     *                          'sql_table' str                      - Column's sql table
     *                          'sql_name'  str                      - Column's sql name if different from data
     *                          'sqlFilter' str                      - Initial SQL filter
     *
     * @return self
     */
    public function setColumn($params)
    {
        $k = isset($params['parent'])?md5($params['parent'], true):++$this->iThead;
        if (isset($params['parent'])) {
            $this->theadChild[] = array('title'=>$params['title'], 'className'=>isset($params['className'])?$params['className']:null);
            $this->thead[$k]['colspan'] = !isset($this->thead[$k]['colspan'])?1:($this->thead[$k]['colspan']+1);
            $this->thead[$k]['title']   = $params['parent'];
            $this->header = true;
        } else {
            if (isset($params['title']))            $this->thead[$k]['title']   = $params['title'];
            if (isset($params['className']))        $this->thead[$k]['className'] = $params['className'];
        }

        if (isset($params['sFilter'])) {
            if (isset($params['sFilter']['type']) && in_array($params['sFilter']['type'], self::$columFilteringParams['type'])) {
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

    /**
     * Add columns to the table
     *
     * @param array $columns
     *
     * @return self
     */
    public function setColumns($columns)
    {
        foreach($columns as $c) {
            $this->setColumn($c);
        }
        return $this;
    }

    /**
     * Add a column to load in the result but wich will not be print
     *
     * @param array $column
     *
     * @return self
     */
    public function setUnsetColumn($column)
    {
        $this->unsetColumns[] = $column;
        return $this;
    }

    /**
     * Add columns to load in the result but wich will not be print
     *
     * @param array $columns
     *
     * @return self
     */
    public function setUnsetColumns($columns)
    {
        foreach($columns as $c) {
            $this->setUnsetColumn($c);
        }
        return $this;
    }

    /**
     * Initialize loading data via Ajax
     * /!/ Doesn't yet support Custom data get function !
     *
     * @param mixed $ajax
     *
     * @return self
     */
    public function setServerSide($ajax)
    {
        $this->setJsInitParam('serverSide', true);
        $this->setAjax($ajax);
        return $this;
    }

    /**
     * Alias for setJsInitParam('ajax', $ajax)
     * /!/ Doesn't yet support Custom data get function !
     *
     * @param mixed $ajax
     *
     * @return self
     */
    public function setAjax($ajax)
    {
        if (is_string($ajax)) {
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
     *
     * @param array $filters
     *
     * @return self
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
        /**
        foreach($filters as $k => $v) {
            $this->setInitFilter($this->getColumn($k), $v);
        }
        /**/
        return $this;
    }

    /**
     * Get a column
     *
     * @param string $k
     *
     * @return mixed Array if requested column exist... else null
     */
    public function getColumn($k)
    {
        foreach($this->columns as $c) {
            if ($c['data'] == $k) {
                return $c;
            }
        }
    }

    /**
     * Alias for setJsInitParam('data', $data);
     * Permit to set the data in the DataTables Javascript Initialization.
     *
     * @param array $data
     *
     * @return self
     */
    public function setData($data)
    {
        if (!isset($data[0])) {
            throw new \LogicException('Aucune donnée soumise');
        }
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
     *
     * @param bool $bool
     *
     * @return self
     */
    public function setHeader($bool)
    {
        $this->header = $bool;
        return $this;
    }

    /**
     * To generate Footer in html
     *
     * @param bool $bool
     *
     * @return self
     */
    public function setFooter($bool)
    {
        $this->footer = $bool;
        return $this;
    }

    /**
     * Is it configured to load data from server-side ?
     *
     * @return bool
     */
    public function isServerSide()
    {
        return isset($this->jsInitParameters['ajax']) ? true : false;
    }


    /**
     * Return columns for js with only JS parameters (remove the other parameters we use in PHP)
     *
     * @params mixed
     */
    protected function getColumnsForJs()
    {
        $rColumns = [];
        foreach($this->columns as $c) {
            $c = self::getColumnForJs($c);
            if ($c!==null) {
                $rColumns[] = $c;
            }
        }
        return !empty($rColumns)?$rColumns:null;
    }

    /**
     * Format column for javascript : only Keep the required options for columns and convert to object.
     *
     * @param array $column
     *
     * @return mixed
     */
    protected static function getColumnForJs($column)
    {
        $rColumn = [];
        foreach($column as $k=>$v) {
            if (in_array($k, self::$columnParams)) {
                $rColumn[$k] = $v;
            }
        }
        return !empty($rColumn) ? (object) $rColumn : null;
    }

    /**
     * Return javascript string to activate table
     *
     * @return string
     */
    public function getJavascript()
    {
        if (!empty($this->columns))
            $this->setJsInitParam('columns', $this->getColumnsForJs());

        $js = 'var '.$this->tableName.' = $("#'.$this->tableName.'").dataTable('."\n"
                .(!empty($this->jsInitParameters) ? json_encode((object) $this->jsInitParameters,  JSON_PRETTY_PRINT) : '')."\n"
            .')';

        /** We set the javascript function in the intial parameters **/
        if (isset($this->jsonNotCompatible[0]))
            $js = str_replace($this->jsonNotCompatible[0], $this->jsonNotCompatible[1], $js);

        $js .= ';'."\n";

        if ($this->individualColumnFiltering) {
            $js .= ''
            .'var '.$this->tableName.'Api = '.$this->tableName.'.api(true);'."\n"
            .'$("#'.$this->tableName.' tfoot th").each( function(colI) {'."\n"
                .'$(".sSearch", this).on("keyup change", function (e) {'."\n"
                .($this->isServerSide() ? '    if ( e.keyCode == 13 ) {'."\n" : '')
                .'        '.$this->tableName.'Api.column( colI ).search('
                .'            '.($this->renderFilterOperators ? '( this.tagName != "INPUT" ||  this.value == \'\' ? \'\' : $(this).prev().find("select").val()) + this.value ' : ' this.value ')
                .'        ).draw();'."\n"
                .($this->isServerSide() ? '    }'."\n" : '')
                .'});'."\n"
            .'});'."\n";
        }
        return $js;
    }

    /**
     * Return html table (min <table id=datatable></table>)
     *
     * @params array $tAttributes To add attributes to the <table> eg. : class=>table-green
     */
    public function getHtml($tAttributes = [])
    {
        $tAttributes['id'] = $this->tableName.(isset($tAttributes['id']) ? ' '.$tAttributes['id'] : '');
        $html = '<table'.self::mapAttributes($tAttributes).'>';

        if ($this->header === true) {
            $html .= '<thead>';
            $html .= '<tr>';
            foreach($this->thead as $v) {
                $html .= '<th'.(isset($v['className'])?' class="'.$v['className'].'"':'').' '.(isset($v['colspan'])?'colspan='.$v['colspan']:'rowspan=2').'>'
                            .(isset($v->title)?$v->title:(isset($v['title']) ? $v['title'] : ''))
                         .'</th>';
            }
            $html .= '</tr>';
            if (isset($this->theadChild)) {
                $html .= '<tr>';
                foreach($this->theadChild as $v) {
                    $html .= '<th'.(isset($v['className'])?' class="'.$v['className'].'"':'').'>'.(isset($v->title)?$v->title:'').'</th>';
                }
                $html .= '</tr>';
            }
            $html .= '</thead>';
        }

        if ($this->footer === true) {
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

    /**
     * Render option for select
     *
     * @param string $name
     * @param string $value
     * @param string $label
     */
    protected function formatOption($name, $value, $label)
    {
        return '<option value="' . $value .'"'
                    .(isset($this->filters[$name]) && strpos($this->filters[$name], $value) === 0 ? ' selected' : '')
                .'>'.(isset($label) ? $label : $value).'</option>';
    }

    /**
     * Return html form elements from the sFilter configuration in a column
     *
     * @param $column array
     *
     * @return string
     */
    protected function renderFilter($column)
    {
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
            case 'select' :
                $o='';
                if (isset($sFilter['values'])) {
                    foreach($sFilter['values'] as $k=>$v) {
                        $o .= '<option value="'.$k.'"'.(isset($column['data']) && isset($this->filters[$column['data']]) && $this->filters[$column['data']] == $k ? ' selected':'').'>'.$v.'</option>';
                    }
                }
                /** Auto-generate options if the data are loaded **/
                elseif (isset($this->jsInitParameters['data'])) {
                    // TODO
                }
                return '<select class="form-control sSearch"><option value=""></option>'.$o.'</select>';
        }
    }

    /**
     * html render function
     *
     * @param array $attributes
     *
     * @return string
     */
    protected static function mapAttributes($attributes) {
        return ' '.join(' ', array_map(
            function($sKey) use ($attributes) {
                return is_bool($attributes[$sKey]) ? ($attributes[$sKey]?$sKey:'') : $sKey.'="'.$attributes[$sKey].'"';
            }, array_keys($attributes)
        ));
    }




    /*** ########## Server Side ########## ***/


    /**
     * Add the pdo link
     *
     * @param object $link
     *
     * @return self
     */
    public function setPdoLink($link)
    {
        $this->pdoLink = $link;
        return $this;
    }

    /**
     * Set the mysql table to request
     *
     * @param string $table
     * @param string $alias
     *
     * @return self
     */
    public function setFrom($table, $alias = null)
    {
        $this->table = $table;
        $this->aliasTable = $alias === null ? $table : $alias;
        return $this;
    }

    /**
     * SQL rendering for JOIN ON
     *
     * @param array  $on
     * @param string $r
     *
     * @return string
     */
    protected static function formatOn($on, $r = '')
    {
        if (isset($on[0]) && is_array($on[0])) {
            foreach($on as $on2) {
                $r = self::formatOn($on2, $r);
            }
        } else {
            $on2 = array_keys($on);
            $r .= (!empty($r) ? ' AND ' : '').'`'.key($on).'`.`'.current($on).'` = `'.next($on2).'`.`'.next($on).'`';
        }
        return $r;
    }

    /**
     * Join data from other tables
     *
     * @param string $table     .
     * @param array  $on        Must contains two elements like key:sql_table => value:sql_column
     * @param string $join      .
     * @param array  $duplicate 0    Contain a column id... Generally the same that on ON
     *                          1     Contain column or columns
     *
     * @return self
     */
    public function setJoin($table, $on, $join = 'LEFT JOIN', $duplicate = false)
    {
        $this->join[] = $join.' '.$table.' ON '.self::formatOn($on);
        //$this->join[] = $join.' `'.$table.'` ON `'.$table.'`.`'.$on[$table].'` = `'.$this->table.'`.`'.$on[$this->table].'`';
        if ($duplicate !== false) {
            $this->patchDuplicateRow['pKey'] = $duplicate[0];
            if (is_array($duplicate[1])) {
                $this->patchDuplicateRow['columns'] = isset($this->patchDuplicateRow['columns']) ? array_merge($this->patchDuplicateRow['columns'], $duplicate[1]) : $duplicate[1];
            } else {
                $this->patchDuplicateRow['columns'][] = $duplicate[1];
            }
        }
        return $this;
    }

    /**
     * Group by a SQL column
     *
     * @param string $column
     *
     * @return self
     */
    public function setGroupBy($column) {
        $this->groupBy = (isset($this->groupBy) ? $this->groupBy.',':'').$column;
        return $this;
    }

    /**
    * Create the data output array for the DataTables rows
    *
    * @param array $data Data from the SQL get
    *
    * @return array Formatted data in a row based format
    */
    protected function data_output($data)
    {
        $out = [];
        $data=array_values($data); // Reset keys
        for ( $i=0, $ien=count($data) ; $i<$ien ; ++$i ) {
            $row = [];

            for ( $j=0, $jen=count($this->columns) ; $j<$jen ; ++$j ) {
                $column = $this->columns[$j];

                if ( isset( $column['formatter'] ) ) {
                    if (isset($column['data'])||isset($column['sql_name']))
                        $row[ isset($column['data'])?$column['data']:$j ] = $column['formatter']( $data[$i][ self::fromSQLColumn($column) ], $data[$i], $column );
                    else
                        $row[ $j ] = $column['formatter']( $data[$i] );
                }
                else {
                    // Compatibility with the json .
                    // if preg_match('#\.#', $column['data']) explode ('.', $colum['data'])...
                    if (isset($column['data'])||isset($column['sql_name']))
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
     *
     * @return array SQL queries to execute (keys: data, recordsFiltered, recordsTotal)
     */
    protected function generateSQLRequest($dtRequest)
    {
        $this->request = $dtRequest;

        $limit = $this->limit();
        $order = $this->order();
        $where = self::where($this->filters());
        $iWhere = self::where($this->initFilters());
        $join = $this->join();
        $from = $this->from();
        $groupBy = $this->groupBy();

        $columns = [];
        foreach($this->columns as $c) {
            if (isset($c['data'])||isset($c['sql_name'])) {
                $columns[] = $this->toSQLColumn($c);
            }
        }
        foreach($this->unsetColumns as $c) {
            $columns[] = $this->toSQLColumn($c);
        }
        $select = ($this->exactCount === false ? 'SQL_CALC_FOUND_ROWS ':'').implode(',',$columns);

        return [
            'data'             => 'SELECT '.$select.$from.$join.$where.$groupBy.$order.$limit.';',
            'recordsFiltered'  => $this->exactCount === false ? 'SELECT FOUND_ROWS() count;' : 'SELECT COUNT(1) as count '.$from.$join.$where.$groupBy,
            'recordsTotal'     => 'SELECT COUNT(*) count FROM '.$from.$join.$iWhere.$groupBy.';',
        ];
    }

    /**
     * SQL rendering of the GroupBy part
     *
     * @return string
     */
    function groupBy() {
        return (isset($this->groupBy) ? ' GROUP BY '.$this->groupBy : '');
    }


    /**
     * SQL rendering of the FROM part
     *
     * @return string
     */
    function from() {
        return ' FROM '.$this->table.($this->table != $this->aliasTable ? ' '.$this->aliasTable : '');
    }

    /**
     * SQL rendering of the JOIN part
     *
     * @return string
     */
    function join() {
        return isset($this->join) ? ' '.implode(' ', $this->join) : '';
    }

    /**
     * Paging : Construct the LIMIT clause for server-side processing SQL query
     *
     * @return string SQL limit clause
     */
    protected function limit()
    {
        if (isset($this->request['start']) && $this->request['length'] != -1) {
            return ' LIMIT '.intval($this->request['start']).','.intval($this->request['length']);
        }
    }

    /**
     * Ordering : Construct the ORDER BY clause for server-side processing SQL query
     *
     * @return string SQL order by clause
     */
    protected function order()
    {
        $order = '';
        if ((!isset($this->request['draw']) || (isset($this->request['draw']) && $this->request['draw'] != 1)) && isset($this->request['order']) && count($this->request['order'])) {
            $orderBy = [];
            for ($i=0,$ien=count($this->request['order']);$i<$ien;$i++) {
                $columnIdx = intval($this->request['order'][$i]['column']);
                $column = $this->columns[$columnIdx];
                if (!isset($column['orderable']) || $column['orderable'] === true) {
                    $orderBy[] = $this->toSQLColumn($column, 2).' '.($this->request['order'][$i]['dir'] === 'asc' ? 'ASC' : 'DESC');
                }
            }
            $order = ' ORDER BY '.implode(', ', $orderBy);
        }
        return $order;
    }

    /**
     * @param array  $column
     * @param string $filter
     */
    public function setInitFilter($column, $filter)
    {
        $this->initColumnSearch = (!empty($this->initColumnSearch) ? ' AND' : ' ').$this->generateSQLColumnFilter($this->toSQLColumn($column, 2, true), $filter);
    }

    /**
     * SQL Rendering for where
     *
     * @param string $filters
     *
     * @return string
     */
    protected static function where($filters)
    {
        return !empty($filters) ? ' WHERE '.$filters : '';
    }

    /**
     * Searching/Filtering : Construct the WHERE clause for server-side processing SQL query.
     *
     * @return string
     */
    protected function filters()
    {
        $where = $this->initFilters();
        $where .= ($where === '' ? '' : ' AND ').'('.$this->globalFilter().')';
        $where .= ($where === '' ?  '' : ' AND ').$this->individualColumnFilters();
        return $where;
    }

    /**
     * SQL Rendering for the  initial filters set
     *
     * @return string SQL Part Query
     */
    protected function initFilters()
    {
        $initColumnSearch = isset($this->initColumnSearch) ? $this->initColumnSearch : '';
        foreach($this->columns as $c) {
            if (isset($c['sqlFilter'])) {
                $initColumnSearch .= (!empty($initColumnSearch)?' AND':' ').$this->generateSQLColumnFilter($this->toSQLColumn($c, 2), $c['sqlFilter']);
            }
        }
        return $initColumnSearch;
    }

     /**
     * SQL Rendering for the global search
     *
     * @return string SQL Part Query
     */
    function globalFilter()
    {
        $globalSearch = '';
        if (isset($this->request['search']) && !empty($this->request['search']['value'])) {
            for ($i=0, $ien=count($this->request['columns']) ; $i<$ien ; $i++) {
                if (self::isSearchable($this->columns[$i])) {
                    $globalSearch .= (!empty($globalSearch) ? ' OR': ' ').$this->toSQLColumn($column, 2).' LIKE '.$this->pdoLink->quote('%'.$this->request['search']['value'].'%');
                }
            }
        }
        return $globalSearch;
    }

    /**
     * SQL Rendering for the invidividual column filters
     *
     * @return string
     */
    protected function individualColumnFilters()
    {
        $columnSearch = '';
        if (isset($this->individualColumnFiltering)) {
            $this->sRangeSeparator = isset($this->sRangeSeparator) ? $this->sRangeSeparator : '~';
            for ( $i=0, $ien=count($this->columns) ; $i<$ien ; $i++ ) {
                if ((!isset($column['searchable']) || $column['searchable'] === true) && !empty($this->request['columns'][$i]['search']['value'])) {
                    $search_value = trim($this->request['columns'][$i]['search']['value']);
                    $key = $this->toSQLColumn($column, 2, true);
                    $columnSearch .= (!empty($columnSearch)?' AND':' ').$this->generateSQLColumnFilter($key, $search_value);
                }
            }
        }
        return $columnSearch;
    }

    /**
     * The $column is seachable ?
     *
     * @param array $column
     *
     * @return bool
     */
    protected static function isSearchable($column) {
        return !isset($column['searchable']) || $column['searchable'] === true ? true : false;
    }

    /**
     * Generate the filter for a column
     *
     * @param string $column
     * @param string $search_value
     * @param object $pdoLink
     * @param string $sRangeSeparator
     *
     * @return string
     */
    static public function _generateSQLColumnFilter($column, $search_value, $pdoLink, $sRangeSeparator = '~')
    {
        if (preg_match("/^\[(=|!=)\]$/i", $search_value, $match)) {
            return $column.' '.$match[1].' '.$pdoLink->quote('');
        }

        if (preg_match("/^\[(!|<=|>=|=|<|>|<>|!=)\](.+)/i", $search_value, $match)) {

            if ($match[1] == '!=' && $match[2] == 'null') {
                return $column.' IS NOT NULL';
            }
            elseif ($match[1] == '=' && $match[2] == 'null') {
                return $column.' IS NULL';
            }
            elseif ($match[1] == '!') {
                return $column.' NOT LIKE '.$pdoLink->quote('%'. $match[2] .'%');
            }
            else {
                return $column.' '.$match[1].' '.$pdoLink->quote($match[2] == 'empty' ? '' : $match[2]);
            }
        }

        elseif (strpos($search_value, 'reg:') === 0) { //&& $column['sFilter']['regex'] === true) {
            return $column.' REGEXP '.$pdoLink->quote(substr($search_value, strlen('Reg:')));
        }

        elseif (preg_match('/(.*)(!?'.$sRangeSeparator.')(.*)/i', $search_value, $matches) && !empty($matches[1]) && !empty($matches[3])) {
                // TODO : use type to format the search value (eg STR_TO_DATE)
                return $column.($matches[2][0]=='!'?' NOT':'')
                    .' BETWEEN '.$pdoLink->quote($matches[1]).' AND '.$pdoLink->quote($matches[3]);

        }

        elseif (strpos($search_value, 'in:')===0) {
            $search_value = substr($search_value, strlen('in:'));
            return $column .' IN('.$search_value.')';
        }
        elseif (strpos($search_value, '!in:')===0) {
            $search_value = substr($search_value, strlen('in:'));
            return $column .' NOT IN('.$search_value.')';
        }

        else {
            return $column.' LIKE '.$pdoLink->quote('%'.$search_value.'%');
        }
    }

    /**
     * Generate the filter for a column
     *
     * @param array  $column
     * @param string $search_value
     *
     * @return string
     */
    protected function generateSQLColumnFilter($column, $search_value)
    {
        return self::_generateSQLColumnFilter($column, $search_value, $this->pdoLink, isset($this->sRangeSeparator) ? $this->sRangeSeparator : '~');
    }

    /**
     * Give a column's sql name
     *
     * @param array $column
     * @param int   $onlyAlias   0: return sql_table.sql_name AS alias
     *                           1: return alias
     *                           2: return sql_table.sql_name
     * @param mixed $filter
     *
     * @return string
     */
    protected function toSQLColumn($column, $onlyAlias = 0, $filter = false)
    {
        if (!isset($column['sql_name']) && !isset($column['data']))
            self::sendFatal('Houston, we have a problem with one of your column : can\'t draw it SQL name because it don\'t have data or sql_name define.'."\n".json_encode($column));

        if ($filter && isset($column['sFilter']) && isset($column['sFilter']['sql_name'])) {
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
     *
     * @param array $column
     *
     * @return string
     */
    protected static function fromSQLColumn($column)
    {
        return isset($column['alias']) ? $column['alias'] : (isset($column['sql_name']) ? $column['sql_name'] : $column['data']);
    }

    /**
     * To avoid duplicate row when LEFT JOIN if GROUP BY is not set
     *
     * @param array $d
     * @param string $pKey
     * @param array $columns
     */
    protected function patchDuplicateRow($d, $pKey, $columns)
    {
        $id=$d[$pKey];
        if (isset($this->rData[$id])) {
            foreach($columns as $c => $separator) {
                $this->rData[$id][$c] .= $separator.$d[$id];
            }
        } else {
            $this->rData[$id] = $d[$id];
        }
    }

    /**
     * Execute the ajax request and send the result in json
     *
     * @param array $request ($_GET, $_POST...)
     * @param bool  $csv
     *
     * @return string Exit with the json result for Datatables.js
     */
    public function exec($request, $csv = false)
    {
        if ($csv) {
            $this->request['length'] = -1;
        }
        $queries = $this->generateSQLRequest($request);

        $this->addToSend('sqlQuery', $queries['data']);

        //dbv($queries['data']);

        $pdo = $this->pdoLink;

        try {
            $q = $pdo->query($queries['data']);
            $this->rData=[];
            // PATCH : Annoying JOIN wich duplicate row if there is different tag
            while($d=$q->fetch()) {
                if (isset($this->patchDuplicateRow)) {
                    $this->patchDuplicateRow($d, $this->patchDuplicateRow['pKey'], $this->patchDuplicateRow['columns']);
                } else {
                    $this->rData[] = $d;
                }
            }
        }
        catch( Exception $Exception ) {
            $this->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2].chr(10).$queries['data'], $this->toSend);
        }

        if ($csv) {
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

    /**
     *
     * @param string $k
     * @param string $v
     */
    public function addToSend($k, $v)
    {
        $this->toSend[$k] = $v;
    }

    /**
     * Send the json encoded result for DataTables.js
     *
     * @param array $data
     * @param int   $recordsFiltered
     * @param int   $recordsTotal
     *
     * @return json output
     */
    protected function sendData($data, $recordsFiltered, $recordsTotal)
    {
        $toJson = array(
            'draw' => intval($this->request['draw']),
            'recordsTotal' => intval($recordsTotal),
            'recordsFiltered' => intval($recordsFiltered),
            'data' => $this->data_output($data) );
        if (isset($this->toSend)) {
            $toJson = array_merge($this->toSend, $toJson);
        }
        exit(json_encode($toJson));
    }

    static public function sendCsv($data)
    {
        header("Content-Disposition: attachment; filename=" . uniqid('dataExpl').'.csv');
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Description: File Transfer");
        exit(self::arrayToCsv($data));
    }

    /**
    * Throw a fatal error.
    * This writes out an error message in a JSON string which DataTables will
    * see and show to the user in the browser.
    *
    * @param string $error  Message to send to the client
    * @param array  $toSend Others informations to transfer
    */
    static public function sendFatal($error, $toSend = null)
    {
        $toJson = array( "error" => $error );
        if (isset($toSend)) {
            $toJson = array_merge($toSend, $toJson);
        }
        exit(json_encode( $toJson ));
    }

    /**
     * Convert an array in a string CSV
     *
     * @param array  $array
     * @param bool   $header_row
     * @param string $col_sep
     * @param string $row_sep
     * @param string $qut
     *
     * @return string
     */
    static public function arrayToCsv($array, $header_row = true, $col_sep = ",", $row_sep = "\n", $qut = '"')
    {
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
