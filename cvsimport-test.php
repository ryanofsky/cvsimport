<?

ini_set('implicit_flush', 1);
ob_implicit_flush(1);
require_once('cvsimport.inc');

$RUN_TESTS = array('test1', 'test2', 'test3', 'test4', 'test5');

function test1($revout, $cvsout, $coout, $expected)
{
  // simple test which fails when you use the buggy diff.exe
  // that thinks files with identical sizes and timestamps are
  // identical
  return TestCreate(array
  (
    array('1.1', false, false),
    array('1.2', false, false),
    array('1.3', false, 'abc'),
    array('1.4', false, 'def'),
    array('1.5', false, 'ghi'),
    array('1.6', false, 'ghi'),
    array('1.7', false, 'ghi')
  ), $revout, $cvsout, $coout, $expected);
}

function test2($revout, $cvsout, $coout, $expected)
{
  // tests out isset(previous) branch of setrevisions()
  
  return TestCreate(array
  (
    array('1.1', false, 'n'),
    array('1.1.3.1', 'noexisting', false),
    array('1.1.3.2', false, false),
    array('1.1.6.1', 'delteated_first', false),
    array('1.1.6.2', false, 'hhf'),
    array('1.1.6.3', false, false),
    array('1.1.6.4', false, 'hhf'),
    array('1.2', false, 'n'),
    array('1.3', false, false)
  ), $revout, $cvsout, $coout, $expected);
}

function test3($revout, $cvsout, $coout, $expected)
{
  // test out creation on branches
  
  return TestCreate(array
  (
    array('1.1', false, false),
    array('1.1.3.1', 'b1', false),
    array('1.1.3.1.4.1', 'b11', false),
    array('1.1.3.1.4.2', false, 'first'),
    array('1.1.3.2', false, 'second'),
    array('1.2', false, false),
    array('1.2.3.1', 'b2', 'third')
  ), $revout, $cvsout, $coout, $expected);
}


function test4($revout, $cvsout, $coout, $expected)
{
  // test out pruned and zero branches
  
  return TestCreate(array
  (
    array('1.1', false, false),
    array('1.1.3.1', 'noexisting', 'fw'),
    array('1.2', false, false),
    array('1.3', false, 'abc'),
    array('1.3.6.1', 'delteated', false),
    array('1.3.6.1.2.1', 'delteated2', false),
    array('1.3.7.1', 'delteated-a', false),
    array('1.3.7.1.2.1', 'delteated2-a', false),
    array('1.3.7.1.2.1.2.1', 'delteated3-a', 'oh no'),
    array('1.3.8.1', 'fold1', 'abc'),
    array('1.3.8.1.2.1', 'fold2', 'abc'),
    array('1.3.9.1', 'fold1-a', 'abc'),
    array('1.3.9.1.2.1', 'fold2-a', 'abc'),
    array('1.3.9.1.2.1.2.1', 'fold3', 'nu')
  ), $revout, $cvsout, $coout, $expected);
}

function test5($revout, $cvsout, $coout, $expected)
{
  // test out pruned and zero branches changed after delays
  
  return TestCreate(array
  (
    array('1.1', false, false),
    array('1.1.3.1', 'noexisting', false),
    array('1.1.3.2', false, 'fw'),
    array('1.2', false, false),
    array('1.3', false, 'abc'),
    array('1.3.6.1', 'delteated', false),
    array('1.3.6.1.2.1', 'delteated2', false),
    array('1.3.7.1', 'delteated-a', false),
    array('1.3.7.1.2.1', 'delteated2-a', false),
    array('1.3.7.1.2.1.2.1', 'delteated3-a', false),
    array('1.3.7.1.2.1.2.2', false, 'changed'),
    array('1.3.8.1', 'fold1', 'abc'),
    array('1.3.8.1.2.1', 'fold2', 'abc'),
    array('1.3.8.1.2.1.2.1', 'fold3', 'abc'),
    array('1.3.9.1', 'fold1-a', 'abc'),
    array('1.3.9.1.2.1', 'fold2-a', 'abc'),
    array('1.3.9.1.2.1.2.1', 'fold3-a', 'abc'),
    array('1.3.9.1.2.1.2.2', false, 'changed')
  ), $revout, $cvsout, $coout, $expected);
}

function SetFileInfo(&$file)
{
  $file->mode = MODE_SAFETEXT;
}

function SetFileRevisionInfo(&$fileRev, $key)
{
  $fileRev->author = 'russ';
  $fileRev->log = "whoa";
  $fileRev->tagDeleted = true;
}

function TestCreate($revisions, $revout, $cvsout, $coout, $expected)
{
  $rs = array();
  foreach($revisions as $r)
  {
    list($number, $branch, $contents) = $r;
    $t = str_replace(".", "_", $number);
    $rtag = "t$t";
    $btag = $branch ? $branch : false;
    $dir = "$revout/$number";
    @mkdir($dir);
    $file = "$dir/file";
    if ($contents === false)
      @unlink($file);
    else
    {
      $f = fopen($file, "wt");
      if (!$f) die("failed to write '$file' at " . __FILE__ . ':' . __LINE__);
      fwrite($f, $contents);
      fclose($f);
      touch($file, 1052400094);
    }
    $rs[$number] =& new Revision($rtag, $btag, $dir);
  }
  $nl =& new RevisionList($rs, $cvsout);
  if ($nl->warned) die("fix warnings!");
  global $BLANKFILE;
  $BLANKFILE = tempnam("","");
  $nl->scan();
  $nl->save();
  unlink($BLANKFILE);
  
  $f1 = $nl->checkout($coout);
  $f2 = compare_dirs($cvsout, $expected);
  
  print("$cvsout, $expected\n");
  
  if ($f2)
    print("The generated cvs repository differed from the expected one.\n");
  else  
    print("The generated cvs repository matched the expected one.\n");
  
  return $f1 || $f2;
  
}

function forward_path($winpath)
{
  return str_replace('\\', '/', $winpath);
}

$basedir = forward_path(dirname(__FILE__));
$outdir = "$basedir/cvsimport-tmp";
$force = false;

$argv = $_SERVER['argv'];
if (count($argv) == 2 && ($argv[1] == '-f' || $argv[1] == '--force'))
  $force = true; 
else if (count($argv) != 1)
{
  print("Usage php $argv[0] [-f | --force]\n");
  exit(1); 
}

if (is_dir($outdir))
{
  if ($force)
    del_dir($outdir);
  else
  {
    print("Error: Directory '$outdir' already exists.\n");
    exit(2);
  }
}

if (!mkdir($outdir))
{
  print("Error: Failed to create directory '$outdir'\n");
  exit(2);
}

foreach ($RUN_TESTS as $test)
{
  $revout = "$outdir/$test-revs";
  $cvsout = "$outdir/$test-cvs";
  $coout = "$outdir/$test-co";
  $expected = "$basedir/$test-expected";
  mkdir($revout); mkdir($cvsout); mkdir($coout);
  $result = $test($revout, $cvsout, $coout, $expected) ? "FAILED" : "PASSED";
  print("== $test $result ==============================================\n");
  ob_flush();
}

?>