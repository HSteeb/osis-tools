<?php

namespace HSteeb\osis2html;


class Replacer
{
  private $sep          = ","; # chapter to verse number separator, TODO pass parameter
  private const Q       = "['\"]";
  private const EQ      = "\s*=\s*";
  private const TAGREST = "[^>]*>";
  private const CHAPTERSTART_1NUMBER = "<chapter\b[^>]*?\bsID" . self::EQ . self::Q . "\s*\w+\.(\d+)\s*" . self::Q . "[^>]*>";
  private const NOTEELEMENT_1CONTENTS = "<note\b" . self::TAGREST . "(.*?)</note\b\s*>";

  /**
   * @param {String} $text OSIS text
   * @return {String} the input $text, with <verse...> start tags replaced, using the info in the sID attribute:
   * - for a single verse number, e.g. "Gen.14.10", returns a span like `<span id="14v10" class="vers">10</span>`
   * - for a multiple verse numbers, e.g. "Gen.14.10 Gen.14.11", returns multiple spans like
   *     * <span id="14v10"/><span id="14v11/><span class="vers">10–11</span>`
   *     * <span id="14v10"/><span id="15v1/><span class="vers">14,10–15,1</span>`
   */
  function convertVerseStart($text)
  {
    $attrVal_1chapter_2verse    = "\w+\.(\d+)\.(\d+)";
    $verseStart_1sIDValue       = "<verse\b[^>]*?\bsID" . self::EQ . self::Q . "\s*(.*?)\s*" . self::Q . self::TAGREST;
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
            $res .= "<span class=\"vers\">$chapter" . $this->sep . "${verse}–$followChapter" . $this->sep . "$followVerse</span> ";
          }
          return $res;
        }
      }
      , $text
      );
    return $text;
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
      [$Matches[1], $Matches[2]] : [];
  }

  function convertIntroductionP($text)
  {
    # p
    $text = preg_replace("@<p\b" . self::TAGREST . "@su", "<p class='e'>", $text);
    $text = preg_replace("@</p\s*>@su", "</p>", $text);
    return $text;
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

    # replace tags
    $text = preg_replace("@<(/?)list\b" . self::TAGREST . "@su", "<$1ul>", $text);
    $text = preg_replace("@<(/?)item\b" . self::TAGREST . "@su", "<$1li>", $text);


    # reference
    $text = preg_replace("@<reference\b" . self::TAGREST . "@su", "<span class='ref'>", $text);
    $text = preg_replace("@</reference\s*>@su", "</span>", $text);

    # drop remaining titles
    $text = preg_replace("@</?(title)" . self::TAGREST . ".*?</\\1\s*>@su", "", $text);

    # cleanup
    $text = preg_replace("@(</li>)(</ul>)@su", "$1\n$2", $text);

    return $text;
  }

  function insertTocs($text, $booksToc, $chaptersToc)
  {
    # toc + title.runningHead (after processing p), set target #bb for book links (cf. NeUe: below book toc)
    return preg_replace(
        "@<(title)\b[^>]*runningHead" . self::TAGREST . "(.*?)</\\1\s*>@su"
      , $booksToc
        . "<h1 id=\"bb\" class='u0'>$2</h1>\n"
        . $chaptersToc
      , $text
      );
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


  function convertChapterTags($text)
  {
    $divSectionStart = "<div\b[^>]*?\btype" . self::EQ . self::Q . "\s*section\s*" . self::Q . self::TAGREST;
    $titleElement_1title        = "<title\b" . self::TAGREST . "(.*?)</title\b\s*>";

    # swap chapter.sID <=> div.type=section
    $text = preg_replace("@(" . self::CHAPTERSTART_1NUMBER . ")\s*($divSectionStart\s*$titleElement_1title)@su", "$3\n$1", $text);

    # div.section to h4
    $text = preg_replace("@$divSectionStart\s*$titleElement_1title@su", "<h4>$1</h4>", $text);

    # change chapter.sID to p.kap, set chapter id #i
    $text = preg_replace("@" . self::CHAPTERSTART_1NUMBER . "@su", "<p id=\"$1\" class='kap'><a href=\"#top\">$1</a></p>", $text);

    # drop chapter.eID
    $text = preg_replace("@<chapter\b[^>]*?\beID" . self::TAGREST . "@su", "", $text);

    return $text;
  }

  function moveVerseStart($text)
  {
    $verseStart                 = "<verse\b[^>]*?\bsID\b" . self::TAGREST;
    $containerStart             = "<(?:p|l)\b" . self::TAGREST;

    # move verse.sID into following <p> or <l>
    $text = preg_replace("@($verseStart)\s*($containerStart)@su", "$2$1", $text);

    return $text;
  }

  function moveNote($text)
  {
    $noteContainerEnd           = "</(?:lg|p)\s*>";

    # move note behind next </lg> or </p>
    $text = preg_replace("@(" . self::NOTEELEMENT_1CONTENTS . ")(.*?)($noteContainerEnd)@su", "$3$4\n$1", $text);

    return $text;
  }


  function convertNote($text)
  {
    $referenceElement_1contents = "<reference\b" . self::TAGREST . "(.*?)</reference\b\s*>";
    $catchWordElement_1contents = "<catchWord\b" . self::TAGREST . "(.*?)</catchWord\b\s*>";

    # change note to div
    $text = preg_replace_callback("@" . self::NOTEELEMENT_1CONTENTS . "@su",
      function($Matches) use ($referenceElement_1contents, $catchWordElement_1contents) {
        $content = $Matches[1];
        $content = preg_replace("@$referenceElement_1contents\s*(?:$catchWordElement_1contents)?(.*)@su"
        , "<div class=\"fn\">$1 " . ("$2" ? "<em>$2</em>" : "") . "$3</div>"
        , $content);
        return $content;
      }
      , $text);
    return $text;
  }


  function convertLg($text)
  {
    $lgStart = "<lg\b" . self::TAGREST;
    $lStart  = "<l\b" . self::TAGREST;
    $lEnd    = "</l\s*>";
    $lgEnd   = "</lg\s*>";

    # change lg + l to p with br
    $text = preg_replace_callback("@$lgStart\s*(.*?)$lgEnd@su",
      function($Matches) use ($lStart, $lEnd) {
        $content = $Matches[1];
        $ok = preg_match_all("@\s*$lStart\s*(.*?)$lEnd@su", $content, $ContentMatches,  PREG_PATTERN_ORDER);
        return $ok === false ? "" : "\n<p class=\"poet\">" . implode("<br/>\n", $ContentMatches[1]) . "</p>";
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
    # drop /div
    $text = preg_replace("@$divEnd@", "", $text);

    return $text;
  }


}
?>
