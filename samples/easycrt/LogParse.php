<?

/*

Quick and dirty code to parse a special text file format used for specifying
log messages.

File consists of a number of sections separated by blank lines.
Each section begins with a revision number like 1.2 1.12 1.05 which must
be on its own line with no spaces or other characters. After the revision
number there will be 0 or more log entries. The first line of a log entry
holds a list of files and regular expressions and subsequent lines contain
the log message. Log entries are separated by blank lines. Blank lines are
escaped when they are doubled up, so if a log message is meant to contain a 
blank line, it can be written with two blank lines in a row.

The list of files and regular expressions at the beginning of each log entry
before the log message is separated by spaces. Filenames containing spaces
can be surrounded with double quotes ("). Regular expressions must begin and
end with forward slashes (/) and may contain spaces. Backslash (/) is
interpreted as an escape character, and can be used to escape quotes, spaces
other backslashes, and any other character except newline.

Example file:

1.1

annie.txt ben.jpg cory.c
initial checkin
these files will be very useful

dumb/dave.pas
Initial checkin, I have no idea why I added this.

1.2

dumb/dave.pas
Delteated

"henrietta hatfield.bas" /^abc[0-9]+$/ /^src\\\/.*\\.c$/
here are some great new files


i use them all the time

*/

function LogParse($filename, &$logs, &$tags)
{
  $wasLine = true;
  $files = null;
  $rev = "";
  $msg = null;
  $tags = null;
  
  $fp = fopen($filename, "rt");
  
  for($i = 1;; ++$i)
  {
    $line = fgets($fp);
    $isLine = $line == "\n";
    $eof = strlen($line) == 0;
    
    if (($wasLine && !$isLine) || $eof)
    {
      if (is_array($files) || isset($msg))
      {
        if (!isset($rev))
          die("Error at " . __FILE__ . ":" . __LINE__ . " missing revision number");
  
        if (!isset($msg))
          die("Error at " . __FILE__ . ":" . __LINE__ . " missing log message");
          
        if (!is_array($files))
          die("Error at " . __FILE__ . ":" . __LINE__ . " missing file list");        
  
        foreach ($files as $f)
          $logs[$rev][normcase($f)] = $msg;
  
        //print("Read length " . strlen($msg) . " log message for the following files in revision revision $rev:\n");
        //foreach($files as $f) print("  $f\n");
  
        $msg = $files = null;
      }
      
      if ($eof) break;
      
      if (preg_match('/^((?:\\d+\\.\\d+)(?:\\.\\d+\\.\\d+)*)\\s*(.*)\\s*$/', $line, $matches))
      {
        $rev = $matches[1];
        $tags[$rev] = strlen($matches[2]) ? 
          preg_split("/\\s+/", $matches[2]) : array();
        $wasLine = true;
      }
      else
      {
        $files = FileList(substr($line, 0, -1));
        $wasLine = false;
        $msg = "";
      }
    }
    else
    {
      if (isset($msg))
      {
        $wasLine = $isLine && !$wasLine;  
        if (!$wasLine) $msg .= $line;
      }
      else
      {
        assert($isLine);
      }
    }
  }
}

define('LOG_SPACE', 0);
define('LOG_PLAIN', 1);
define('LOG_QUOTE', 2);
define('LOG_REGEX', 3);

function FileList($search)
{
  $terms = array();
  $n = strlen($search);
  $term = "";
  $mode = LOG_SPACE;
  $escaped = false;
  
  for ($i = 0; $i < $n; ++$i)
  {
    $c = $search[$i];
    if ($escaped)
    {
      $term .= $c;
      $escaped = false;
    }
    else if ($c == '\\')
    {
      $escaped = true;
    }
    else if ($c == "/")
    {
      $term .= $c;
      if ($mode == LOG_SPACE) 
        $mode = LOG_REGEX;
      else if ($mode == LOG_REGEX)
        $mode = LOG_SPACE;        
    }
    else if ($c == '"')
    {
      if ($mode == LOG_SPACE) 
        $mode = LOG_QUOTE;
      else if ($mode == LOG_QUOTE)
        $mode = LOG_SPACE;
      else
        $term .= $c;        
    }
    else if ($c == ' ' && ($mode == LOG_PLAIN || $mode == LOG_SPACE))
    {
      if (strlen($term))
      {
        $terms[] = $term;
        $term = "";
      }
    }
    else
    {
      if ($mode == LOG_SPACE) $mode = LOG_PLAIN;
      $term .= $c;
    }
  }
  if (strlen($term))
    $terms[] = $term;

  return $terms;
}

?>