<?php

namespace Test;

use HSteeb\osis2html\Converter;

class ConverterTest extends \PHPUnit\Framework\TestCase
{
    private $Converter;
    private const PROLOG = <<<EOPROLOG
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
EOPROLOG;

    private const EPILOG = <<<EOEPILOG

</osisText>
</osis>
EOEPILOG;

      private const SRC = self::PROLOG . self::EPILOG;

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

    public function testConvertChapterTagsDivVerseTitle()
    {
      # in ReplacerTest, <verse eID...> is in the way, but here, conversoin of the section succeeds.
      $text = self::PROLOG . <<<EOTEXT
<div type="section"><verse eID="Exod.1.7"/>
<title>Izip Ram ih Israel Tuarnak</title>
EOTEXT
        . self::EPILOG;

      $exp = <<<EOEXP
<h2 id="top" class="main">S</h2>
<p class="chapterLinks">
<a href="#1">1</a></p>
</div><p id="1" class='chapter'><a href="#top">1</a></p>
<h3>Izip Ram ih Israel Tuarnak</h3>

EOEXP;
      $this->assertEquals($exp, $this->Converter->testConvert($text));
    }

    public function testConvertFootnotes()
    {
      $text = self::PROLOG . <<<EOTEXT
<verse osisID="Exod.12.1" sID="Exod.12.1"/><p>I,<verse eID="Exod.12.1"/>
<verse osisID="Exod.12.2" sID="Exod.12.2"/>&lt;&lt;T.<verse eID="Exod.12.2"/>
<verse osisID="Exod.12.3" sID="Exod.12.3"/>I <note placement="foot"><reference type="annotateRef">12:3</reference> <catchWord>t no:</catchWord> H.</note>t.<verse eID="Exod.12.3"/>
<verse osisID="Exod.12.4" sID="Exod.12.4"/>C.<verse eID="Exod.12.4"/>
<verse osisID="Exod.12.5" sID="Exod.12.5"/>N.<verse eID="Exod.12.5"/>
<verse osisID="Exod.12.6" sID="Exod.12.6"/>C <note placement="foot"><reference type="annotateRef">12:6</reference> <catchWord>k:</catchWord> H.</note>k.<verse eID="Exod.12.6"/>
<verse osisID="Exod.12.7" sID="Exod.12.7"/>C.<verse eID="Exod.12.7"/>
<verse osisID="Exod.12.8" sID="Exod.12.8"/>C.<verse eID="Exod.12.8"/>
<verse osisID="Exod.12.9" sID="Exod.12.9"/>C.<verse eID="Exod.12.9"/>
<verse osisID="Exod.12.10" sID="Exod.12.10"/>C.<verse eID="Exod.12.10"/>
<verse osisID="Exod.12.11" sID="Exod.12.11"/>C.</p><verse eID="Exod.12.11"/>
EOTEXT
        . self::EPILOG;

      $exp = <<<EOEXP
<h2 id="top" class="main">S</h2>
<p class="chapterLinks">
<a href="#1">1</a></p>
</div><p id="1" class='chapter'><a href="#top">1</a></p>
<p><span id="12v1" class="verse">1</span> I,
<span id="12v2" class="verse">2</span> &lt;&lt;T.
<span id="12v3" class="verse">3</span> I t.
<span id="12v4" class="verse">4</span> C.
<span id="12v5" class="verse">5</span> N.
<span id="12v6" class="verse">6</span> C k.
<span id="12v7" class="verse">7</span> C.
<span id="12v8" class="verse">8</span> C.
<span id="12v9" class="verse">9</span> C.
<span id="12v10" class="verse">10</span> C.
<span id="12v11" class="verse">11</span> C.</p>
<div class="fn">12:3 <em>t no:</em> H.</div>
<div class="fn">12:6 <em>k:</em> H.</div>

EOEXP;
      $this->assertEquals($exp, $this->Converter->testConvert($text));
    }



}
