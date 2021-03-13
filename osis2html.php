<?php


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
$prepareFunction = null;
//$prepareFunction = function($osis)
//{
//  # fill in: process $osis...
//  return $osis;
//};
$Converter->run($infile, $outfile, $prepareFunction);

?>
<?php


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
<?php



class Replacer
{
  private const Q       = "['\"]";
  private const EQ      = "\s*=\s*";
  private const TAGREST = "[^>]*>";
  private const CHAPTERSTART_1NUMBER = "<chapter\b[^>]*?\bsID" . self::EQ . self::Q . "\s*\w+\.(\d+)\s*" . self::Q . "[^>]*>";
  private const NOTEELEMENT_1CONTENTS = "<note\b" . self::TAGREST . "(.*?)</note\s*>";

  /**
   * @param {String} $osis the XML text. Note that XML special characters in the text are masked: e.g. "&lt;"
   * @param {Array|null} $ReplaceArray array of two arrays of equal size, to be replaced element-wise (PHP: str_replace)
   * e.g. [ ["@", "&lt;&lt;", "&gt;&gt;"], ["", "\u{00ab}", "\u{00bb}"] ]
   * replaces "@" by empty string, XML masked angle brackets "<<"/">>" by typographic guillemets (Unicode U+00AB/U+00BB)
   */
  function replaceArray($osis, $ReplaceArray)
  {
    if ($ReplaceArray) {
      if (!is_array($ReplaceArray) || count($ReplaceArray) != 2
          || !is_array($ReplaceArray[0]) || !is_array($ReplaceArray[1])
          || count($ReplaceArray[0]) != count($ReplaceArray[1])
          ) {
        throw new \Exception("ReplaceArray must be null or array containing two arrays of equal size");
      }
      $osis = str_replace($ReplaceArray[0], $ReplaceArray[1], $osis);
    }
    return $osis;
  }


  function getChapterNumbers($text)
  {
    #echo "a: getChaptersToc($text)\n";

    $ok = preg_match_all("@" . self::CHAPTERSTART_1NUMBER . "@su", $text, $Matches, PREG_PATTERN_ORDER);
    return ($ok === false) ? null : $Matches[1];
  }

  function formatChapterNumbersToc($ChapterNumbers)
  {
    if (!$ChapterNumbers) {
      return "";
    }
    $res = "<p class=\"chapterLinks\">\n";
    #echo "c: getChaptersToc Matches = " . print_r($Matches, true) . "\n";
    $i = 0;
    foreach ($ChapterNumbers as $chapterNumber) {
      #echo "d: ChapterMatch = " . print_r($ChapterMatch, true) . "\n";
      $res .= "<a href=\"#$chapterNumber\">" . $chapterNumber . "</a>";
      if (++$i == 10) {
        $res .= "<br/>\n"; # allow for break
        $i = 0;
      }
    }
    $res .= "</p>\n";
    return $res;
  }


  function getTitle($text)
  {
    return preg_match("@<title" . self::TAGREST . "\s*(.*?)\s*</title\s*>@su", $text, $Matches) ? $Matches[1] : "";
  }

  function splitPrologAndText($text)
  {
    return preg_match("@</header\s*>\s*(.*?)\s*(<chapter.*?)</osisText\s*>@su", $text, $Matches) ?
      [$Matches[1], $Matches[2]] : ["", ""];
  }

  function convertIntroductionP($text)
  {
    # p
    $text = preg_replace("@<p\b" . self::TAGREST . "@su", "<p class='intro'>", $text);
    $text = preg_replace("@</p\s*>@su", "</p>", $text);
    return $text;
  }

  function dropBookDiv($text)
  {
    # drop div.type=book
    return preg_replace(
        "@\s*<div[^>]*?type" . self::EQ . self::Q . "\s*book\s*" . self::Q . self::TAGREST . "\s*@su"
      , ""
      , $text
      );
  }


  function convertMainTitle($text, $rootID)
  {
    # title.runningHead
    # Result is used in insertTocs()!
    return preg_replace(
        "@\s*<title\b[^>]*runningHead" . self::TAGREST . "(.*?)</title\s*>\s*@su"
      , "\n<h2 id=\"$rootID\" class=\"main\">$1</h2>\n"
      , $text
      );
  }


