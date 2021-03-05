<?php

use HSteeb\osis2html\Converter;
require 'vendor/autoload.php';

/**
 * Render OSIS XML file to HTML for display on bible2.net
 */
if ($argc < 3) {
  echo <<<EOUSAGE
Usage:
  php osis2html.php infile outfile [config.json]

EOUSAGE;
  exit;
}
$infile  = $argv[1];
if (!file_exists($infile)) {
  echo "File $infile not found.\n";
  exit;
}
$outfile = $argv[2];

$Config  = [];
if ($argc == 4) {
  $jsonfile = $argv[3];
  if (!file_exists($jsonfile)) {
    echo "File $jsonfile not found.\n";
    exit;
  }
  $s = file_get_contents($jsonfile);
  $Config = json_decode($s, /* assoc */ true);
  if ($Config === null) {
    echo "Invalid JSON: " . json_last_error_msg() . "\n";
    exit;
  }
  echo "Loaded JSON $jsonfile.\n";
}

$Converter = new Converter($Config);
$prepareFunction = function($osis)
{
  # drop "@", replace angle bracket quotes by typographic ones
  $From = ["@", "&lt;&lt;", "&gt;&gt;", "&lt;", "&gt;"];
  $To   = ["", "\u{00ab}", "\u{00bb}", "\u{2039}", "\u{203A}"];
  $osis = str_replace($From, $To, $osis);
  return $osis;
};
$Converter->run($infile, $outfile, $prepareFunction);

?>
