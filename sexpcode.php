<?php
/*
Plugin Name: SexpCode for WordPress
Plugin URI: http://cairnarvon.rotahall.org/2010/05/25/towards-a-better-bbcode/
Description: Enables the use of SexpCode in comments.
Version: 1.1
Author: Koen Crolla
Author URI: http://cairnarvon.rotahall.org/
*/

function sexpcode_parse_sexp($string, $offset, $tags)
{
    /* sexpcode_parse_sexp :: String -> Int -> Array -> (String, Int) */

    if ($string[$offset] != '{')
        return array(0, -1); /* parser fuck-up, not syntax error */
    
    ++$offset;
    $eos = strlen($string);


    /* Retrieve the entire function expression first */

    $i = $offset;
    $n = 0;
    while ($i < $eos && $string[$i] != ' ' || $n != 0) {
        switch ($string[$i]) {
        case '{':
            ++$n;
            break;
        case '}':
            if (--$n < 0) return array(0, -1);
            break;
        }
        ++$i;
    }
    if ($i == $eos) return array(0, -1);
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


    $open = $close = "";
    $verbatim = false;

    if (array_key_exists($expr, $tags) && $tags[$expr]['arity'] > 0) {

        /* Simple expressions of >0-arity functions can use the special
         * argument syntax.
         */

        $open = $tags[$expr]['open'];
        $close = $tags[$expr]['close'];

        for ($i = 1; $i <= $tags[$expr]['arity']; ++$i) {
            $j = $offset;

            while ($j < $eos && $string[$j] != ' ')
                ++$j;

            if ($offset == $eos) return array(0, -1);
            $open = str_replace('%' . $i . '%',
                                substr($string, $offset, $j - $offset),
                                $open);
            $offset = $j + 1;
        }

    } else {

        /* Normal function syntax; might be compound, iterated. */

        $funcs = array();
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
            @list($func, $iter) = explode('*', $func);
            $iter = $iter === null ? 1 : floor($iter);

            $o = $c = "";

            if ($func[0] == '{') {
                /* Higher-arity function (or pretender) */

                $func = substr($func, 1, -1);
                list($func, $args) = explode(' ', $func, 2);
                if (!array_key_exists($func, $tags))
                    return array(0, -1);

                $args = explode(' ', $args, $tags[$func]['arity']);
                $o = $tags[$func]['open'];
                $c = $tags[$func]['close'] . $close;

                for ($i = 1; $i <= $tags[$func]['arity']; ++$i)
                    $o = str_replace('%' . $i . '%', $args[$i - 1], $o);

            } else {
                /* Simple function (or pretender) */

                if (!array_key_exists($func, $tags))
                    return array(0, -1);

                $o = $tags[$func]['open'];
                $c = $tags[$func]['close'];

                if ($tags[$func]['arity'] > 0)
                    for ($i = 1; $i <= $tags[$func]['arity']; ++$i)
                        $o = str_replace('%' . $i . '%', '', $o);
            }
            
            $iter = min($iter, $tags[$func]['iter'] ? 3 : 1);
            while ($iter > 0) {
                $open .= $o;
                $close = $c . $close;
                --$iter;
            }

            if ($func == 'verbatim') $verbatim = true;

        }

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
                $ret .= substr($string, $offset, $i - $offset - 1);
                list($p, $i) = sexpcode_parse_sexp($string, $i, $tags);

                $ret .= $p;
                $offset = $i;
            } else ++$n;
            break;
        }
        ++$i;
    }

    return $ret . substr($string, $offset) . $close;
}


function sexpcode_translate($input)
{
    $tags = array('b' => array('open'  => '<b>',
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
                  'o' => array('open'  => '<span style="text-decoration: ' .
                                          'overline">',
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
                  'spoiler' => array('open'  => '<span style="background:' .
                                                ' #000" onmouseover="this' .
                                                '.style.color=\'#FFF\';" ' .
                                                'onmouseout="this.style.c' .
                                                'olor=this.style.backgrou' .
                                                'ndColor=\'#000\'">',
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


    $input = str_replace(array('\\',    '\{',     '\}'),
                         array('&#92;', '&#123;', '&#125;'),
                         $input);


    $i = 0;
    $out = "";
    $eos = strlen($input);

    while ($i < $eos) {
        $j = strpos($input, '{', $i);

        if ($j === false) {
            $out .= substr($input, $i);
            return $out;
        }

        $out .= substr($input, $i, $j - $i);
        $i = $j;

        list($parsed, $i) = sexpcode_parse_sexp($input, $i, $tags);
        if ($i < 0) return $input;
        
        $out .= $parsed;

    }

    return $out;
}

add_filter('comment_text', 'sexpcode_translate', 5);

?>
