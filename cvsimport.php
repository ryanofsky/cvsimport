<?

require_once('cvsimport.inc');

if ($_SERVER['argc'] != 2)
{
  print("Usage: php cvsimport.php config.inc\n");
  exit();
}

$file = $_SERVER['argv'][1];

if (!file_exists($file))
{
  die("File '$file' does not exist");
}
else
{
  include $file;
}

$nl =& new RevisionList($Revisions, $OutDir);
if ($nl->warned)
{
  print("\n\nWarnings have been issued. Please correct any problems and try again.\n\n");
  exit();
}
else
{
  $BLANKFILE = tempnam("","");
  $nl->scan();
  $nl->save();
  unlink($BLANKFILE);
}

?>