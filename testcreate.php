<?

require_once('cvsimport.inc');

function SetFileInfo(&$file)
{
  $file->mode = MODE_SAFETEXT;
  $file->collapseRevisions = false;
  $file->collapseBranches = false;
}

function SetFileRevisionInfo(&$fileRev, $key)
{
  $fileRev->author = 'russ';
  $fileRev->log = "whoa";
  $fileRev->tagDeleted = true;
}

function TestCreate($revisions, $outdir)
{
  $rs = array();
  foreach($revisions as $r)
  {
    list($number, $branch, $contents) = $r;
    $t = str_replace(".", "_", $number);
    $rtag = "t$t";
    $btag = $branch ? $branch : false;
    $dir = "$outdir/$number";
    @mkdir($dir);
    $file = "$dir/file";
    if ($contents === false)
      @unlink($file);
    else
    {
      $f = fopen($file, "wt");
      fwrite($f, $contents);
      fclose($f); 
    }
    $rs[$number] =& new Revision($rtag, $btag, $dir);
  }
  $nl =& new RevisionList($rs, $outdir);
  if ($nl->warned) die("fix warnings!");
  global $BLANKFILE;
  $BLANKFILE = tempnam("","");
  $nl->scan();
  $nl->save();
  unlink($BLANKFILE);
  
  $nl->checkout(true);
}

TestCreate(array
(
  array('1.1', false, false),
  array('1.1.3.1', 'noexisting', 'fw'),
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
), 'L:/server/shares/cvsroot/testimport');



/*


test out !isset(previous) branch of setrevisions()
TestCreate(array
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
), 'L:/server/shares/cvsroot/testimport');

test out isset(previous) branch of setrevisions()
TestCreate(array
(
  array('1.1', false, 'n'),
  array('1.1.3.1', 'noexisting', false),
  array('1.1.3.2', false, false),
  array('1.1.6.1', 'delteated_first', false),
  array('1.1.6.2', false, 'hhf'),
  array('1.1.6.3', false, false),
  array('1.1.6.4', false, 'hhf'),
  array('1.2', false, 'n'),
  array('1.3', false, false),
), 'L:/server/shares/cvsroot/testimport');
*/


?>