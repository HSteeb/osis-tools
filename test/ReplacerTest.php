<?php

namespace Test;

use HSteeb\osis2html\Replacer;

class ReplacerTest extends  \PHPUnit\Framework\TestCase
{
    private $Replacer;

    public function setUp(): void
    {
        $this->Replacer = new Replacer();
    }

    public function testConstructor()
    {
        $this->assertNotNull($this->Replacer);
    }

    public function testGetVerseStart()
    {
      $this->assertEquals("abc", $this->Replacer->convertVerseStart("abc"));

      $this->assertEquals(
          "<span id=\"14v1\" class=\"vers\">1</span> "
        , $this->Replacer->convertVerseStart('<verse osisID="Gen.14.10" sID="Gen.14.1"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\" class=\"vers\">10</span> "
        , $this->Replacer->convertVerseStart('<verse osisID="Gen.14.10" sID="Gen.14.10"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\"/><span id=\"14v11\"/><span class=\"vers\">10–11</span> "
        , $this->Replacer->convertVerseStart('<verse sID="Gen.14.10 Gen.14.11"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\"/><span id=\"14v11\"/><span id=\"14v12\"/><span class=\"vers\">10–12</span> "
        , $this->Replacer->convertVerseStart('<verse sID="Gen.14.10 Gen.14.11 Gen.14.12"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\"/><span id=\"14v11\"/><span id=\"15v1\"/><span class=\"vers\">14,10–15,1</span> "
         , $this->Replacer->convertVerseStart('<verse sID="Gen.14.10 Gen.14.11 Gen.15.1"/>')
         );
    }

    public function testGetVerseStartExceptions1()
    {
       $this->expectExceptionMessage('Cannot handle sID');
       $this->Replacer->convertVerseStart('<verse sID="xy"/>');
    }

    public function testGetVerseStartExceptions2()
    {
       $this->expectExceptionMessage('Cannot handle sID');
       $this->Replacer->convertVerseStart('<verse sID="Gen.14.10 xy"/>');
    }

    public function testGetVerseStartExceptions3()
    {
       $this->expectExceptionMessage('Descending chapter numbers 14/13 in');
       $this->Replacer->convertVerseStart('<verse sID="Gen.14.10 Gen.13.11"/>');
    }

    public function testGetVerseStartExceptions4()
    {
       $this->expectExceptionMessage('Missing sID value in');
       $this->Replacer->convertVerseStart('<verse sID=""/>');
    }

    public function testFormatBooksToc()
    {
      $Map = [
          "Gn" => "Genesis"
        , "Ex" => "Exodus"
        , "Mt" => "Matthew"
        , "Ap" => "Revelation"
        ];
      $exp = <<<EOEXP
<h2 class="bookLinks">OT</h2>
<p>
<a href="Gn.html#ROOTID">Genesis</a>
<a href="Ex.html#ROOTID">Exodus</a>
</p>
<h2 class="bookLinks">NT</h2>
<p>
<a href="Mt.html#ROOTID">Matthew</a>
<a href="Ap.html#ROOTID">Revelation</a>
</p>

EOEXP;
      $this->assertEquals(
          $exp
        , $this->Replacer->formatBooksToc($Map, "Mt", "OT", "NT", "ROOTID")
        );
    }

     public function testFormatBooksTocNoRootID()
    {
      $Map = [
          "Gn" => "Genesis"
        ];
      $exp = <<<EOEXP
<h2 class="bookLinks">OT</h2>
<p>
<a href="Gn.html">Genesis</a>
</p>

EOEXP;
      $this->assertEquals(
          $exp
        , $this->Replacer->formatBooksToc($Map, "Mt", "OT", "NT", "")
        );
    }

    public function testGetChapterNumbers()
    {
      $text = <<<EOCH
<chapter sID="Gen.1"/>...<chapter eID="Gen.1"/>...
<p><a href="Gn.html">Genesis</a></p>
<chapter sID="Gen.2"/>...<chapter eID="Gen.2"/>...
<chapter sID="Gen.21"/>...<chapter eID="Gen.21"/>...
EOCH;
      $this->assertEquals(
          ["1", "2", "21"]
        , $this->Replacer->getChapterNumbers($text)
        );
    }

    public function testFormatChapterNumbersToc()
    {
      $this->assertEquals(
          "<p class=\"chapterLinks\">\n<a href=\"#1\">1</a><a href=\"#2\">2</a><a href=\"#50\">50</a></p>\n"
        , $this->Replacer->formatChapterNumbersToc(["1", "2", "50"])
        );
    }

    public function testGetTitle()
    {
      $this->assertEquals(
          "TITLE"
        , $this->Replacer->getTitle("<p class=\"chapterLinks\">\nxxx</p>\n<title x=\"y\"> TITLE </title >abc<title> other </title>")
        );
    }

    public function testSplitPrologAndText()
    {
      $this->assertEquals(
          ["prolog", "<chapter type=\"test\"/><p>body</p>"]
        , $this->Replacer->splitPrologAndText("<osis><header >...</header > prolog <chapter type=\"test\"/><p>body</p></osisText>")
        );
    }

    public function testConvertIntroductionP()
    {
      $text = <<<EOP
<p subType="x-introduction">p1.</p>
<p subType="x-introduction">p2.</p>
EOP;
      $exp = <<<EOEXP
<p class='e'>p1.</p>
<p class='e'>p2.</p>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->convertIntroductionP($text));
    }

    public function testConvertList()
    {
      $text = <<<EOLIST
<div type="outline" subType="x-introduction"><list><head>Head</head>
<item type="x-indent-1" subType="x-introduction">Item1
<reference>Ref1</reference></item>
<item type="x-indent-1" subType="x-introduction">Item2
<reference>Ref2</reference></item></list></div>
EOLIST;
      $exp = <<<EOEXP
<div type="outline" subType="x-introduction"><h2>Head</h2>
<ul>
<li>Item1
<span class='ref'>Ref1</span></li>
<li>Item2
<span class='ref'>Ref2</span></li>
</ul></div>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->convertList($text));
    }

    public function testDropMilestones()
    {
      $text = <<<EOTEXT
<milestone type="x-usfm-toc2" n="K"/>
<milestone type="x-usfm-toc1" n="K"/>
<title level="1" type="main">K</title><list>
EOTEXT;
      $exp = <<<EOEXP
<title level="1" type="main">K</title><list>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->dropMilestones($text));
    }

    public function testConvertOutlineDiv()
    {
      $text = <<<EOTEXT
    </p><div type="outline" subType="x-introduction"><list>
EOTEXT;
      $exp = <<<EOEXP
    </p>
<div class="outline">
<list>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->convertOutlineDiv($text));
    }