  function dropMilestones($text)
  {
    # drop tags: milestone
    return preg_replace("@</?(milestone)" . self::TAGREST . "\s*@su", "", $text);
  }

  function convertOutlineDiv($text)
  {
    # change div.type=outline to div.class=outline
    return preg_replace(
        "@\s*<div[^>]*?type" . self::EQ . self::Q . "\s*outline\s*" . self::Q . self::TAGREST . "\s*@su"
      , "\n<div class=\"outline\">\n"
      , $text
      );
  }

  function convertList($text)
  {
    # change list + head to <h.> + list
    $text = preg_replace("@(<list" . self::TAGREST . ")\s*<head" . self::TAGREST . "\s*(.*?)\s*</head\s*>@us", "<h2>$2</h2>\n$1", $text);

    # Wrap item.x-indent=[234] sequences into inner ul/li lists
    $eItemBefore_1 = "\s*(</item\s*>)\s*";
    $sItem = "<item\b[^>]*";
    $eItem = ".*?</item\s*>\s*";
    # - item.x-indent=[234]
    $text = preg_replace("@" . $eItemBefore_1. "((?:" . $sItem . "x-indent-[234]" . self::TAGREST . $eItem . ")+)@su", "\n<ul>$2</ul>\n$1\n", $text);
    # - item.x-indent=[34]
    $text = preg_replace("@" . $eItemBefore_1. "((?:" . $sItem . "x-indent-[34]"  . self::TAGREST . $eItem . ")+)@su", "\n<ul>$2</ul>\n$1\n", $text);
    # - item.x-indent=4
    $text = preg_replace("@" . $eItemBefore_1. "((?:" . $sItem . "x-indent-4"     . self::TAGREST . $eItem . ")+)@su", "\n<ul>\n$2</ul>\n$1\n", $text);

    # replace tags
    $text = preg_replace("@\s*<(/?)list\b" . self::TAGREST . "\s*@su", "\n<$1ul>\n", $text);
    $text = preg_replace("@\s*<item\b"  . self::TAGREST . "\s*@su", "\n<li>", $text);
    $text = preg_replace("@\s*</item\b" . self::TAGREST . "\s*@su", "</li>\n", $text); # drops "\n" in "</ul>\n</li>" (1)


    # reference
    $text = preg_replace("@<reference\b" . self::TAGREST . "@su", "<span class='ref'>", $text);
    $text = preg_replace("@</reference\s*>@su", "</span>", $text);

    # drop remaining titles
    $text = preg_replace("@</?(title)" . self::TAGREST . ".*?</\\1\s*>@su", "", $text);

    # cleanup
    $text = preg_replace("@(</ul>)(</li>)@su", "$1\n$2", $text); # repairs (1) above
    $text = preg_replace("@(</li>)(</ul>)@su", "$1\n$2", $text);

    return $text;
  }


  /**
   * Format table of Bible books with hyperlinks into 2x: h2.bookLinks + p
   *
   * @param {Array} $BooknamesMap
   * - keys: book file names, e.g. "Gn" or "GEN"
   * - values: book names to show, e.g. "Matthew"
   * @param {String} $bookMtKey the book file name of book Matthew (triggers new paragraph)
   * @param {String} $otTitle title of the Old Testament hyperlinks
   * @param {String} $ntTitle title of the New Testament hyperlinks
   * @param {String} $rootID HTML id for the link target in the HTML files, id omitted if empty
   */
  function formatBooksToc($BooknamesMap, $bookMtKey, $otTitle, $ntTitle, $rootID)
  {
    $res = "<h2 class=\"bookLinks\">$otTitle</h2>\n<p>\n";
    foreach ($BooknamesMap as $key => $bookName) {
      if ($key == $bookMtKey) {
        $res .= "</p>\n<h2 class=\"bookLinks\">$ntTitle</h2>\n<p>\n";
      }
      $res .= "<a href=\"$key.html" . ($rootID ? "#$rootID" : "") . "\">" . $bookName . "</a>\n";
    }
    $res .= "</p>\n";
    return $res;
  }

