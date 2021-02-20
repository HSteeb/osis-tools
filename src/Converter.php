<?php
namespace HSteeb\osis2html;

use HSteeb\osis2html\Replacer;

class Converter
{
  private static $SEP = ","; # chapter to verse number separator
  private static $q;
  private static $eq;
  private static $chapterStart_1number;
  private static $Replacer;

  function init()
  {
    self::$q                    = "['\"]";
    self::$eq                   = "\s*=\s*";
    self::$chapterStart_1number = "<chapter\b[^>]*?\bsID" . self::$eq . self::$q . "\s*\w+\.(\d+)\s*" . self::$q . "[^>]*>";
    self::$Replacer = new Replacer();
  }
  static function run($infile, $outfile)
  {
    try {
      $osis = file_get_contents($infile);
      echo "Loaded $infile\n";
      $html = self::convert($osis);
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

  private static function convert($osis)
  {
    $osis = preg_replace("/@/", "", $osis); # BibleThianghlim contains `Himi @barli`, `type="psalm">@Hla Hruaitu`... (index entries?)
    return
        self::getHeader($osis)
      . self::getBody($osis)
      . self::getFooter($osis)
      ;
  }

  private static function getHeader($osis)
  {
# 2021-02-17 HS TODO CSS PATH
    $cssPath = "styles.css"; # importer for bootstrap + style.css
#    $cssPath = "/css/style.css";
#    $cssPath = "/steeb/bible2.net/impl/css/style.css";
    $title = "";
    if (preg_match("@<title[^>]*>\s*(.*?)\s*</title>@su", $osis, $Matches)) {
      $title = $Matches[1];
    }
    return <<<EOHEADER
<html>
<head>
 <title>$title</title>
 <link rel="stylesheet" type="text/css" href="$cssPath">
 <meta charset="utf-8">
</head>
<body class="bible">
<div id="content" class="container">
<div class="row">
<div class="col-md-12">
EOHEADER;
  }

  private static function getBody($osis)
  {
    $html = "";
    if (preg_match("@</header\s*>\s*(.*?)(<chapter.*?)</osisText\s*>@su", $osis, $Matches)) {
      $prolog = $Matches[1];
      $text   = $Matches[2];
      $html =
        self::getProlog($prolog, $text)
      . self::getChapters($text)
        ;
    }
    else {
      throw new Exception("getBody: no match.");
    }
    return $html;
  }

  /**
   * @param {String} $prolog - text from behind </header> up to before first <chapter>
   */
  private static function getProlog($prolog, $text)
  {
    $q                          = "['\"]";
    $eq                         = "\s*=\s*";

    # change list + head to <h.> + list
    $prolog = preg_replace("@(<list[^>]*>)\s*<head[^>]*>\s*(.*?)\s*</head\s*>@us", "<h2>$2</h2>\n$1", $prolog);

    # change div.type=outline to div.class=outline
    $prolog = preg_replace("@\s*<div[^>]*?type${eq}${q}\s*outline\s*${q}[^>]*>\s*@su", "\n<div class=\"outline\">\n", $prolog);

    # drop tags: milestone
    $prolog = preg_replace("@</?(milestone)[^>]*>@su", "", $prolog);

    # replace tags
    $prolog = preg_replace("@<(/?)list\b[^>]*>@su", "<$1ul>", $prolog);
    $prolog = preg_replace("@<(/?)item\b[^>]*>@su", "<$1li>", $prolog);

    # p
    $prolog = preg_replace("@<p\b[^>]*>@su", "<p class='e'>", $prolog);
    $prolog = preg_replace("@</p\s*>@su", "</p>", $prolog);

    # compute toc
    $booksToc = self::getBooksToc();
    $chaptersToc = self::$Replacer->getChaptersToc($text);
    # toc + title.runningHead (after processing p), set target #bb for book links (cf. NeUe: below book toc)
    $prolog = preg_replace(
        "@<(title)\b[^>]*runningHead[^>]*>(.*?)</\\1\s*>@su"
      , $booksToc
        . "<p id=\"bb\" class='u0'>$2</p>\n"
        . $chaptersToc
      , $prolog
      );


    # reference
    $prolog = preg_replace("@<reference\b[^>]*>@su", "<span class='ref'>", $prolog);
    $prolog = preg_replace("@</reference\s*>@su", "</span>", $prolog);

    # drop remaining titles
    $prolog = preg_replace("@</?(title)[^>]*>.*?</\\1\s*>@su", "", $prolog);

    # cleanup
    $prolog = preg_replace("@(</li>)(</ul>)@su", "$1\n$2", $prolog);

    return $prolog;
  }

  private static function getBooksToc()
  {
    $BOOKNAMES = self::enBookNames();

    $res = "<p class=\"u3 bookLinks\">Old Testament</p>\n<p>\n";
    foreach ($BOOKNAMES as $normBookName => $bookName) {
      if ($normBookName == "Mt") {
        $res = "</p>\n<p class=\"u3\">New Testament</p>\n<p>\n";
      }
      $res .= "<a href=\"$normBookName.html#bb\">" . $bookName . "</a>\n";
    }
    $res .= "</p>\n";
    return $res;
  }

  private static function getChapters($text)
  {
    $q                          = self::$q;
    $eq                         = self::$eq;
    $chapterEnd                 = "<chapter\b[^>]*?\beID[^>]*>";
    $verseStart                 = "<verse\b[^>]*?\bsID\s*[^>]*>";
    $verseEnd                   = "<verse\b[^>]*?\beID[^>]*>";
    $sID_1chapter_2verse        = "\w+\.(\d+)\.(\d+)\s*${q}";
    $divSectionStart            = "<div\b[^>]*?\btype${eq}${q}\s*section\s*${q}[^>]*>";
    $divEnd                     = "</div\b[^>]*>";
    $containerStart             = "<(?:p|l)\b[^>]*>";
    $titleElement_1title        = "<title\b[^>]*>(.*?)</title\b\s*>";
    $lgStart                    = "<lg\b[^>]*>";
    $lStart                     = "<l\b[^>]*>";
    $lEnd                       = "</l\b\s*>";
    $lgEnd                      = "</lg\b\s*>";
    $noteElement_1contents      = "<note\b[^>]*>(.*?)</note\b\s*>";
    $noteContainerEnd           = "</(?:lg|p)\s*>";
    $referenceElement_1contents = "<reference\b[^>]*>(.*?)</reference\b\s*>";
    $catchWordElement_1contents = "<catchWord\b[^>]*>(.*?)</catchWord\b\s*>";

    # swap chapter.sID <=> div.type=section
    $text = preg_replace("@(" . self::$chapterStart_1number . ")\s*($divSectionStart\s*$titleElement_1title)@su", "$3\n$1", $text);

    # div.section
    $text = preg_replace("@$divSectionStart\s*$titleElement_1title@su", "<h4>$1</h4>", $text);
    # drop /div
    $text = preg_replace("@$divEnd@", "", $text);

    # change chapter.sID to p.kap, set chapter id #i
    $text = preg_replace("@" . self::$chapterStart_1number . "@su", "<p id=\"$1\" class='kap'><a href=\"#top\">$1</a></p>", $text);

    # move verse.sID into following <p> or <l>
    $text = preg_replace("@($verseStart)\s*($containerStart)@su", "$2$1", $text);

    # drop chapter.eID
    $text = preg_replace("@$chapterEnd@su", "", $text);

    # change verse.sID to span.vers
    #$text = preg_replace("@$verseStart_1chapter_2verse@su", "<span class='vers'>$2</span> ", $text);

    $text = self::$Replacer->getVerseStart($text);

    # drop verse.eID
    $text = preg_replace("@$verseEnd@su", "", $text);

    # move note behind next </lg> or </p>
    $text = preg_replace("@($noteElement_1contents)(.*?)($noteContainerEnd)@su", "$3$4\n$1", $text);

    # change note to div
    $text = preg_replace_callback("@$noteElement_1contents@su",
      function($Matches) use ($referenceElement_1contents, $catchWordElement_1contents) {
        $content = $Matches[1];
        $content = preg_replace("@$referenceElement_1contents\s*(?:$catchWordElement_1contents)?(.*)@su"
        , "<div class=\"fn\">$1 " . ("$2" ? "<em>$2</em>" : "") . "$3</div>\n"
        , $content);
        return $content;
      }
      , $text);

    # change lg + l to p with br
    $text = preg_replace_callback("@$lgStart\s*(.*?)$lgEnd@su",
      function($Matches) use ($lStart, $lEnd) {
        $content = $Matches[1];
        $ok = preg_match_all("@$lStart\s*(.*?)$lEnd@su", $content, $Matches,  PREG_PATTERN_ORDER);
        return $ok === false ? "" : "<p class=\"poet\">" . implode("<br/>", $Matches[1]) . "</p>";
      }
      , $text);

    return $text;
  }

  private static function getFooter($osis)
  {
    return <<<EOFOOTER
</div>
</div>
</div>
</body>
</html>

EOFOOTER;
  }

  private static function enBookNames()
  {
    return [
        "Gn" => "Genesis"
      , "Ex" => "Exodus"
      , "Lv" => "Leviticus"
      , "Nu" => "Numbers"
      , "Dt" => "Deuteronomy"
      , "Jos" => "Joshua"
      , "Jdc" => "Judges"
      , "Rth" => "Ruth"
      , "1Sm" => "1 Samuel"
      , "2Sm" => "2 Samuel"
      , "1Rg" => "1 Kings"
      , "2Rg" => "2 Kings"
      , "1Chr" => "1 Chronicles"
      , "2Chr" => "2 Chronicles"
      , "Esr" => "Ezra"
      , "Neh" => "Nehemiah"
      , "Esth" => "Esther"
      , "Job" => "Job"
      , "Ps" => "Psalm"
      , "Prv" => "Proverbs"
      , "Eccl" => "Ecclesiastes"
      , "Ct" => "Song of Solomon"
      , "Is" => "Isaiah"
      , "Jr" => "Jeremiah"
      , "Thr" => "Lamentations"
      , "Ez" => "Ezekiel"
      , "Dn" => "Daniel"
      , "Hos" => "Hosea"
      , "Joel" => "Joel"
      , "Am" => "Amos"
      , "Ob" => "Obadiah"
      , "Jon" => "Jonah"
      , "Mch" => "Micah"
      , "Nah" => "Nahum"
      , "Hab" => "Habakkuk"
      , "Zph" => "Zephaniah"
      , "Hgg" => "Haggai"
      , "Zch" => "Zechariah"
      , "Ml" => "Malachi"
      , "Mt" => "Matthew"
      , "Mc" => "Mark"
      , "L" => "Luke"
      , "J" => "John"
      , "Act" => "Acts"
      , "R" => "Romans"
      , "1K" => "1 Corinthians"
      , "2K" => "2 Corinthians"
      , "G" => "Galatians"
      , "E" => "Ephesians"
      , "Ph" => "Philippians"
      , "Kol" => "Colossians"
      , "1Th" => "1 Thessalonians"
      , "2Th" => "2 Thessalonians"
      , "1T" => "1 Timothy"
      , "2T" => "2 Timothy"
      , "Tt" => "Titus"
      , "Phm" => "Philemon"
      , "H" => "Hebrews"
      , "Jc" => "James"
      , "1P" => "1 Peter"
      , "2P" => "2 Peter"
      , "1J" => "1 John"
      , "2J" => "2 John"
      , "3J" => "3 John"
      , "Jd" => "Jude"
      , "Ap" => "Revelation"
    ];
  }
}
?>
