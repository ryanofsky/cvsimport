<?

require_once('cvsimport.inc');

$options = array(array('test', 't', 1, false));
$args = strip_options($_SERVER['argv'], $options, $opt_values);

if (count($args) != 2)
{
  print("Usage: php cvsimport.php [OPTION] config.inc\n\n"
      . "  -t, --test=DIRECTORY   after creating repository, attempt to\n"
      . "                         check out different versions of code to\n" 
      . "                         the specified directory and see if it\n"
      . "                         matches the imported code.\n");
  exit();
}

$file = $args[1];

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

$BLANKFILE = tempnam("","");
$nl->scan();
$nl->save();
unlink($BLANKFILE);
if (function_exists('CleanUp')) CleanUp();

if (($test = $opt_values['test']) !== false)
{
  if (!is_dir($test)) die("Testing directory '$test' does not exist.");
  $nl->checkout($test);
}

?>