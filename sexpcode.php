<?php
/*
Plugin Name: SexpCode for WordPress
Plugin URI: http://cairnarvon.rotahall.org/2010/05/25/towards-a-better-bbcode/
Description: Enables the use of SexpCode in comments.
Version: 1.2
Author: Koen Crolla
Author URI: http://cairnarvon.rotahall.org/
*/

$sexpcode_tags =
    array('b' => array('open'  => '<b>',
                       'close' => '</b>',
                       'iter'  => false,
                       'arity' => 0),
          'i' => array('open'  => '<i>',
                       'close' => '</i>',
                       'iter'  => false,
                       'arity' => 0),
          'u' => array('open'  => '<u>',
                       'close' => '</u>',
                       'iter'  => false,
                       'arity' => 0),
          's' => array('open'  => '<s>',
                       'close' => '</s>',
                       'iter'  => false,
                       'arity' => 0),
          'o' => array('open'  => '<span style="text-decoration: overline' .
                                  '">',
                       'close' => '</span>',
                       'iter'  => false,
                       'arity' => 0),
          'm' => array('open'  => '<pre>',
                       'close' => '</pre>',
                       'iter'  => false,
                       'arity' => 0),
          'tt' => array('open'  => '<pre>',
                        'close' => '</pre>',
                        'iter'  => false,
                        'arity' => 0),
          'sub' => array('open'  => '<sub>',
                         'close' => '</sub>',
                         'iter'  => true,
                         'arity' => 0),
          'sup' => array('open'  => '<sup>',
                         'close' => '</sup>',
                         'iter'  => true,
                         'arity' => 0),
          'quote' => array('open'  => '<blockquote>',
                           'close' => '</blockquote>',
                           'iter'  => true,
                           'arity' => 0),
          'spoiler' => array('open'  => '<span style="background: #000" o' .
                                        'nmouseover="this.style.color=\'#' .
                                        'FFF\';" onmouseout="this.style.c' .
                                        'olor=this.style.backgroundColor=' .
                                        '\'#000\'">',
                             'close' => '</span>',
                             'iter'  => false,
                             'arity' => 0),
          'verbatim' => array('open'  => '',
                              'close' => '',
                              'iter'  => false,
                              'arity' => 0),
          'blockquote' => array('open'  => '<blockquote>',
                                'close' => '</blockquote>',
                                'iter'  => true,
                                'arity' => 0),
          'url' => array('open'  => '<a href="%1%">',
                         'close' => '</a>',
                         'iter'  => false,
                         'arity' => 1),
          'code' => array('open'  => '<code title="%1% code">',
                          'close' => '</code>',
                          'iter'  => false,
                          'arity' => 1));


function sexpcode_next_arg($input, $offset)
{
    /* sexpcode_next_arg :: String -> Int -> (String, Int) */

    $eos = strlen($input);
    $n = 0;

    while ($offset < $eos && $input[$offset] == ' ') ++$offset;

    $i = $offset;

    while ($offset < $eos && ($input[$offset] != ' ' || $n != 0)) {
        switch ($input[$offset]) {
        case '{':
            ++$n;
            break;
        case '}':
            --$n;
            break;
        }
        ++$offset;
    }

    $arg = substr($input, $i, $offset - $i);
    $arg = str_replace(array("'{", '}'), array("", ""), $arg);
    $arg = preg_replace("/{[^ ]+ /", "", $arg);

    return array($arg, $offset + 1);
}

function sexpcode_get_tags($expr, $defs)
{
    /* sexpcode_get_tags :: String -> (String, String, Boolean) */

    global $sexpcode_tags;
    $funcs = array();
    $open = $close = "";
    $tot_arity = 0;
    $frep_c = 1;
    $verbatim = false;

    for ($i = $j = $n = 0; $i < strlen($expr); ++$i) {
        switch ($expr[$i]) {
        case '.':
            if ($n == 0) {
                $funcs[] = substr($expr, $j, $i - $j);
                $j = $i + 1;
            }
            break;
        case '{':
            ++$n;
            break;
        case '}':
            --$n;
            break;
        }
    } 
    $funcs[] = substr($expr, $j);

    foreach ($funcs as $func) {
        $func = strtr($func, "^", "*");
        @list($func, $iter) = explode('*', $func);
        $iter = $iter === null ? 1 : floor($iter);

        $o = $c = "";
        $alias = false;

        if ($func[0] == '{') {
            /* Higher-arity function (or pretender) */

            $func = substr($func, 1, -1);
            list($func, $args) = explode(' ', $func, 2);

            if (array_key_exists($func, $sexpcode_tags)) {
                $o = $sexpcode_tags[$func]['open'];
                $c = $sexpcode_tags[$func]['close'];
                $verbatim = false;
                $arity = $sexpcode_tags[$func]['arity'];

            } elseif (array_key_exists($func, $defs)) {
                list($o, $c, $verbatim, $arity) = $defs[$func];
                $alias = true;

            } else return false;

            for ($i = 1, $j = 0; $i <= $arity; ++$i) {
                if (($p = sexpcode_next_arg(&$args, $j)) === false) break;
                list($arg, $j) = $p;

                $o = str_replace('%' . $i . '%', $arg, $o);
                --$arity;
            }
            $i_start = $i;

        } elseif (array_key_exists($func, $defs)) {
            /* Function alias */

            list($o, $c, $verbatim, $arity) = $defs[$func];
            $alias = true;
            $i_start = 1;

        } elseif (array_key_exists($func, $sexpcode_tags)) {
            /* Simple function (or pretender) */

            $o = $sexpcode_tags[$func]['open'];
            $c = $sexpcode_tags[$func]['close'];

            $arity = $sexpcode_tags[$func]['arity'];
            $i_start = 1;

        } else return false;

        if ($arity > 0) {
            $j = $arity;
            $i = $i_start;
            while ($j--) {
                $o = str_replace('%' . $i . '%', '%' . $frep_c . '%', $o);
                ++$i;
                ++$frep_c;
            }
        }
        
        $iter = min($iter, !$alias &&
                            $arity == 0 &&
                            $sexpcode_tags[$func]['iter'] ? 3 : 1);

        while ($iter-->0) {
            $open .= $o;
            $close = $c . $close;
            $tot_arity += $arity;
        }

        if ($func == 'verbatim') $verbatim = true;
    }

    return array($open, $close, $verbatim, $tot_arity);
}


