<?php
namespace rOpenDev\DataTablesPHP;

use rOpenDev\PHPToJS\PHPToJS;
use Exception;
use PDO;

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
class DataTable
{
    /** @var string Contain the table's id */
    protected $tableName;

    /** @var bool Draw the footer in html **/
    protected $footer = false;
    /** @var bool Draw the header in html **/
    protected $header = false;

    /** @var int Columns's number to print */
    protected $iThead = 0;

    /** @var array */
    protected $theadChild = [];
    /** @var array */
    protected $thead = [];

    /** @var array Initialization's Parameters for dataTables.js*/
    protected $jsInitParameters = [];

    /** @var array Columns wich will be printed */
    protected $columns = [];
    /** @var array Columns wich need to be request via SQL but not print **/
    protected $unsetColumns = [];

    /** @var object PDO Link */
    protected $pdoLink;

    /** @var array Contain initial Filters */
    protected $filters = [];

    /** @var bool Active counters */
    public $counters = true;

    /** @var array Var contain other information to send to the dataTable via Json Server-Side. Eg : sql queries for debug. */
    protected $toSend;

    /** @var bool Active the visualization of operators when rendering fields (for MySQL) */
    public $renderFilterOperators = true;

    /**
     * Active Individual Column Filtering
     * @var bool
     */
    protected $individualColumnFiltering = false;

    /** @var array */
    private static $instance = [];

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
     * @var string $having              .
     */
    protected $table, $aliasTable, $join, $patchDuplicateRow, $groupBy, $request, $initColumnSearch = [], $sRangeSeparator, $rData, $data, $having = '';

