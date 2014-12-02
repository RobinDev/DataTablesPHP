<?php
namespace rOpenDev\DataTablesPHP;

/**
 * PHP DataTablesPHP Helper
 *
 * @author     Original Author Robin <contact@robin-d.fr> http://www.robin-d.fr/
 * @link       http://www.robin-d.fr/DataTablesPHP/
 * @link       https://github.com/RobinDev/DataTablesPHP
 * @since      File available since Release 2014.12.02
 */
class Helper
{
    /**
     * html render function
     *
     * @param array $attributes
     *
     * @return string
     */
    public static function mapAttributes($attributes)
    {
        return ' '.join(' ', array_map(
            function ($sKey) use ($attributes) {
                return is_bool($attributes[$sKey]) ? ($attributes[$sKey] ? $sKey : '') : $sKey.'="'.$attributes[$sKey].'"';
            }, array_keys($attributes)
        ));
    }

    /**
     * Thead formatter
     *
     * @param array $thead
     *
     * @return string
     */
    public static function theadFormatter($thead, $rowspan = 1)
    {
        $html = '<tr>';
        foreach ($thead as $v) {
            if ($rowspan > 1 && !isset($v['colspan'])) {
                $v['rowspan'] = $rowspan;
            }
            $attr = array_intersect_key($v, array_flip(['class', 'colspan', 'rowspan']));
            $html .= '<th'.Helper::mapAttributes($attr).'>'.(isset($v['title']) ? $v['title'] : '').'</th>';
        }
        $html .= '</tr>';

        return $html;
    }

    /**** SQL Helper ***/

    /**
     * SQL rendering for JOIN ON
     *
     * @param array  $on
     * @param string $r
     *
     * @return string
     */
    public static function formatOn($on, $r = '')
    {
        if (isset($on[0]) && is_array($on[0])) {
            foreach ($on as $on2) {
                $r = self::formatOn($on2, $r);
            }
        } else {
            $on2 = array_keys($on);
            $r .= (!empty($r) ? ' AND ' : '').'`'.key($on).'`.`'.current($on).'` = `'.next($on2).'`.`'.next($on).'`';
        }

        return $r;
    }

    /******************/

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
    public static function arrayToCsv($array, $header_row = true, $col_sep = ",", $row_sep = "\n", $qut = '"')
    {
        if (!is_array($array) || !isset($array[0]) || !is_array($array[0])) {
            return false;
        }
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