function sexpcode_parse_sexp($string, $offset, $defs)
{
    /* sexpcode_parse_sexp :: String -> Int -> Array -> (String, Int) */

    if ($string[$offset] != '{')
        return array("", -1); /* parser fuck-up, not syntax error */
    
    ++$offset;
    $eos = strlen($string);
    global $sexpcode_tags;


    /* Retrieve the entire function expression first */

    $i = $offset;
    $n = 0;
    while ($i < $eos && $string[$i] != ' ' || $n != 0) {
        switch ($string[$i]) {
        case '{':
            ++$n;
            break;
        case '}':
            if (--$n < 0) return array("", -1);
            break;
        }
        ++$i;
    }
    if ($i == $eos) return array("", -1);
    $expr = substr($string, $offset, $i - $offset);
    $offset = $i + 1;


    /* Bun's special verbatim syntax. */

    if (preg_match('/^[^a-zA-Z0-9{}]/', $expr)) {
        $end = strpos($string, " " . $expr, $offset);

        return $end === false ? array(substr($string, $offset), $eos)
                              : array(substr($string,
                                             $offset,
                                             $end - $offset),
                                      $end + strlen($expr) + 2);
    }


    /* Function definition */

    if ($expr == "define") {
        $i = $offset;
        while ($i < $eos && $string[$i] !== ' ')
            ++$i;
        if ($i == $eos) return array("", -1);

        $alias = substr($string, $offset, $i - $offset);

        ++$i;
        $offset = $i;
        $n = 0;
        while ($i < $eos) {
            if ($string[$i] == '{')
                ++$n;
            elseif ($string[$i] == '}') {
                if ($n == 0) break;
                --$n;
            }
            ++$i;
        }
        if ($i == $eos) return array("", -1);

        $expr = substr($string, $offset, $i - $offset);
        $offset = $i + 1;

        if (($defs[$alias] = sexpcode_get_tags($expr, &$defs)) === false)
            return array("", -1);

        return array("", $offset);
    }

    
    /* And undefinition. */

    if ($expr == "undefine") {
        $i = $offset;
        while ($i < $eos && $string[$i] !== '}')
            ++$i;
        if ($i == $eos) return array("", -1);

        $fun = substr($string, $offset, $i - $offset);
        if (array_key_exists($fun, $defs))
            unset($defs[$fun]);

        return array("", $i + 1);
    }


    /* Regular function expression */

    if (($t = sexpcode_get_tags($expr, &$defs)) === false)
        return array("", -1);
    list($open, $close, $verbatim, $arity) = $t;

    for ($i = 1; $i <= $arity; ++$i) {
        if (($p = sexpcode_next_arg(&$string, $offset)) === false)
            return array("", -1);
        list($arg, $offset) = $p;

        $open = str_replace('%' . $i . '%', $arg, $open);
    }


    $ret = $open;
    $i = $offset;
    $n = 0;

    while ($i < $eos) {
        switch ($string[$i]) {
        case '}':
            if ($n == 0) {
                return array($ret . substr($string,
                                           $offset,
                                           $i - $offset) . $close,
                             $i + 1);
            } else --$n;
            break;

        case '{':
            if (!$verbatim) {
                $ret .= substr($string, $offset, $i - $offset);
                list($p, $i) = sexpcode_parse_sexp(&$string, $i, &$defs);

                if ($i < 0) return array("", -1);

                $ret .= $p;
                $offset = $i;
                --$i;
            } else ++$n;
            break;
        }
        ++$i;
    }

    
    /* User omitted closing braces; close his tags. */

    return array($ret . substr($string, $offset) . $close, $eos);
}


function sexpcode_translate($input)
{
    $input = str_replace(array('\\\\',    '\{',     '\}'),
                         array('&#92;', '&#123;', '&#125;'),
                         $input);

    $i = 0;
    $out = "";
    $eos = strlen($input);

    $defs = array();

    while ($i < $eos) {
        $j = strpos($input, '{', $i);

        if ($j === false) {
            $out .= substr($input, $i);
            return $out;
        }

        $out .= substr($input, $i, $j - $i);
        $i = $j;

        list($parsed, $i) = sexpcode_parse_sexp(&$input, $i, &$defs);
        if ($i < 0) return $input;
        
        $out .= $parsed;

    }

    return $out;
}

add_filter('comment_text', 'sexpcode_translate', 50);

?>
