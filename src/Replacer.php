<?php

namespace HSteeb\osis2html;


class Replacer
{
  private $SEP = ","; # chapter to verse number separator
  private $q;
  private $eq;
  private $chapterStart_1number;

  function __construct()
  {
    $this->q                    = "['\"]";
    $this->eq                   = "\s*=\s*";
    $this->chapterStart_1number = "<chapter\b[^>]*?\bsID" . $this->eq . $this->q . "\s*\w+\.(\d+)\s*" . $this->q . "[^>]*>";
  }

  /**
   * @param {String} $text OSIS text
   * @return {String} the input $text, with <verse...> start tags replaced, using the info in the sID attribute:
   * - for a single verse number, e.g. "Gen.14.10", returns a span like `<span id="14v10" class="vers">10</span>`
   * - for a multiple verse numbers, e.g. "Gen.14.10 Gen.14.11", returns multiple spans like
   *     * <span id="14v10"/><span id="14v11/><span class="vers">10–11</span>`
   *     * <span id="14v10"/><span id="15v1/><span class="vers">14,10–15,1</span>`
   */
  function getVerseStart($text)
  {
    $q                          = "['\"]";
    $eq                         = "\s*=\s*";
    $attrVal_1chapter_2verse    = "\w+\.(\d+)\.(\d+)";
    $verseStart_1sIDValue       = "<verse\b[^>]*?\bsID${eq}${q}\s*(.*?)\s*${q}[^>]*>";
    #echo "\n-------------\n0: text=$text\n";

    # analyze one <verse...> start tag
    $text = preg_replace_callback("@$verseStart_1sIDValue@su",
      function($Matches) use ($attrVal_1chapter_2verse) {
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
          return "<span id=\"${chapter}v$verse\" class=\"vers\">$verse</span> ";
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
            $res .= "<span class=\"vers\">${verse}–$followVerse</span> ";
          }
          else {
            if ($chapter > $followChapter) {
              # chapter numbers of first and last verse number are descending
              throw new \Exception("Descending chapter numbers $chapter/$followChapter in sID of $tag");
            }
            # first and last verse number in different chapters: add span with range of chapter+verse pairs
            $res .= "<span class=\"vers\">$chapter" . $this->SEP . "${verse}–$followChapter" . $this->SEP . "$followVerse</span> ";
          }
          return $res;
        }
      }
      , $text
      );
    return $text;
  }

  function getChaptersToc($text)
  {
    #echo "a: getChaptersToc($text)\n";
    $ok = preg_match_all("@" . $this->chapterStart_1number . "@su", $text, $Matches, PREG_PATTERN_ORDER);
    if ($ok === false) {
      #echo "b: getChaptersToc ok = false\n";
      return "";
    }
    $res = "<p class=\"chapterLinks\">\n";
    #echo "c: getChaptersToc Matches = " . print_r($Matches, true) . "\n";
    $i = 0;
    foreach ($Matches[1] as $chapterNumber) {
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



}
?>
