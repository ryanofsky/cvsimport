<?

$Win32 = true;

$Revisions = array
(
  '1.01' => new Revision('window', false, 'M:/russ/000Stick/1.01 window'),
  '1.02' => new Revision(false, false, 'M:/russ/000Stick/1.02'),
  '1.03' => new Revision(false, false, 'M:/russ/000Stick/1.03'),
  '1.04' => new Revision(false, false, 'M:/russ/000Stick/1.04'),
  '1.05' => new Revision(false, false, 'M:/russ/000Stick/1.05'),
  '1.06' => new Revision(false, false, 'M:/russ/000Stick/1.06'),
  '1.07' => new Revision(false, false, 'M:/russ/000Stick/1.07'),
  '1.08' => new Revision(false, false, 'M:/russ/000Stick/1.08'),
  '1.09' => new Revision('version_1_0', false, 'M:/russ/000Stick/1.09 version_1_0'),
  '1.10' => new Revision(false, false, 'M:/russ/000Stick/1.10'),
  '1.11' => new Revision('scifair_98', false, 'M:/russ/000Stick/1.11 scifair_98'),
  '1.12' => new Revision(false, false, 'M:/russ/000Stick/1.12'),
  '1.13' => new Revision('version_1_1', false, 'M:/russ/000Stick/1.13 version_1_1'),
  '1.14' => new Revision(false, false, 'M:/russ/000Stick/1.14'),
  '1.15' => new Revision('scifair_99', false, 'M:/russ/000Stick/1.15 scifair_99'),
  '1.16' => new Revision('abandoned', false, 'M:/russ/000Stick/1.16 abandoned')
);

$OutDir = "L:/server/shares/cvsroot/stick";

$extensions = array
(
  'res' => MODE_BINARY,  
  'dat' => MODE_BINARY,  
  'hfx' => MODE_BINARY,
  'bmp' => MODE_BINARY,
  'pas' => MODE_TEXT
);

//foreach(array_keys($Revisions) as $rev)
//  $Revisions[$rev]->tags[] = "t" . str_replace(".", "_", $rev);

$author = "russ";
$log = array();

$log['1.01']['thewind.pas'] = 'displays an empty window';

$log['1.02']['thewind.pas'] = 'became stick.pas';

$log['1.02']['stick.pas'] = '- basic architecture: fighter object and event handling stubs
- drawing functions based on EasyCRT, but take DC as parameter';

$log['1.03']['stick.pas'] = '- added a little more functionality to fighter objects';  

$log['1.04']['stick.pas'] = '- worked out geometry needed to draw stick figure
- added the game loop';  

$log['1.05']['stick.pas'] = '- added variable delay to animation loop so game speed is
  not affected by frame rate (under normal circumstances)';  

$log['1.06']['stick.pas'] = '- brought in EasyCRT\'s bitmap routines';  

$log['1.06']['blank.bmp'] = '- used to initialize the animation buffer';  

$log['1.07']['stick.bmp'] = '- logo for title screen';  

$log['1.07']['stick.pas'] = '- slight reorganization: constants moved to top
- new resource management procedure used in graphics routines.
  all handles for a given DC are stored together in a record
- added animation routines for stick fighter\'s moves and changes
  to existing rendering routines and data structures
- added intro screen and animation';  

$log['1.08']['stick.pas'] = '- added damage meter';  

$log['1.09']['stick.pas'] = '- added endgame detection
  - added game load / save
  - added high score display and persistence
  - added game menus';  
  
$log['1.09']['scores.dat'] = '- high scores file';

$log['1.09']['stick.res'] = '- icon resource';
  
$log['1.10']['stick.pas'] = '- set paused := true in pause procedure';  

$log['1.10']['scores.dat'] = '- high scores file';

$log['1.11']['stick.pas'] = '- based on stick.pas version_1_0
- drawing functions have been moved into easygdi.pas (version_3_2)
- drawing functions are all now prefixed with an \'a\' for advanced
  to distinguish them from EasyCRT functions
- stickfighter icon is now loaded from resource id 999 instead of 1';  

$log['1.11']['easygdi.pas'] = '- easygdi library (version_3_2)';

$log['1.11']['scores.dat'] = '- high scores file';

$log['1.11']['stick.res'] = '- changed icon\'s resource number';

$log['1.12']['stick.pas'] = '- now autodetects executable path';  

$log['1.13']['stick.pas'] = '- deleted some commented out code
- this version of stickfighter was distributed with 4.x versions of easycrt even though it runs on older easygdi code';

$log['1.13']['scores.dat'] = '- high scores file';

$log['1.14'] = '- split up into units
- rewrite of many other areas';

$log['1.15'] = '- work for 1999 science fair';

$log['1.16'] = '- post-science fair work';

function SetFileInfo(&$file)
{
  global $extensions;
  $ext = strtolower(FileExtension($file->path));
  if (isset($extensions[$ext]))
    $file->mode = $extensions[$ext];
  else
    die("Can't match extension '$ext' at " . __FILE__ . ":" . __LINE__); 
}

function SetFileRevisionInfo(&$fileRev, $key)
{
  global $log, $author;
  
  $filename = $fileRev->file->path;
 
  $l = FileRevisionEntry($log, $key, $filename);
  $a = FileRevisionEntry($author, $key, $filename);
  
  if (isset($l)) $fileRev->log = $l;
  if (isset($a)) $fileRev->author = $a;
}

?>