<?php

namespace SimpleDAO\Utils;

/**
 * Description of Inflector
 *
 * @author cevantime
 */
class Inflector
{
    /**
     * 
     * @param string $str the string to transform to snake case
     * @return string
     */
    public static function snakify($str)
    {
        preg_match_all('/([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)/', $str, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    /**
     * 
     * @param string $str
     * @return string 
     */
    public static function camelize($str)
    {
        return str_replace('_', '', ucwords($str, '_'));
    }
}
