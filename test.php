<?php

/*  Test suite for SexpCode for WordPress. This is intended to be run from
 * the command line, and isn't part of the actual plugin.
 */

error_reporting(E_ALL);

function add_filter($a, $b) {}

include "sexpcode.php";

$tests = array();

$tests[1] = array("Regular text, no markup.",
                  "Regular text, no markup.");

$tests[] = array("Text {b simple function}",
                 "Text <b>simple function</b>");
$tests[] = array("{i simple function} text",
                 "<i>simple function</i> text");
$tests[] = array("{u simple function}",
                 "<u>simple function</u>");
$tests[] = array("t{b simple}x",
                 "t<b>simple</b>x");

$tests[] = array("{b.u compound}",
                 "<b><u>compound</u></b>");
$tests[] = array("{b.i.u Triple compound!} ",
                 "<b><i><u>Triple compound!</u></i></b> ");
$tests[] = array(" {b.b Repeated compound }",
                 " <b><b>Repeated compound </b></b>");

$tests[] = array("Testing {sub*3 iteration!}",
                 "Testing <sub><sub><sub>iteration!</sub></sub></sub>");
$tests[] = array("{b*2 Can't iterate this.}",
                 "<b>Can't iterate this.</b>");
$tests[] = array("{sup*99 abuse}!",
                 "<sup><sup><sup>abuse</sup></sup></sup>!");
$tests[] = array("{sub*0 Invalid?}",
                 "Invalid?");

$tests[] = array("{--- Verbatim ---}",
                 "Verbatim");
$tests[] = array("{--- Not verbatim---}",
                 "Not verbatim---}");
$tests[] = array(" {. x .} ",
                 " x ");
$tests[] = array("{*0 test }}}{{} *0}",
                 "test }}}{{}");
$tests[] = array("{verbatim This is verbatim too.}",
                 "This is verbatim too.");
$tests[] = array("A {verbatim.b {u compound}} verbatim.",
                 "A <b>{u compound}</b> verbatim.");
$tests[] = array("{u.verbatim Verbatim comp}ound.",
                 "<u>Verbatim comp</u>ound.");

$tests[] = array("{url http://example.com High arity!}",
                 '<a href="http://example.com">High arity!</a>');
$tests[] = array("{{url http://rotahall.org} Equivalent.}",
                 '<a href="http://rotahall.org">Equivalent.</a>');
$tests[] = array("This {url.b is relatively undefined}.",
                 'This <a href=""><b>is relatively undefined</b></a>.');
$tests[] = array(" {b.{url http://example.org/}.u composurl}.",
                 ' <b><a href="http://example.org/"><u>composurl</u></a><' .
                 '/b>.');
$tests[] = array("{b.url.u c}",
                 '<b><a href=""><u>c</u></a></b>');

$tests[] = array("This is {b.u ultimate} {url http://example.com/ test} o" .
                 "f {sup*30.u expart} {verbatim.{code C} commenting}{-*- " .
                 "v -*-}.",
                 'This is <b><u>ultimate</u></b> <a href="http://example.' .
                 'com/">test</a> of <sup><sup><sup><u>expart</u></sup></s' .
                 'up></sup> <code title="C code">commenting</code>v.');


/* Add more tests here. */


$start = microtime(true);
$fail = 0;
$failed = array();

echo "Starting \033[1m", count($tests), "\033[0m tests.\n";

foreach ($tests as $id => $test) {
    $s = sexpcode_translate($test[0]) == $test[1];
    echo "\nTest ", $id, "... ", $s ? "\033[;32mPASS\033[0m"
                                    : "\033[1;31mFAIL\033[0m";

    if (!$s) {
        ++$fail;
        $test[] = sexpcode_translate($test[0]);
        $failed[] = $test;
    }
}

$tot = microtime(true) - $start;

echo "\n\n", count($tests), " tests finished in ", $tot, " seconds (",
     count($tests) / $tot, " tests per second, ", $tot / count($tests),
     " seconds per test).\n\n";
echo "\033[1m", $fail, "\033[0m test" . ($fail == 1 ? '' : 's'),
     " failed!\n\n";

if ($fail) {
    foreach ($failed as $f) {
        echo "\033[1mInput:\033[0m           ", $f[0], "\n",
             "\033[1mExpected output:\033[0m ", $f[1], "\n",
             "\033[1mActual output:\033[0m   ", $f[2], "\n\n";
    }
}
