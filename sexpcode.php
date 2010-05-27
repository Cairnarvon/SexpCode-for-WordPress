<?php
/*
Plugin Name: SexpCode for WordPress
Plugin URI: http://cairnarvon.rotahall.org/2010/05/25/towards-a-better-bbcode/
Description: Enables the use of SexpCode in comments.
Version: 1.0.1
Author: Koen Crolla
Author URI: http://cairnarvon.rotahall.org/
*/

function sexpcode_translate($input)
{
    $tags = array('b' => array('open'  => '<b>',
                               'close' => '</b>',
                               'iter'  => false),
                  'i' => array('open'  => '<i>',
                               'close' => '</i>',
                               'iter'  => false),
                  'u' => array('open'  => '<u>',
                               'close' => '</u>',
                               'iter'  => false),
                  's' => array('open'  => '<s>',
                               'close' => '</s>',
                               'iter'  => false),
                  'o' => array('open'  => '<span style="text-decoration: ' .
                                          'overline">',
                               'close' => '</span>',
                               'iter'  => false),
                  'sub' => array('open'  => '<sub>',
                                 'close' => '</sub>',
                                 'iter'  => true),
                  'sup' => array('open'  => '<sup>',
                                 'close' => '</sup>',
                                 'iter'  => true),
                  'code' => array('open'  => '<code>',
                                  'close' => '</code>',
                                  'iter'  => false),
                  'spoiler' => array('open'  => '<span style="background:' .
                                                ' #000" onmouseover="this' .
                                                '.style.color=\'#FFF\';" ' .
                                                'onmouseout="this.style.c' .
                                                'olor=this.style.backgrou' .
                                                'ndColor=\'#000\'">',
                                     'close' => '</span>',
                                     'iter'  => false),
                  'quote' => array('open'  => '<blockquote>',
                                   'close' => '</blockquote>',
                                   'iter'  => true),
                  'blockquote' => array('open'  => '<blockquote>',
                                        'close' => '</blockquote>',
                                        'iter'  => true),
                  'm' => array('open'  => '<pre>',
                               'close' => '</pre>',
                               'iter'  => false),
                  'tt' => array('open'  => '<pre>',
                                'close' => '</pre>',
                                'iter'  => false));

    $input = strtr($input, array('\\' => '&#92;',
                                 '\\{' => '&#123;',
                                 '\\}' => '&#125;'));

    $closers = array();
    $i = $j = $k = 0;

    $out = "";

    while ($i < strlen($input)) {
        if ($input[$i] == '{') {
            if (($j = strpos($input, ' ', $i)) === false) return input;
            $expr = substr($input, $i + 1, $j - $i - 1);

            if (preg_match('/^[^0-9a-zA-Z\s]+/', $expr) === 1) {
                $j = strpos($input, ($expr . '}'));
                $i = $j === false ? strlen($input) : $j + strlen($expr) + 1;

            } else {
                $open = "";
                $close = "";
                $verb = false;

                $subs = explode('.', $expr);
                foreach ($subs as $sub) {
                    list($tag, $n) = explode('*', $sub, 2);

                    if ($tags[$tag] !== null) {
                        $n = $n === null || !$tags[$tag]['iter'] ? 1 : $n + 0;
                        if ($n > 3) $n = 3;
    
                        while ($n-->0) {
                            $open .= $tags[$tag]['open'];
                            $close = $tags[$tag]['close'] . $close;
                        }
                    } elseif ($tag == "verbatim") {
                        $verb = true;
                    } else return $input;
                }
    
                $out .= substr($input, $k, $i - $k) . $open;
                $closers[] = $close;
                $k = $j + 1;

                if ($verb) {
                    $verb = 0;
                    while ($verb >= 0 && $i < strlen($input)) {
                        ++$i;
                        if ($input[$i] == '{')
                            ++$verb;
                        elseif ($input[$i] == '}')
                            --$verb;
                    }
                    continue;
                }
            }

        } elseif ($input[$i] == '}') {
            if (($j = count($closers)) < 1) return $input;
            --$j;

            $out .= substr($input, $k, $i - $k) . $closers[$j];
            unset($closers[$j]);
            $closers = array_values($closers);

            $k = $i + 1;

        }
        ++$i;
    }

    $out .= substr($input, $k);

    while (($i = count($closers)) > 0) {
        --$i;
        $out .= $closers[$i];
        unset($closers[$i]);
        $closers = array_values($closers);
    }

    return $out;
}

add_filter('comment_text', 'sexpcode_translate', 5);

?>
