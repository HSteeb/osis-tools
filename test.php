<?php
use HSteeb\osis2html\Replacer;

require 'vendor/autoload.php';

$R = new Replacer();

echo $R->getVerseStart("abc");

?>
