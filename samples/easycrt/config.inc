<?

$Win32 = true;

$Revisions = array
(
  '1.1'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.01 wincrt'),
  '1.2'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.02 russcrt'),
  '1.3'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.03'),
  '1.4'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.04 version_1_02'),
  '1.5'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.05'),
  '1.6'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.06 version_2_0'),
  '1.7'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.07 version_2_01'),
  '1.7.2.1'  => new Revision(false, "garett", 'm:/russ/000EasyCrt/1.07.2.1 garrett_1'),
  '1.7.2.2'  => new Revision(false, false, 'm:/russ/000EasyCrt/1.07.2.2 garrett_2'),
  '1.8'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.08 version_3_0'),
  '1.9'      => new Revision(false, false, 'm:/russ/000EasyCrt/1.09 intermediate'),
  '1.10'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.10 version_3_2'),
  '1.10.2.1' => new Revision(false, "scifair_98", 'm:/russ/000EasyCrt/1.10.2.1 scifair_98'),
  '1.11'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.11 version_3_3'),
  '1.12'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.12'),
  '1.13'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.13'),
  '1.14'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.14'),
  '1.15'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.15'),
  '1.16'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.16 version_4_0'),
  '1.17'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.17 version_4_01'),
  '1.18'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.18 version_4_02'),
  '1.19'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.19'),
  '1.20'     => new Revision(false, false, 'm:/russ/000EasyCrt/1.20'),
  '1.20.2.1' => new Revision(false, "scifair_99", 'm:/russ/000EasyCrt/1.20.2.1 scifair_99')
);

$OutDir = "L:/server/shares/cvsroot/easycrt";

$extensions = array
(
  'pas' => MODE_TEXT,
  'txt' => MODE_TEXT,
  'bak' => MODE_TEXT,
  'bat' => MODE_TEXT,
  'cfg' => MODE_TEXT,
  'ini' => MODE_TEXT,
  'cdf' => MODE_TEXT,
  'fgl' => MODE_TEXT,
  'htm' => MODE_TEXT,
  'lid' => MODE_TEXT,
  'mda' => MODE_TEXT,
  'int' => MODE_TEXT,
  'ipr' => MODE_TEXT,
  'pnt' => MODE_TEXT,
  'rge' => MODE_TEXT,
  'rpt' => MODE_TEXT,
  'shl' => MODE_TEXT,
  'rul' => MODE_TEXT,
  'log' => MODE_TEXT,
  'scp' => MODE_TEXT,
  'tag' => MODE_TEXT,
  'tdw' => MODE_TEXT,
  'tpw' => MODE_TEXT,
  'tsb' => MODE_TEXT,
  'html' => MODE_TEXT,
  'rw' => MODE_TEXT,
  '' => MODE_BINARY,
  'ino' => MODE_BINARY,
  'dat' => MODE_BINARY,
  'bmp' => MODE_BINARY,
  'hfx' => MODE_BINARY,
  'res' => MODE_BINARY,
  'hfx' => MODE_BINARY,
  'fdf' => MODE_BINARY,
  'mng' => MODE_BINARY,
  'ins' => MODE_BINARY,
  'jpg' => MODE_BINARY,
  'jbf' => MODE_BINARY,
  'obs' => MODE_BINARY,
  'exe' => MODE_BINARY,
  'dll' => MODE_BINARY,
  'sel' => MODE_BINARY,
  'bas' => MODE_BINARY,
  'bin' => MODE_BINARY,
  'dbg' => MODE_BINARY,
  'dta' => MODE_BINARY,
  'gam' => MODE_BINARY,
  'gif' => MODE_BINARY,
  'ico' => MODE_BINARY,
  'xls' => MODE_BINARY,
  'cab' => MODE_BINARY,
  'doc' => MODE_BINARY,
  'tpu' => MODE_BINARY,
  'ttf' => MODE_BINARY,
  'wav' => MODE_BINARY,
  'psp' => MODE_BINARY,  
  'dsk' => MODE_BINARY,
  'ex_' => MODE_BINARY
);

include('LogParse.php');

$log = $tags = array();
LogParse("M:/russ/000EasyCrt/history.txt", $log, $tags);

foreach($tags as $rev => $t)
  $Revisions[$rev]->tags = $t;

//foreach(array_keys($Revisions) as $rev)
//  $Revisions[$rev]->tags[] = "t" . str_replace(".", "_", $rev);
  
function SetFileInfo(&$file)
{
  global $extensions;

  if (!strcasecmp($file->path, 'samples/stick/scores.bak') || !strcasecmp($file->path, 'samples/stick/lab/old/980123stick/scores.bak'))
  {
    $file->mode = MODE_BINARY;
    return;
  }

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
  
  if ($key == "1.7.2.1" || $key == "1.7.2.2")
    $fileRev->author = "garrett";
  else
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