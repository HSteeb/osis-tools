<?php

use HSteeb\osis2html\Converter;
require 'vendor/autoload.php';

/**
 * Render OSIS XML file to HTML for display on bible2.net
 */
if ($argc < 3) {
  echo <<<EOUSAGE
Usage:
  php osis2html.php infile outfile

EOUSAGE;
  exit;
}
Converter::init();
$infile  = $argv[1];
$outfile = $argv[2];

Converter::run($infile, $outfile);

?>
