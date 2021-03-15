# osis-tools

OSIS Bible format conversion: OSIS to HTML converter.

## Usage

You need an installed PHP interpreter to run the script:

    $ php osis2html.php <input_osis.xml> <output_html.htm> [<config file.json>]

Without a config file, the script generates a rudimentary HTML HEAD with `<title>`, but no linked stylesheet (see `Converter.php`, `DEFAULTCONFIG`).

The generated HTML contains some `class` attributes for styling.

As of 2021-03, I used the script with the output of one converter
(refdoc, see References below), for two Bibles. So it may need
adaptations for other OSIS flavors, or other Bibles.

Example output:

- [Bible Thianghlim on bible2.net](https://bible2.net/bible/BibleThianghlim/Gn.html) (as of 2021-03)
- [Portuguese Bíblia Livre on bible2.net](https://bible2.net/bible/PortugueseBibliaLivre/Gn.html) (as of 2021-03)


## HTML Output Details

- front matter generated to `<ul>` lists and `<p>` paragraphs
- optionally: hyperlinks to other Bible book HTML files inserted above the main title (see below)
- hyperlinks to chapters (formatted in blocks of 10) inserted below the main title
- `majorSection` becomes `<h2>`, placed above chapter numbers
- `section` and `subSection` become `<h3>`/`<h4>`, placed below chapter numbers
- a chapter number becomes a `<p>` element
- a verse number becomes a `<span>` element with an `id` attribute like `1v3`, so you can use an URL like `Genesis.html#1v3`
- footnotes are moved below the `<p>` paragraph or sequence of headings in which they occur; with superscript indicator letters (like ^a^, ^b^)
- OSIS line groups (typically in Psalms) are represented as `<p class="cite">` paragraphs with lines separated by `<br/>`.

### Configuration

The optional **JSON configuration file** can have the following entries (top-level keys; for an example see the `sample/` folder):

- `chapterVerseSep`: separator "," in Genesis 1,2.
- `rootID`: (optional) id of HTML element, the link target for navigating to the start of the file, default `top`.
- `header`: HTML header including start of body, up to before the generated content. Default: rudimentary, `<title>` only.
    * `%title;` will be replaced by the title from the OSIS file.
    * In order to include your preferred stylesheet, insert a line like `<link rel=\"stylesheet\" type=\"text/css\" href=\"styles.css\">`.
- `footer`: HTML footer including end of body, after the generated content. Default: just closing tags.
- `replace`: (optional) array containing two sub-arrays with strings to replace element-wise in the input OSIS file, before any other processing;
    * XML special characters to be replaced must be written in the masked form like `&lt;&gt;&amp;`
    * e.g. `[ ["@", "&lt;&lt;", "&gt;&gt;"], ["", "\u{00ab}", "\u{00bb}"] ]`
    * replaces `@` by an empty string, XML masked angle brackets `<<`/`>>` by typographic guillemets (Unicode U+00AB/U+00BB)
    * Note: `\u{00ab}` in PHP (since PHP 7) = `\u00ab` in JSON.

The script supports generating **hyperlinks between the Bible books**, at the top of each HTML file. The following JSON entries are used:

- `bookNames`: array of filename => Bible book name (for table of contents), like `{"Gn": "Genesis", "Ex": "Exodus"}`.
- `bookNameMt`: filename of book Matthew, for separating OT and NT in the table of contents
- `otTitle`: heading of OT in table of contents
- `ntTitle`: heading of NT in table of contents

## Sample Files

Subfolder `sample/` contains

- OSIS files (few text taken from the free German Luther 1912),
- a sample configuration file and
- a sample CSS file.

Run the script on the sample OSIS files:

~~~
.../osis-tools> php osis2html.php sample/Gn.xml sample/Gn.html sample/config-en.json
.../osis-tools> php osis2html.php sample/Ex.xml sample/Ex.html sample/config-en.json
~~~

Alternative: simply run `make sample`.

Inspect `sample/Gn.html` in your browser (Chrome may refuse to show the local file, Firefox shows it).

## Contributing

Bug reports and pull requests are welcome on GitHub at https://github.com/hsteeb/osis-tools.

### Basics

- For development, you'll need additional tools, see Prerequisites below.
- The implementation mainly uses regular expressions (PHP `preg_replace`), grouped in several methods which are under unit test.
    * The regex patterns are defensive, to allow for white-space and attributes at places where XML allows them.
    * They assume well-formed XML input.
- The output aims to have meaningful line-breaks between HTML block tags.
- The development version of the script (`osis2htmlSrc.php`) is located in subfolder `src/`.
- It uses class files in namespaces, managed by `composer`.
- Unit tests are using `PHPunit`.

### XML features not especially handled

- XML comments
- CDATA sections
- Processing instructions

### OSIS features not especially handled

- References
- likely many more...

### Prerequisites

For development (I'm working under Ubuntu Linux), you need:

- composer (PHP package manager)
    * `composer.json` installs PHPunit for unit tests
- make, cat, grep (GNU file utils, for building the single-file version)

### Steps

Clone the repository and get the necessary composer packages:

```
git clone https://github.com/hsteeb/osis-tools.git
sudo apt-get install composer
composer install
```

Run the unit tests:

```
make phpunit
```

Build the single-file version `./osis2html.php`:

```
make build
```

### Integration test

The source code does not contain real Bible files, and therefore no integration test.

I'm using a simple integration test in a subfolder `itest/`, using a folder of
source OSIS files and a folder of reference HTML files:

~~~
itest/
  src/          OSIS xml files
  ref/          Generated HTML files
~~~

See the makefile targets `itest`, `idiff` and `isave`.

## License

GPL3. See the LICENSE file.

## References

Info:

- [OSIS page](https://www.crosswire.org/osis/) (at Cross Wire)
- [Converting SFM Bibles to OSIS](https://wiki.crosswire.org/Converting_SFM_Bibles_to_OSIS) (at Cross Wire)

Tools:

- [chrislit/usfm2osis](https://github.com/chrislit/usfm2osis) USFM to OSIS converter, by Chris Little (Python)
- [refdoc/Module-tools](https://github.com/refdoc/Module-tools) USFM to OSIS converter, by Peter von Kaehne, used by Cross Wire (Python)
- [osis2html5](https://github.com/tadd/osis2html5) by Tadashi Saito (Ruby)