    /** @var \Symfony\Component\Translation\TranslatorInterface */
    protected $translator;

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
        'cellType',          // To set TH
    ];

    /** @var array Column options for search */
    protected static $columFilteringParams = [
        'type'  => ['text', 'number', 'select', 'date'],
        'regex' => true,
        'values' => [],
    ];

    /**
     * Get a table initialized if exist, else initialize one
     *
     * @param string $id Id wich permit to identify the table
     *
     * @return self
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
        $this->jsInitParameters[$key] = $value;

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
        foreach ($params as $k => $v) {
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
        $this->setJsInitParam('dom', $dom);

        return $this;
    }

    /**
     * Return a new/next column index for a new column added
     *
     * @param array $params MAY contain parent if the table has a complex header
     *
     * @return mixed A string or an integer
     */
    protected function getColumnIndex($params)
    {
        if (isset($params['parent'])) {
            if (is_array($params['parent']) && isset($params['parent']['parent'])) {
                return md5($params['parent'][0]);
            }

            return md5($params['parent'], true);
        }

        return ++$this->iThead;
    }

    /**
     * Add a new column header
     *
     * @param array $params 's column
     */
    protected function setTHead(&$params)
    {
        $k = $this->getColumnIndex($params);

        if (isset($params['parent'])) {
            $this->theadChild[] = [
                'title' => $params['title'],
                'class' => isset($params['className']) ? $params['className'] : null,
                'colspan' => 1,
            ];
            $this->thead[$k]['colspan'] = !isset($this->thead[$k]['colspan']) ? 1 : ($this->thead[$k]['colspan']+1);
            $this->thead[$k]['title']   = $params['parent'];
            $this->header = true;
        } else {
            if (isset($params['title'])) {
                $this->thead[$k]['title']   = $params['title'];
            }
            if (isset($params['className'])) {
                $this->thead[$k]['class'] = $params['className'];
            }
        }
    }

    /**
     * Manage the column tfoot
     * Normalize sFilter params if exist
     *
     * @param array $params
     */
    protected function setTFoot(&$params)
    {
        if (isset($params['sFilter'])) {
            if (isset($params['sFilter']['type']) && in_array($params['sFilter']['type'], self::$columFilteringParams['type'])) {
                $params['sFilter'] = array_merge(self::$columFilteringParams, $params['sFilter']);
                $this->individualColumnFiltering = true;
                $this->footer = true;
            } else {
                unset($params['sFilter']);
            }
        }
    }

    /**
     * Add a column to the table
     *
     * @param array $params Can contain :
     *                      $params[$key]      = mixed   // Properties for Initialization Javascript (see self::$columnParams)
     *                      $params['parent']  = string  // Permit to have a complex header with a colspan...
     *                      // Set the same string to put multiple column under the same th
     *                      $params['sFilter'] = array   // Permit to add a filtering html input for the column
     *                      $params['sFilter']['type']      = string   // see self::$columFilteringParams['type']
     *                      $params['sFilter']['regex']     = bool     //
     *                      $params['sFilter']['sql_table'] = string
     *                      $params['sFilter']['sql_name']  = string
     *                      $params['sFilter']['sqlFilter'] = string   // Initial SQL filter
     *
     * @param bool $show Set false to Add a column to load in the result but wich will not be print
     *
     * @return self
     */
    public function setColumn($params, $show = true)
    {
        if ($show) {
            $this->setTHead($params);
            $this->setTFoot($params);

            $this->columns[] = $params;
        } else {
            $this->unsetColumns[] = $params;
        }

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
        foreach ($columns as $c) {
            $this->setColumn($c, isset($c['show']) ? $c['show'] : true);
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
                'type' => 'POST',
            );
        }
        $this->setJsInitParam('ajax', is_array($ajax) || is_object($ajax) ? (object) $ajax : (string) $ajax);

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
        foreach ($this->columns as $c) {
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
        $n = count($data);
        for ($i = 0;$i<$n;++$i) {
            $data[$i] = $objectize ? (object) $data[$i] : $data[$i];
        }
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
     * @return mixed NULL if there is no column else ARRAY
     */
    protected function getColumnsForJs()
    {
        $rColumns = [];

        foreach ($this->columns as $column) {
            self::removeNotJsOptions($column);
            if ($column !== null) {
                $rColumns[] = $column;
            }
        }

        return !empty($rColumns) ? $rColumns : null;
    }

    /**
     * Format column for javascript : only Keep the required options for columns and convert to object.
     *
     * @param array $column
     */
    protected static function removeNotJsOptions(&$column)
    {
        foreach ($column as $k => $v) {
            if (!in_array($k, self::$columnParams)) {
                unset($column[$k]);
            }
        }
    }

    /**
     * Return javascript string to activate table
     *
     * @return string
     */
    public function getJavascript()
    {
        if (!empty($this->columns)) {
            $this->setJsInitParam('columns', $this->getColumnsForJs());
        }

        $js = 'var '.$this->tableName.' = $("#'.$this->tableName.'").dataTable('."\n"
                .(!empty($this->jsInitParameters) ? PHPToJS::render((object) $this->jsInitParameters) : '')."\n"
            .');'."\n";

        $js .= $this->getJavascriptSearchPart();

        return $js;
    }

    /**
     * Generate the javascript relative to the individual column filtering
     *
     * @return string
     */
    protected function getJavascriptSearchPart()
    {
        if (!$this->individualColumnFiltering) {
            return '';
        }
        $js = 'var '.$this->tableName.'Api = '.$this->tableName.'.api(true);'."\n"
            .'$("#'.$this->tableName.' tfoot th").each( function(colI) {'."\n"
                .'$(".sSearch", this).on("keyup change", function (e) {'."\n"
                .($this->isServerSide() ? '    if ( e.keyCode == 13 ) {'."\n" : '')
                .'        '.$this->tableName.'Api.column( colI ).search('
                .'            '.($this->renderFilterOperators ? '( this.tagName != "INPUT" ||  this.value == \'\' ? \'\' : $(this).prev().find("select").val()) + this.value ' : ' this.value ')
                .'        ).draw();'."\n"
                .($this->isServerSide() ? '    }'."\n" : '')
                .'});'."\n"
            .'});'."\n";

        return $js;
    }

    /**
     * Return html table (min <table id=datatable></table>)
     *
     * @param array $tAttributes To add attributes to the <table> eg. : class=>table-green
     */
    public function getHtml($tAttributes = [])
    {
        $tAttributes['id'] = $this->tableName.(isset($tAttributes['id']) ? ' '.$tAttributes['id'] : '');
        $html = '<table'.Helper::mapAttributes($tAttributes).'>';

        if ($this->header === true) {
            $html .= '<thead>';

            $html .= Helper::theadFormatter($this->thead, 2);
            if (isset($this->theadChild)) {
                $html .= Helper::theadFormatter($this->theadChild);
            }
            $html .= '</thead>';
        }

        if ($this->footer === true) {
            $html .= '<tfoot>';
            $html .= '<tr>';
            foreach ($this->columns as $k => $v) {
                $html .= '<th>'.(isset($v['sFilter']) ? $this->renderFilter($v) : '').'</th>'; //'.(isset($v->className)?' class="'.$v->className.'"':'').'
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
     *
     * @return string
     */
    protected function formatOption($name, $value, $label)
    {
        return '<option value="'.$value.'"'
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

        switch ($sFilter['type']) {
            case 'text'   :
                $r = '<div class="input-group"><div class="input-group-addon">'
                        .($this->renderFilterOperators ?
                        '<select class=form-control>'
                            .'<option value="">'.$this->trans('').'</option>'
                            .$this->formatOption($column['data'], '[!]',     $this->trans('!'))
                            .$this->formatOption($column['data'], '[=]',     $this->trans('='))
                            .$this->formatOption($column['data'], '[!=]',    $this->trans('!='))
                            .$this->formatOption($column['data'], 'reg:',    $this->trans('REGEXP'))
                            .$this->formatOption($column['data'], 'lenght:', $this->trans('Lenght'))
                        .'</select>' : '').'</div>'
                        .'<input class="form-control sSearch" type="text" value="'.(isset($column['data']) && isset($this->filters[$column['data']]) ? preg_replace('/^(\[(!|<=|>=|=|<|>|<>|!=)\]|reg:|in:|lenght:)/i', '', $this->filters[$column['data']]) : '').'">'
                    .'</div></div>';

                return $r;
            case 'number' : case 'date' :
                $r = '<div class="input-group"><div class="input-group-addon">'
                        .($this->renderFilterOperators ?
                        '<select class=form-control>'
                            .'<option value="">'.$this->trans('').'</option>'
                            .$this->formatOption($column['data'], '[=]',  $this->trans('='))
                            .$this->formatOption($column['data'], '[!=]', $this->trans('!='))
                            .$this->formatOption($column['data'], '[<=]', $this->trans('<='))
                            .$this->formatOption($column['data'], '[<]',  $this->trans('<'))
                            .$this->formatOption($column['data'], '[>=]', $this->trans('>='))
                            .$this->formatOption($column['data'], '[>]',  $this->trans('>'))
                            .$this->formatOption($column['data'], 'in:',  $this->trans('IN(int,int,int...)'))
                            .$this->formatOption($column['data'], 'reg:', $this->trans('REGEXP'))
                        .'</select>' : '').'</div>'
                        .'<input class="form-control sSearch" type="text" value="'.(isset($this->filters[$column['data']]) ? preg_replace('/^(\[(<=|>=|=|<|>|<>|!=)\]|reg:|in:)/i', '', $this->filters[$column['data']]) : '').'">'
                    .'</div></div>';

                return $r;
            case 'select' :
                $o = '';
                if (isset($sFilter['options'])) {
                    $sFilter['values'] = $sFilter['options'];
                }
                if (isset($sFilter['values'])) {
                    foreach ($sFilter['values'] as $k => $v) {
                        $o .= '<option value="'.$k.'"'.(isset($column['data']) && isset($this->filters[$column['data']]) && $this->filters[$column['data']] == $k ? ' selected' : '').'>'.$v.'</option>';
                    }
                }
                /** Auto-generate options if the data are loaded **/
                elseif (isset($this->jsInitParameters['data'])) {
                    // TODO
                }

                return '<select class="form-control sSearch"><option value=""></option>'.$o.'</select>';
        }
    }

    /*** ########## Server Side ########## ***/

    /**
     * Set counters active
     * (disabled them permits to gain in performance)
     *
     * @param bool $bool
     *
     * @return self
     */
    public function setCounterActive($bool)
    {
        $this->counters = $bool;

        return $this;
    }

    /**
     * Add the pdo link
     *
     * @param \PDO $link
     *
     * @return self
     */
    public function setPdoLink(PDO $link)
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
        $this->join[] = $join.' '.$table.' ON '.Helper::formatOn($on);
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
    public function setGroupBy($column)
    {
        $this->groupBy = (isset($this->groupBy) ? $this->groupBy.',' : '').$column;

        return $this;
    }

    /**
     * Create the data output array for the DataTables rows
     *
     * @param array $data Data from the SQL get
     *
     * @return array Formatted data in a row based format
     */
    protected function dataOutput($data)
    {
        $out = [];
        $data = array_values($data); // Reset keys
        for ($i = 0, $ien = count($data); $i<$ien; ++$i) {
            $row = [];

            for ($j = 0, $jen = count($this->columns); $j<$jen; ++$j) {
                $column = $this->columns[$j];

                if (isset($column['formatter'])) {
                    if (isset($column['data']) || isset($column['sql_name'])) {
                        $row[ isset($column['data']) ? $column['data'] : $j ] = call_user_func($column['formatter'], $data[$i][ self::fromSQLColumn($column) ], $data[$i], $column);
                    } else {
                        $row[ $j ] = call_user_func($column['formatter'], $data[$i]);
                    }
                } else {
                    // Compatibility with the json .
                    // if preg_match('#\.#', $column['data']) explode ('.', $colum['data'])...
                    if (isset($column['data']) || isset($column['sql_name'])) {
                        $row[ isset($column['data']) ? $column['data'] : $j ] = $data[$i][ self::fromSQLColumn($column) ];
                    } else {
                        $row[ $j ] = '';
                    }
                }
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Generate the SQL queries (optimized for MariaDB/MySQL) to execute.
     *
     * @param array $dtRequest Request send by DataTables.js ($_GET or $_POST)
     *
     * @return array SQL queries to execute (keys: data, recordsFiltered, recordsTotal)
     */
    protected function generateSQLRequest(array $dtRequest)
    {
        $this->request = $dtRequest;

        $limit = $this->limit();
        $order = $this->order();
        $where = self::where($this->filters());
        $iWhere = self::where($this->initFilters());
        $join = $this->join();
        $having = !empty($this->having) ? ' HAVING '.$this->having : '';
        $from = $this->from();
        $groupBy = $this->groupBy();

        $columns = [];
        foreach ($this->columns as $c) {
            if (isset($c['data']) || isset($c['sql_name'])) {
                $columns[] = $this->toSQLColumn($c);
            }
        }
        foreach ($this->unsetColumns as $c) {
            $columns[] = $this->toSQLColumn($c);
        }
        $select = ($this->counters ? 'SQL_CALC_FOUND_ROWS ' : '').implode(',', $columns);

        return [
            'data'             => 'SELECT '.$select.$from.$join.$where.$groupBy.$having.$order.$limit.';',
            'recordsFiltered'  => $this->counters ? 'SELECT FOUND_ROWS() count;' : '',
            'recordsTotal'     => $this->counters ? $this->recordsTotal($from.$join.$iWhere) : '',
        ];
    }

    /**
     * Render the SQL Query to get the total
     *
     * @param string $fromJoinAndWhere
     *
     * @return string SQL Request
     */
    protected function recordsTotal($fromJoinAndWhere)
    {
        return 'SELECT COUNT('.(isset($this->groupBy) ? 'DISTINCT '.$this->groupBy : '*').') count '.$fromJoinAndWhere.';';
    }

    /**
     * SQL rendering of the GroupBy part
     *
     * @return string
     */
    public function groupBy()
    {
        return (isset($this->groupBy) ? ' GROUP BY '.$this->groupBy : '');
    }

    /**
     * SQL rendering of the FROM part
     *
     * @return string
     */
    public function from()
    {
        return ' FROM '.$this->table.($this->table != $this->aliasTable ? ' '.$this->aliasTable : '');
    }

    /**
     * SQL rendering of the JOIN part
     *
     * @return string
     */
    public function join()
    {
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
        if (isset($this->request['order']) && count($this->request['order'])) {
            $orderBy = [];
            for ($i = 0, $ien = count($this->request['order']);$i<$ien;$i++) {
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
        $this->initColumnSearch[] = $this->generateSQLColumnFilter($this->toSQLColumn($column, 2, true), $filter);
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

        $gf = $this->globalFilter();
        $where .= (empty($where) ? $gf : (empty($gf) ? '' : ' AND '.$gf));

        $gf = $this->individualColumnFilters();
        $where .= (empty($where) ? $gf : (empty($gf) ? '' : ' AND '.$gf));

        return $where;
    }

    /**
     *
     * @param string $where_condition
     * @param array  $column
     *
     * @return bool
     */
    public function setHaving($where_condition, $column)
    {
        if (stripos(trim(str_replace(' ', '', $where_condition)), 'SUM(') === false) {
            return false;
        } else {
            $where_condition = str_replace($this->toSQLColumn($column, 2), $this->toSQLColumn($column, 1, true), $where_condition);
            $this->having .= !empty(trim($this->having)) ? ' AND '.$where_condition : ' '.$where_condition;

            return true;
        }
    }

    /**
     * SQL Rendering for the  initial filters set
     *
     * @return string SQL Part Query
     */
    protected function initFilters()
    {
        $initColumnSearch = $this->initColumnSearch;
        foreach ($this->columns as $c) {
            if (isset($c['sqlFilter'])) {
                $where_condition = $this->generateSQLColumnFilter($this->toSQLColumn($c, 2), $c['sqlFilter']);
                if (!$this->setHaving($where_condition, $c)) {
                    $initColumnSearch[] = $this->generateSQLColumnFilter($this->toSQLColumn($c, 2), $c['sqlFilter']);
                }
            }
        }

        return implode(' AND ', $initColumnSearch);
    }

    /**
     * SQL Rendering for the global search
     *
     * @return string SQL Part Query
     */
    public function globalFilter()
    {
        $globalSearch = [];
        if (isset($this->request['search']) && !empty($this->request['search']['value'])) {
            for ($i = 0, $ien = count($this->request['columns']); $i<$ien; $i++) {
                if (self::isSearchable($this->columns[$i])) {
                    $where_condition = $this->toSQLColumn($this->columns[$i], 2).' LIKE '.$this->pdoLink->quote('%'.$this->request['search']['value'].'%');
                    if (!$this->setHaving($where_condition, $this->columns[$i])) {
                        $globalSearch[] = $where_condition;
                    }
                }
            }
        }

        return empty($globalSearch) ? '' : '('.implode(' OR ', $globalSearch).')';
    }

    /**
     * SQL Rendering for the invidividual column filters
     *
     * @return string
     */
    protected function individualColumnFilters()
    {
        $columnSearch = [];
        if (isset($this->individualColumnFiltering)) {
            $this->sRangeSeparator = isset($this->sRangeSeparator) ? $this->sRangeSeparator : '~';
            for ($i = 0, $ien = count($this->columns); $i<$ien; $i++) {
                if (self::isSearchable($this->columns[$i]) && !empty($this->request['columns'][$i]['search']['value'])) {
                    $search_value = trim($this->request['columns'][$i]['search']['value']);
                    $key = $this->toSQLColumn($this->columns[$i], 2, true);
                    $where_condition = $this->generateSQLColumnFilter($key, $search_value);
                    if (!$this->setHaving($where_condition, $this->columns[$i])) {
                        $columnSearch[] = $where_condition;
                    }
                }
            }
        }

        return implode(' AND ', $columnSearch);
    }

    /**
     * The $column is seachable ?
     *
     * @param array $column
     *
     * @return bool
     */
    protected static function isSearchable($column)
    {
        return !isset($column['searchable']) || $column['searchable'] === true ? true : false;
    }

    /**
     * Generate the filter for a column
     *
     * @param string $column
     * @param string $search_value
     * @param \PDO   $pdoLink
     * @param string $sRangeSeparator
     *
     * @return string
     */
    public static function _generateSQLColumnFilter($column, $search_value, PDO $pdoLink, $sRangeSeparator = '~')
    {
        if (preg_match("/^\[(=|!=)\]$/i", $search_value, $match)) {
            return $column.' '.$match[1].' '.$pdoLink->quote('');
        }

        if (preg_match("/^\[(!|<=|>=|=|<|>|<>|!=)\](.+)/i", $search_value, $match)) {
            if ($match[1] == '!=' && $match[2] == 'null') {
                return $column.' IS NOT NULL';
            } elseif ($match[1] == '=' && $match[2] == 'null') {
                return $column.' IS NULL';
            } elseif ($match[1] == '!') {
                return $column.' NOT LIKE '.$pdoLink->quote('%'.$match[2].'%');
            } else {
                return $column.' '.$match[1].' '.$pdoLink->quote($match[2] == 'empty' ? '' : $match[2]);
            }
        } elseif (strpos($search_value, 'lenght:') === 0) {
            return 'CHAR_LENGTH('.$column.') = '.(int) substr($search_value, strlen('lenght:'));
        } elseif (strpos($search_value, 'reg:') === 0) { //&& $column['sFilter']['regex'] === true) {
            return $column.' REGEXP '.$pdoLink->quote(substr($search_value, strlen('reg:')));
        } elseif (preg_match('/(.*)(!?'.$sRangeSeparator.')(.*)/i', $search_value, $matches) && !empty($matches[1]) && !empty($matches[3])) {
            // TODO : use type to format the search value (eg STR_TO_DATE)
                return $column.($matches[2][0] == '!' ? ' NOT' : '')
                    .' BETWEEN '.$pdoLink->quote($matches[1]).' AND '.$pdoLink->quote($matches[3]);
        } elseif (strpos($search_value, 'in:') === 0) {
            $search_value = substr($search_value, strlen('in:'));

            return $column.' IN('.$search_value.')';
        } elseif (strpos($search_value, '!in:') === 0) {
            $search_value = substr($search_value, strlen('in:'));

            return $column.' NOT IN('.$search_value.')';
        } else {
            return $column.' LIKE '.$pdoLink->quote('%'.$search_value.'%');
        }
    }

    /**
     * Generate the filter for a column
     *
     * @param string $column
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
     * @param int   $onlyAlias 0: return sql_table.sql_name AS alias
     *                         1: return alias
     *                         2: return sql_table.sql_name
     * @param bool  $filter
     *
     * @return string
     */
    protected function toSQLColumn($column, $onlyAlias = 0, $filter = false)
    {
        if (!isset($column['sql_name']) && !isset($column['data'])) {
            self::sendFatal('Houston, we have a problem with one of your column : can\'t draw it SQL name because it don\'t have data or sql_name define.'."\n".json_encode($column));
        }

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
     * @param array  $d
     * @param string $pKey
     * @param array  $columns
     */
    protected function patchDuplicateRow($d, $pKey, array $columns)
    {
        $id = $d[$pKey];
        if (isset($this->rData[$id])) {
            foreach ($columns as $c => $separator) {
                $this->rData[$id][$c] .= $separator.$d[$c];
            }
        } else {
            $this->rData[$id] = $d;
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
    public function exec(array $request, $csv = false)
    {
        if ($csv) {
            $this->request['length'] = -1;
        }
        $queries = $this->generateSQLRequest($request);

        $this->addToSend('sqlQuery', $queries['data']);

        $pdo = $this->pdoLink;

        try {
            $q = $pdo->query($queries['data']);
            $this->rData = [];
            // PATCH : Annoying JOIN wich duplicate row if there is different tag
            if (isset($this->patchDuplicateRow)) {
                while ($d = $q->fetch()) {
                    $this->patchDuplicateRow($d, $this->patchDuplicateRow['pKey'], $this->patchDuplicateRow['columns']);
                }
            } else {
                $this->rData = $q ? $q->fetchAll() : [];
            }
        } catch (Exception $Exception) {
            $this->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2].chr(10).$queries['data'], $this->toSend);
        }

        if ($csv) {
            return self::sendCsv($this->rData);
        }

        if ($this->counters) {
            try {
                $q = $pdo->query($queries['recordsFiltered']);
            } catch (Exception $Exception) {
                $this->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2].chr(10).$queries['recordsFiltered'], $this->toSend);
            }
            $recordsFiltered = $q ? $q->fetch()['count'] : 0;

            try {
                $q = $pdo->query($queries['recordsTotal']);
            } catch (Exception $Exception) {
                $this->sendFatal($pdo->errorInfo()[0].' - '.$pdo->errorInfo()[2].chr(10).$queries['recordsTotal'], $this->toSend);
            }
            $recordsTotal = $q->fetch()['count'];
        } else {
            $recordsFiltered = $recordsTotal = 0;
        }

        $this->sendData($this->rData, $recordsFiltered, $recordsTotal);
    }

    /**
     * Add something to send via the ajax
     *
     * @param string $k
     * @param string $v
     *
     * @return self
     */
    public function addToSend($k, $v)
    {
        $this->toSend[$k] = $v;

        return $this;
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
            'data' => $this->dataOutput($data), );
        if (isset($this->toSend)) {
            $toJson = array_merge($this->toSend, $toJson);
        }
        exit(json_encode($toJson));
    }

    /**
     * Return the csv
     *
     * @param array $data
     */
    protected static function sendCsv($data)
    {
        header("Content-Disposition: attachment; filename=".uniqid('dataExpl').'.csv');
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Description: File Transfer");
        exit(Helper::arrayToCsv($data));
    }

    /**
     * Throw a fatal error.
     * This writes out an error message in a JSON string which DataTables will
     * see and show to the user in the browser.
     *
     * @param string $error  Message to send to the client
     * @param array  $toSend Others informations to transfer
     */
    public static function sendFatal($error, $toSend = null)
    {
        $toJson = ['error' => utf8_encode($error)];
        if (isset($toSend)) {
            $toJson = array_merge($toSend, $toJson);
        }

        exit(json_encode($toJson));
    }

    /**
     * @param \Symfony\Component\Translation\TranslatorInterface $translator
     *
     * @return self
     */
    public function setTranslator(\Symfony\Component\Translation\TranslatorInterface $translator)
    {
        $this->translator = $translator;

        return $this;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected function trans($text)
    {
        if (isset($this->translator)) {
            return $this->translator->trans('dataTable.'.$text);
        }

        return $text;
    }
}
