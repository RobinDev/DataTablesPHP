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