  /**
   * Assumption: run after convertMainTitle, using its output.
   */
  function insertTocs($text, $booksToc, $chaptersToc)
  {
    # toc + h1....main, matching `<h1 id="top" class="main">...</h1>`
    return preg_replace(
        "@\s*(<h2\b[^>]*main" . self::TAGREST . ".*?</h2\s*>)\s*@su"
      , "$booksToc$1\n$chaptersToc"
      , $text
      );
  }


  function convertChapterTags($text, $rootID)
  {
    $divMajorSectionStart = "<div\b[^>]*?\btype" . self::EQ . self::Q . "\s*majorSection\s*" . self::Q . self::TAGREST;
    $divLowerSectionStart = "<div\b[^>]*?\btype" . self::EQ . self::Q . "\s*(?:section|subSection)\s*" . self::Q . self::TAGREST;
    $divSectionStart      = "<div\b[^>]*?\btype" . self::EQ . self::Q . "\s*section\s*"      . self::Q . self::TAGREST;
    $divSubSectionStart   = "<div\b[^>]*?\btype" . self::EQ . self::Q . "\s*subSection\s*"   . self::Q . self::TAGREST;
    $titleElement_1title  = "<title\b" . self::TAGREST . "(.*?)</title\b\s*>";
    $titleTypePsalm_1title= "<title\b[^>]*?\btype" . self::EQ . self::Q . "\s*psalm\s*" . self::Q . self::TAGREST . "(.*?)</title\b\s*>";
    $eDivs                = "(?:\s*</div\s*>)*";

    # Assume this hierarchy:
    # - div.type=majorSection Psalm books, multiple chapters
    # - chapter
    # - div.type=section within chapter
    # - div.type=subSection

    # put chapter.sID behind div.type=majorSection, move 0..n </div> to front
    $text = preg_replace("@\s*(" . self::CHAPTERSTART_1NUMBER . ")($eDivs)\s*($divMajorSectionStart\s*$titleElement_1title)@su", "$3\n$4\n$1", $text);

    # div.majorSection to h2
    $text = preg_replace("@\s*$divMajorSectionStart\s*$titleElement_1title@su", "\n<h2>$1</h2>", $text);
    # div.section to h3
    $text = preg_replace("@\s*$divSectionStart\s*$titleElement_1title@su", "\n<h3>$1</h3>", $text);
    # div.subsection to h4
    $text = preg_replace("@\s*$divSubSectionStart\s*$titleElement_1title@su", "\n<h4>$1</h4>", $text);

    # div.type=psalm to h4.psalm
    $text = preg_replace("@\s*$titleTypePsalm_1title\s*@su", "\n<h4 class=\"psalm\">$1</h4>", $text);

    # change chapter.sID to p.chapter, set chapter id #i
    $text = preg_replace("@\s*" . self::CHAPTERSTART_1NUMBER . "@su", "\n<p id=\"$1\" class='chapter'><a href=\"#$rootID\">$1</a></p>", $text);

    # drop chapter.eID
    $text = preg_replace("@<chapter\b[^>]*?\beID" . self::TAGREST . "@su", "", $text);

    return $text;
  }

  function moveVerseStart($text)
  {
    $verseStart                 = "<verse\b[^>]*?\bsID\b" . self::TAGREST;
    $containerStart             = "<(?:p|lg\b[^>]*?>\s*<l|l)\b" . self::TAGREST;

    # move verse.sID into following <p> or <l>
    $text = preg_replace("@($verseStart)\s*($containerStart)@su", "$2$1", $text);

    return $text;
  }

