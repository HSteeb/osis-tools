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
      $this->assertEquals("abc", $this->Replacer->getVerseStart("abc"));

      $this->assertEquals(
          "<span id=\"14v1\" class=\"vers\">1</span> "
        , $this->Replacer->getVerseStart('<verse osisID="Gen.14.10" sID="Gen.14.1"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\" class=\"vers\">10</span> "
        , $this->Replacer->getVerseStart('<verse osisID="Gen.14.10" sID="Gen.14.10"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\"/><span id=\"14v11\"/><span class=\"vers\">10–11</span> "
        , $this->Replacer->getVerseStart('<verse sID="Gen.14.10 Gen.14.11"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\"/><span id=\"14v11\"/><span id=\"14v12\"/><span class=\"vers\">10–12</span> "
        , $this->Replacer->getVerseStart('<verse sID="Gen.14.10 Gen.14.11 Gen.14.12"/>')
        );
      $this->assertEquals(
          "<span id=\"14v10\"/><span id=\"14v11\"/><span id=\"15v1\"/><span class=\"vers\">14,10–15,1</span> "
         , $this->Replacer->getVerseStart('<verse sID="Gen.14.10 Gen.14.11 Gen.15.1"/>')
         );
    }

    public function testGetVerseStartExceptions1()
    {
       $this->expectExceptionMessage('Cannot handle sID');
       $this->Replacer->getVerseStart('<verse sID="xy"/>');
    }

    public function testGetVerseStartExceptions2()
    {
       $this->expectExceptionMessage('Cannot handle sID');
       $this->Replacer->getVerseStart('<verse sID="Gen.14.10 xy"/>');
    }

    public function testGetVerseStartExceptions3()
    {
       $this->expectExceptionMessage('Descending chapter numbers 14/13 in');
       $this->Replacer->getVerseStart('<verse sID="Gen.14.10 Gen.13.11"/>');
    }

    public function testGetVerseStartExceptions4()
    {
       $this->expectExceptionMessage('Missing sID value in');
       $this->Replacer->getVerseStart('<verse sID=""/>');
    }

    public function testGetChaptersToc()
    {
      $this->assertEquals(
          "<p class=\"chapterLinks\">\n<a href=\"#1\">1</a><a href=\"#3\">3</a></p>\n"
        , $this->Replacer->getChaptersToc('<chapter sID="Gen.1"/>...<chapter sID="Gen.3"/>...')
        );
    }

}
