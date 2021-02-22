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

  static function run($infile, $outfile)
  {
    try {
      self::$Replacer = new Replacer();
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
    $title = self::$Replacer->getTitle($osis);
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
    list ($prolog, $text) = self::$Replacer->splitPrologAndText($osis);
    if ($prolog && $text) {
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
    $prolog = self::$Replacer->convertIntroductionP($prolog);
    $prolog = self::$Replacer->convertList($prolog);
    $prolog = self::$Replacer->dropMilestones($prolog);
    $prolog = self::$Replacer->convertOutlineDiv($prolog);

    # compute toc
    $booksToc    = self::$Replacer->formatBooksToc(self::enBookNames(), "Mt", "Old Testament", "New Testament", "bb");
    $ChapterNumbers = self::$Replacer->getChapterNumbers($text);
    $chaptersToc = self::$Replacer->formatChapterNumbersToc($ChapterNumbers);
    $prolog = self::$Replacer->insertTocs($prolog, $booksToc, $chaptersToc);

    return $prolog;
  }

  private static function getChapters($text)
  {

    $text = self::$Replacer->convertChapterTags($text);
    $text = self::$Replacer->moveVerseStart($text);

    $text = self::$Replacer->convertVerseStart($text);

    $text = self::$Replacer->dropEndTags($text);

    $text = self::$Replacer->moveNote($text);
    $text = self::$Replacer->convertNote($text);
    $text = self::$Replacer->convertLg($text);

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
      , "Ap" => " "
    ];
  }
}
?>
