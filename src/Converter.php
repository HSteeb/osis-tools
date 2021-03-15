<?php
namespace HSteeb\osis2html;

use HSteeb\osis2html\Replacer;

class Converter
{
  private const DEFAULTCONFIG = [
    "chapterVerseSep" => ","
  , "rootID"          => "top"
  , "header"          => "<!DOCTYPE html>\n<html>\n<head>\n<title>%title;</title>\n<meta charset=\"utf-8\">\n</head>\n<body>\n"
  , "footer"          => "</body>\n</html>\n"
  , "bookNames"       => []
  , "bookNameMt"      => "Mt"
  , "otTitle"         => "Old Testament"
  , "ntTitle"         => "New Testament"
  ];

  private $Replacer;
  private $Config;

  /**
   * @param {Array} $Config options: see README.
   */
  function __construct($Config = [])
  {
      $this->Config = array_merge(self::DEFAULTCONFIG, $Config);
      $this->Replacer = new Replacer();
  }

  /**
   * @param {String} $infile path to source file
   * @param {String} $outfile path to result file
   * @param {Function} $prepareFunction if given, is called with the OSIS string as single parameter, must return the string.
   * - use-case: e.g. specific string replacements.
   */
  function run($infile, $outfile, $prepareFunction = null)
  {
    try {
      $osis = file_get_contents($infile);
      echo "Loaded $infile\n";

      if ($prepareFunction) {
        $osis = $prepareFunction($osis);
      }
      $html = $this->convert($osis);
      echo "Converted.\n";

      $bytesWritten = file_put_contents($outfile, $html);
      if ($bytesWritten === false) {
        throw new Exception("Failed to write " . $outfile);
      }
      echo "Saved $outfile.\n";

    }
    catch (Exception $E) {
      echo "run: " . $E->getMessage();
    }
  }

  /**
   * For unit test
   * @param {String} $osis the Bible book text to convert
   * @return {String} the resulting HTML text
   */
  function testConvert($osis)
  {
    return $this->convert($osis);
  }

  private function convert($osis)
  {
    if (isset($this->Config["replace"])) {
      $osis = $this->Replacer->replaceArray($osis, $this->Config["replace"]);
    }
    return
        $this->getHeader($osis)
      . $this->getBody($osis)
      . $this->getFooter($osis)
      ;
  }

  private function getHeader($osis)
  {
    $title = $this->Replacer->getTitle($osis);
    return preg_replace("/%title;/", $title, $this->Config["header"]);
  }

  private function getBody($osis)
  {
    $html = "";
    list ($prolog, $text) = $this->Replacer->splitPrologAndText($osis);
    if ($prolog && $text) {
      $html =
        $this->getProlog($prolog, $text)
      . $this->getChapters($text)
        ;
    }
    else {
      throw new \Exception("Failed to split book prolog and chapters text.");
    }
    return $html;
  }

  /**
   * @param {String} $prolog - text from behind </header> up to before first <chapter>
   */
  private function getProlog($prolog, $text)
  {
    $Cfg = $this->Config;

    $prolog = $this->Replacer->convertIntroductionP($prolog);
    $prolog = $this->Replacer->dropBookDiv($prolog);
    $prolog = $this->Replacer->convertMainTitle($prolog, $Cfg["rootID"]);
    $prolog = $this->Replacer->convertList($prolog);
    $prolog = $this->Replacer->dropMilestones($prolog);
    $prolog = $this->Replacer->convertOutlineDiv($prolog);

    # compute toc
    if ($Cfg["bookNames"]) {
      #echo "Creating books TOC\n";
      $booksToc = $this->Replacer->formatBooksToc($Cfg["bookNames"], $Cfg["bookNameMt"], $Cfg["otTitle"], $Cfg["ntTitle"], $Cfg["rootID"]);
    }
    else {
      $booksToc = "";
    }
    $ChapterNumbers = $this->Replacer->getChapterNumbers($text);
    $chaptersToc    = $this->Replacer->formatChapterNumbersToc($ChapterNumbers);
    $prolog = $this->Replacer->insertTocs($prolog, $booksToc, $chaptersToc);


    return $prolog;
  }

  private function getChapters($text)
  {
    # drop end tags first, e.g. for section - verse end tag - title
    $text = $this->Replacer->dropEndTags($text);

    $text = $this->Replacer->convertChapterTags($text, $this->Config["rootID"]);
    $text = $this->Replacer->moveVerseStart($text);
    $text = $this->Replacer->convertVerseStart($text, $this->Config["chapterVerseSep"]);


    $text = $this->Replacer->moveNoteBehindBlock($text);
    $text = $this->Replacer->moveNoteBehindH($text);
    $text = $this->Replacer->convertLg($text);

    return $text;
  }

  private function getFooter($osis)
  {
    return $this->Config["footer"];
  }

}
?>
