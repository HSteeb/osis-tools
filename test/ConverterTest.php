<?php

namespace Test;

use HSteeb\osis2html\Converter;

class ConverterTest extends \PHPUnit\Framework\TestCase
{
    private $Converter;
    private const SRC = <<<EOSRC
<?xml version="1.0" encoding="UTF-8"?>
<osis xmlns="http://www.bibletechnologies.net/2003/OSIS/namespace" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.bibletechnologies.net/2003/OSIS/namespace http://www.crosswire.org/~dmsmith/osis/osisCore.2.1.1-cw-latest.xsd">
<osisText osisRefWork="Bible" xml:lang="und" osisIDWork="-x">
<header>
<work osisWork="-x"/>
</header>
<div type="book" osisID="Gen" canonical="true">
<title type="runningHead">S</title><milestone type="x-usfm-toc3" n="S"/>
</div>
<chapter osisID="Gen.1" sID="Gen.1"/>
</osisText>
</osis>
EOSRC;

    public function setUp(): void
    {
        $this->Converter = new Converter();
    }

    public function testConstructor()
    {
        $this->assertNotNull($this->Converter);
    }

    public function testConvert()
    {
      $exp = <<<EOEXP
<h2 id="top" class="main">S</h2>
<p class="chapterLinks">
<a href="#1">1</a></p>
</div><p id="1" class='chapter'><a href="#top">1</a></p>

EOEXP;
      $this->assertEquals($exp, $this->Converter->testConvert(self::SRC));
    }

    public function testConvertWithConfig()
    {
      $exp = <<<EOEXP
<h2 id="TOP" class="main">S</h2>
<p class="chapterLinks">
<a href="#1">1</a></p>
</div><p id="1" class='chapter'><a href="#TOP">1</a></p>

EOEXP;
      $Config = [ "rootID" => "TOP" ]; # test just one config property
      $Converter = new Converter($Config);
      $this->assertEquals($exp, $Converter->testConvert(self::SRC));
    }

    public function testConvertSplitExceptions1()
    {
      $this->expectExceptionMessage('Failed to split');
      $this->assertEquals("", $this->Converter->testConvert("/header\s*>\s*(.*?)\s*(<chapter.*?)</osisText\s"));
    }

}