    public function testInsertTocs()
    {
      $text = <<<EOTEXT
<title type="runningHead">K</title><milestone type="x-usfm-toc3" n="Sem"/>
EOTEXT;
      $exp = <<<EOEXP
BOOKSTOC
<h1 id="bb" class='u0'>K</h1>
CHAPTERSTOC
<milestone type="x-usfm-toc3" n="Sem"/>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->insertTocs($text, "BOOKSTOC\n", "CHAPTERSTOC\n"));
    }

    public function testConvertChapterTags()
    {
      $text = <<<EOTEXT
<chapter osisID="Gen.1" sID="Gen.1"/>
<div type="section"><title>S</title>
<verse osisID="Gen.1.1" sID="Gen.1.1"/><p>A.<verse eID="Gen.1.1"/>
EOTEXT;
      $exp = <<<EOEXP
<h4>S</h4>
<p id="1" class='kap'><a href="#top">1</a></p>
<verse osisID="Gen.1.1" sID="Gen.1.1"/><p>A.<verse eID="Gen.1.1"/>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->convertChapterTags($text));
    }


    public function testMoveVerseStartIntoP()
    {
      $text = <<<EOTEXT
<verse eID="Gen.2.23"/>
<verse osisID="Gen.2.24" sID="Gen.2.24"/><p>C
EOTEXT;
      $exp = <<<EOEXP
<verse eID="Gen.2.23"/>
<p><verse osisID="Gen.2.24" sID="Gen.2.24"/>C
EOEXP;
      $this->assertEquals($exp, $this->Replacer->moveVerseStart($text));
    }

    public function testMoveVerseStartIntoL()
    {
      $text = <<<EOTEXT
<verse eID="Gen.3.14"/>
<verse osisID="Gen.3.15" sID="Gen.3.15"/><l level="1">C
EOTEXT;
      $exp = <<<EOEXP
<verse eID="Gen.3.14"/>
<l level="1"><verse osisID="Gen.3.15" sID="Gen.3.15"/>C
EOEXP;
      $this->assertEquals($exp, $this->Replacer->moveVerseStart($text));
    }

    public function testMoveNoteBehindP()
    {
      $text = <<<EOTEXT
<p>Na <note placement="foot"><reference type="annotateRef">3:17</reference> <catchWord>word:</catchWord> 16.</note>word</p>
EOTEXT;
      $exp = <<<EOEXP
<p>Na word</p>
<note placement="foot"><reference type="annotateRef">3:17</reference> <catchWord>word:</catchWord> 16.</note>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->moveNote($text));
    }

    public function testMoveNoteBehindLG()
    {
      $text = <<<EOTEXT
<l level="1">Na <note placement="foot"><reference type="annotateRef">3:17</reference> <catchWord>word:</catchWord> 16.</note>word</l>
<l level="1">L</l></lg>
EOTEXT;
      $exp = <<<EOEXP
<l level="1">Na word</l>
<l level="1">L</l></lg>
<note placement="foot"><reference type="annotateRef">3:17</reference> <catchWord>word:</catchWord> 16.</note>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->moveNote($text));
    }

    public function testConvertNote()
    {
      $text = <<<EOTEXT
<p>Na word</p>
<note placement="foot"><reference type="annotateRef">3:17</reference> <catchWord>word:</catchWord> 16.</note>
<l level="1">Na word</l>
<l level="1">L</l></lg>
<note placement="foot"><reference type="annotateRef">3:18</reference> <catchWord>word:</catchWord> 16.</note>
EOTEXT;
      $exp = <<<EOEXP
<p>Na word</p>
<div class="fn">3:17 <em>word:</em> 16.</div>
<l level="1">Na word</l>
<l level="1">L</l></lg>
<div class="fn">3:18 <em>word:</em> 16.</div>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->convertNote($text));
    }

    public function testConvertLg()
    {
      $text = <<<EOTEXT
</p><lg>
<l level="1">&lt;&lt;H</l>
<l level="1">Ṭ</l>
<verse eID="Gen.3.14"/>
<verse osisID="Gen.3.15" sID="Gen.3.15"/><l level="1">Cu</l></lg>
<p>
EOTEXT;
      $exp = <<<EOEXP
</p>
<p class="poet">&lt;&lt;H<br/>
Ṭ<br/>
Cu</p>
<p>
EOEXP;
      $this->assertEquals($exp, $this->Replacer->convertLg($text));
    }


    public function testDropEndTags()
    {
      $text = <<<EOTEXT
<verse eID="Gen.3.13"/>
<verse osisID="Gen.3.14" sID="Gen.3.14"/><p>C</p></div>
<verse eID="Gen.3.14"/>
EOTEXT;
      $exp = <<<EOEXP

<verse osisID="Gen.3.14" sID="Gen.3.14"/><p>C</p>

EOEXP;
      $this->assertEquals($exp, $this->Replacer->dropEndTags($text));
    }


}