  /**
   * @param {String} $text OSIS text
   * @param {String} $chapterVerseSep separator between chapter and verse number, typically "," or ":"
   * @return {String} the input $text, with <verse...> start tags replaced, using the info in the sID attribute:
   * - for a single verse number, e.g. "Gen.14.10", returns a span like `<span id="14v10" class="verse">10</span>`
   * - for a multiple verse numbers, e.g. "Gen.14.10 Gen.14.11", returns multiple spans like
   *     * <span id="14v10"/><span id="14v11/><span class="verse">10–11</span>`
   *     * <span id="14v10"/><span id="15v1/><span class="verse">14,10–15,1</span>`
   */
  function convertVerseStart($text, $chapterVerseSep = ",")
  {
    $verseClass = "verse";
    $attrVal_1chapter_2verse    = "\w+\.(\d+)\.(\d+)";
    $verseStart_1sIDValue       = "<verse\b[^>]*?\bsID" . self::EQ . self::Q . "\s*(.*?)\s*" . self::Q . self::TAGREST;
    #echo "\n-------------\n0: text=$text\n";

    # analyze one <verse...> start tag
    $text = preg_replace_callback("@$verseStart_1sIDValue@su",
      function($Matches) use ($attrVal_1chapter_2verse, $chapterVerseSep, $verseClass) {
        $tag = $Matches[0];
        #echo "\n1: tag=$tag\n";
        $sID = $Matches[1];                  # sID attribute value contents
        $Arr = preg_split("/\s+/", $sID);    # array of "Gen.14.10"-like IDs
        #echo "\n2: count(Arr)=".count($Arr)."\n" . print_r($Arr, true);
        if (count($Arr) < 1 || !$Arr[0]) {
          // preg_split on empty $sID gives $Arr = [""] argh
          throw new \Exception("Missing sID value in $tag");
        }
        #echo "\n3: sID=|$Arr[0]|\n";
        # analyze the first (maybe only) "Gen.14.10-like verse number in sID
        if (1 !== preg_match("@$attrVal_1chapter_2verse@", $Arr[0], $IDMatches)) {
          throw new \Exception("Cannot handle sID " . $Arr[0] . " in $tag");
        }
        #echo "\n4: IDMatches= " . print_r($IDMatches, true) . "\n";
        # remember chapter/verse of the verse number
        $chapter = $IDMatches[1];
        $verse   = $IDMatches[2];
        if (count($Arr) == 1) {
          # single verse number in sID, e.g. "Gen.14.10", set verse id #1v2
          return "<span id=\"${chapter}v$verse\" class=\"$verseClass\">$verse</span> ";
        }
        else {
          # multiple verse numbers in sID, e.g. "Gen.14.10 Gen.14.11"
          # add the id of the first verse number, set verse id #1v2
          $res = "<span id=\"${chapter}v$verse\"/>";
          $followChapter = "";
          $followVerse   = "";
          # loop over the second..last verse number
          for ($i = 1; $i < count($Arr); ++$i) {
            #echo "\n5: i=$i Arr[$i]= $Arr[$i]\n";
            # analyze the current second..last "Gen.14.10-like verse number in sID
            if (1 !== preg_match("@$attrVal_1chapter_2verse@", $Arr[$i], $FollowIDMatches)) {
              throw new \Exception("Cannot handle sID " . $Arr[$i] . " in $tag");
            }
            $followChapter = $FollowIDMatches[1];
            $followVerse   = $FollowIDMatches[2];
            # append span with id attribute for the current verse number, set verse id #1v2
            $res .= "<span id=\"${followChapter}v$followVerse\"/>";
          }
          if ($chapter == $followChapter) {
            # first and last verse number are in same chapter: add span with range of verses
            $res .= "<span class=\"$verseClass\">${verse}–$followVerse</span> ";
          }
          else {
            if ($chapter > $followChapter) {
              # chapter numbers of first and last verse number are descending
              throw new \Exception("Descending chapter numbers $chapter/$followChapter in sID of $tag");
            }
            # first and last verse number in different chapters: add span with range of chapter+verse pairs
            $res .= "<span class=\"$verseClass\">$chapter" . $chapterVerseSep . "${verse}–$followChapter" . $chapterVerseSep . "$followVerse</span> ";
          }
          return $res;
        }
      }
      , $text
      );
    return $text;
  }


  function moveNoteBehindBlock($text)
  {
    $text = preg_replace_callback(
        "@(<(lg|p)\b" . self::TAGREST . ")(.*?)(</\\2\s*>)\s*@su"
      , function($Matches)
        {
          # process one <p> or <lg> as "container"
          list($stag, $content, $etag) = [$Matches[1], $Matches[3], $Matches[4]];
          $Notes = [];
          $currentIndicator = "a";
          $content = preg_replace_callback(
              "@" . self::NOTEELEMENT_1CONTENTS . "@su"
            , function($NoteMatches) use (&$Notes, &$currentIndicator)
              {
                # process one <note> (also below)
                $Notes[] = [$NoteMatches[0], $currentIndicator]; # remember it
                return "<sup class=\"fnref\">" . $currentIndicator++ . "</sup>"; # replace by indicator
              }
            , $content
            );
          # replace container content by content without notes, plus all notes added behind it
          return $stag . $content . $etag . "\n" . $this->formatNotes($Notes);
        }
      , $text
      );
    return $text;
  }


