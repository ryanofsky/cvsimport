<?

$Win32 = true;

$Revisions = array
(
  '1.1'     => new Revision(false, false, 'M:/russ/000cvsimport/1.01'),
  '1.2'     => new Revision(false, false, 'M:/russ/000cvsimport/1.02'),
  '1.3'     => new Revision(false, false, 'M:/russ/000cvsimport/1.03'),
  '1.3.1.1' => new Revision(false, "lostcities", 'M:/russ/000cvsimport/1.03.1.1'),
  '1.4'     => new Revision(false, false, 'M:/russ/000cvsimport/1.04'),
  '1.5'     => new Revision(false, false, 'M:/russ/000cvsimport/1.05'),
  '1.6'     => new Revision(false, false, 'M:/russ/000cvsimport/1.06'),
  '1.7'     => new Revision(false, false, 'M:/russ/000cvsimport/1.07'),
  '1.8'     => new Revision(false, false, 'M:/russ/000cvsimport/1.08'),
  '1.9'     => new Revision(false, false, 'M:/russ/000cvsimport/1.09'),
  '1.10'    => new Revision(false, false, 'M:/russ/000cvsimport/1.10'),
  '1.11'    => new Revision(false, false, 'M:/russ/000cvsimport/1.11'),
  '1.12'    => new Revision(false, false, 'M:/russ/000cvsimport/1.12'),
  '1.13'    => new Revision(false, false, 'M:/russ/000cvsimport/1.13'),
  '1.14'    => new Revision(false, false, 'M:/russ/000cvsimport/1.14'),
  '1.15'    => new Revision(false, false, 'M:/russ/000cvsimport/1.15')
);

$OutDir = "L:/server/shares/cvsroot/cvsimport";

$extensions = array
(
  'inc' => MODE_TEXT,
  'php' => MODE_TEXT,
  'bat' => MODE_TEXT,
  'txt' => MODE_TEXT,
  'zip' => MODE_BINARY,
);

include('LogParse.php');

$log = $tags = array();
LogParse("M:/russ/000cvsimport/history.txt", $log, $tags);

foreach($tags as $rev => $t)
  $Revisions[$rev]->tags = $t;

foreach(array_keys($Revisions) as $rev)
  $Revisions[$rev]->tags[] = "t" . str_replace(".", "_", $rev);
  
function SetFileInfo(&$file)
{
  global $extensions;

  $ext = strtolower(FileExtension($file->path));
  if (isset($extensions[$ext]))
    $file->mode = $extensions[$ext];
  else
    die("Can't match extension '$ext' at " . __FILE__ . ":" . __LINE__); 
}

$logUsed = array();

function SetFileRevisionInfo(&$fileRev, $key)
{
  global $log, $author, $logUsed;
  
  $filename = $fileRev->file->path;
  
  $k = FileEntry($log[$key], $filename);
  if (isset($k))
  {
    $logUsed[$key][$k] = true;
    $fileRev->log = $log[$key][$k];
  }
  
  $fileRev->author = "russ";
}

function CleanUp()
{
  global $log, $logUsed;
  foreach($log as $rev => $rl)
  foreach($rl as $re => $l)
  {
    if (empty($logUsed[$rev][$re]))
      print("Unused log entry for $re revision $rev\n");
  }
}

?>