  function moveNoteBehindH($text)
  {
    $text = preg_replace_callback(
        "@((?:\s*<(h\d)\b" . self::TAGREST . ".*?</\\2\s*>)+)\s*@su" # match a sequence of headings
      , function($Matches)
        {
          $headings = $Matches[1];
          $Notes = [];
          $currentIndicator = "a";
          $headings = preg_replace_callback(
              "@" . self::NOTEELEMENT_1CONTENTS . "@su"
            , function($NoteMatches) use (&$Notes, &$currentIndicator)
              {
                # process one <note> (also above)
                $Notes[] = [$NoteMatches[0], $currentIndicator]; # remember it
                return "<sup class=\"fnref\">" . $currentIndicator++ . "</sup>"; # replace by indicator
              }
            , $headings
            );
          # replace container content by content without notes, plus all notes added behind it
          return $headings . "\n" . $this->formatNotes($Notes, /*includeRef*/ false);
        }
      , $text
      );
    return $text;
  }

  function formatNotes($Notes, $includeRef = true)
  {
    if (!$Notes) {
      return "";
    }
    $Result = [];
    foreach ($Notes as $Note) {
      list($text, $indicator) = $Note;
      $Result[] = $this->convert1Note($text, $indicator, $includeRef);
    }
    return implode("\n", $Result) . "\n";
  }

  /**
   * @param {String} $text the OSIS <note> element
   * @param {String} $indicator the character denoting the running footnote in the text
   * @param {Bool} $includeRef whether the footnote shall include the OSIS <reference> text (default true)
   * - use-case: omitted in a footnote below heading lines
   */
  function convert1Note($text, $indicator, $includeRef = true)
  {
    $referenceElement_1contents = "<reference\b" . self::TAGREST . "(.*?)</reference\b\s*>";
    $catchWordElement_1contents = "<catchWord\b" . self::TAGREST . "(.*?)</catchWord\b\s*>";

    # change note to div
    $text = preg_replace_callback("@" . self::NOTEELEMENT_1CONTENTS . "@su",
      function($Matches) use ($referenceElement_1contents, $catchWordElement_1contents, $indicator, $includeRef) {
        $content = $Matches[1];
        $content = preg_replace("@$referenceElement_1contents\s*(?:$catchWordElement_1contents)?(.*)@su"
        , "<p class=\"fn\">"
          . ($includeRef ? "<span class=\"ref\">$1</span>" : "")
          . "<sup class=\"ind\">$indicator</sup>"
          . ("$2" ? " <span class=\"word\">$2</span>" : "")
          . "<span class=\"text\">$3</span></p>"
        , $content);
        return $content;
      }
      , $text);
    return $text;
  }


  function convertLg($text)
  {
    $lgStart = "<lg\b" . self::TAGREST;
    $lStart  = "<l\b"  . self::TAGREST;
    $lEnd    = "</l\s*>";
    $lgEnd   = "</lg\s*>";

    # change lg + l to p with br
    $text = preg_replace_callback("@$lgStart\s*(.*?)$lgEnd@su",
      function($Matches) use ($lStart, $lEnd) {
        $content = $Matches[1];
        $ok = preg_match_all("@\s*$lStart\s*(.*?)$lEnd@su", $content, $ContentMatches,  PREG_PATTERN_ORDER);
        return $ok === false ? "" : "\n<p class=\"cite\">" . implode("<br/>\n", $ContentMatches[1]) . "</p>";
      }
      , $text);

    return $text;
  }

  function dropEndTags($text)
  {
    $verseEnd = "<verse\b[^>]*?\beID" . self::TAGREST;
    $divEnd   = "</div\b" . self::TAGREST;

    # drop verse.eID
    $text = preg_replace("@$verseEnd@su", "", $text);
    # drop </div>
    $text = preg_replace("@$divEnd@", "", $text);

    return $text;
  }


}
?>
