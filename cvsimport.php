<?

ini_set("output_buffering", "0");
ini_set("implicit_flush", "1");

// binary file that should be marked as such in cvs (given kb flag)
define("MODE_BINARY", 0);

// text file. any CRLF's and CR's will be replaced by LF's. This mode should be
// be used for any Unix, Mac, or PC text file. It should not be used for
// binary files because it will corrupt them.
define("MODE_TEXT", 1);

// text file that should be copied into cvs literally (without replacing CRLF's
// and CR's with LF's
// Use this mode when you are unsure if a file is a unix text file or if it contains
// binary data. In this mode, binary files will not be corrupted on import, and
// you can always mark them with -kb later to prevent them from being corrupted
// on checkout with non-unix cvs clients. This mode should not be used for files
// that contain MS-DOS or mac line endings, otherwise those line endings will
// be copied literally into cvs. It only works for UNIX text files because the CVS
// server happens to use the UNIX format internally.
define("MODE_SAFETEXT", 2);

// on unix, this is equivalent to MODE_SAFETEXT, on PC and MAC this should be
// the same as MODE_TEXT. This mode can be used on text files that
// are in the format corresponding to the current operating system. The script
// will open these files in "rt" mode and allow the system's C library to perform
// and translations. This mode is included for completeness only, there shouldn't
// be any reason to use it unless you are running this on operating system which
// stores text files in an unknown format.
define("MODE_NATIVETEXT", 3);

define("MODE_TRANSLATE_MASK", 3);

// this can be ORed with MODE_TEXT, MODE_SAFETEXT, or MODE_NATIVETEXT to enable
// RCS keyword substitution on the file, by default it will be disabled. If the
// file is marked as MODE_BINARY, this flag will be ignored.
define("MODE_SUBSTITUTION", 4);

define("PRUNE_DISABLED");

$mode_abbrevs = array(MODE_BINARY => 'b', MODE_TEXT => 't', MODE_SAFETEXT => 's', MODE_NATIVETEXT => 'v');

class Revision
{
  // public variables:

  var $directory; // folder containing that srevision
  var $tag; // tag name to use for this revision
  var $btag; // branch tag name to use if this is the first node in a new branch
  var $log;
  var $author;
  
  // internal variables:

  var $snumber; // stringified revision number
  var $number; // cvs revision as an array of integers
  var $date = false; // date of the newest file
 
  function Revision($directory, $tag, $btag, $log = '', $author = '')
  {
    GLOBAL $DEFAULT_AUTHOR, $BLANKFILE;

    $this->directory = $directory;
    $this->tag = $tag ? as_array($tag) : array();
    $this->btag = $btag;
    $this->log = $log;
    $this->author = $author;
  }
}

class File
{
  var $path;
  var $mode;
  var $submode;
  var $defaultBranch = false;
  var $headRevision = false;
  var $revisions = array();
  var $zeroBranches = array();
  
  // When a file doesn't exist at all on a particular
  // branch but does exist in one of the branch's 
  // ancestor revisions, the default behavior (pruneBranches) is
  // to mark the file as being deleted for the branch
  // When pruneBranches is true, the branch tag is completely
  // left out of the RCS file, saving a little space.
  
  var $pruneBranches = false;
  
  // array of branch numbers (arrays of odd length)
  // indexed by branch tags
  var $zeroBranches = array();
  
  function File($path)
  {
    $this->path = $path;
  }
  
  function prepareNodes(&$versions, $prune) // set up nodes for a particular file
  {
    $history = array();

    foreach(array_keys($versions) as $i)
    {
      $version = &$versions[$i];
      $depth = count($version->number);

      if ($version->number == array(1,1,1,1)) $file->defaultBranch = $i;

      // $pa is the index of this branch's parent node
      // $pr is the index of the previous node on the current branch
      $pa = variable_get($history[$depth - 2], false);
      $pr = variable_get($history[$depth], false);
      if ($pr && $depth > 2 && !array_eq($version->number, $versions[$pr]->number, $depth-1))
        $pr = false;

      $needsBranchTag = false;

      $revision =& new FileRevision();
      unset($prevision);

      // set revisions's revision, prev, and diff members

      if($depth == 2) // revision is on the main trunk
      {
        if ($pr === false)
        {
          $revision->number = array(1,1);
        }
        if ($pr !== false) // not the very first revision
        {
          $prevision =& $this->revisions[$pr];
          $prevision->diff = $i;  // because this is the main trunk use a reverse diff 
          $revision->next = $pr;  // and point backwards to the previous revision
          $revision->number = $prevision->number;
          ++$revision->number[1];
        }
      }
      else if($pr !== false)
      { // not the first revision on a branch
        $prevision =& $this->revisions[$pr];
        $revision->diff = $pr;   // use a forward diff on a branch
        $prevision->next = $i;   // previous node points to this one
        $revision->number = $prevision->number;
        ++$revision->revision[$depth-1];
      }
      else if($pa === false)
        warning("\"$version->snumber\" needs a parent node to branch off of. Either change its version number or add in a new node (even an empty one will do) like a \"" . implode(".",array_slice($version->number,0,$depth-2)) . "\"" . ( $version->number[$depth-3] != 1 ? " or a \"" . implode(".",array_slice($version->number,0,$depth-3)) . ".1\"": "") . ".");
      else // first revision on a branch
      {
        $prevision =& $this->revisions[$pa];
        $revision->diff = $pa;
        $prevision =& $file->revisions[$pa];
        
        if ($node->revisons[$depth-2] % 2 == 0)
        {
          array_push($revision->number, $prevision->branchNo, 1);
          $prevision->branchNo += 2;
        }
        else
        {
          array_push($revision->number, $prevision->vendorBranchNo, 1);
          $prevision->vendorBranchNo +=2;
        }
        $needsBranchTag = true;
      }

      if (!$needsBranchTag && strlen($version->btag) > 0)
        warning("You supplied a branch tag for version \"$version->snumber\" that cannot be used because this version is not at the head of a new branch. You need to set it to the empty string (\"\") or false.");
      else if ($needs_branch_tag && strlen($node->btag) == 0)
        warning("Because version \"$version->snumber\" is at the head of a new branch, you must give it a branch tag");

      // set exists, filename, date, exists, state, placeholder fields
      // if pruning is enabled, find out whether to keep this revision
      
      $keep = !$prune;
      $revision->filename = $version->directory . $this->path;
      
      if (file_exists($revision->filename))
      {
        $revision->exists = true;
        $revision->date = filemtime($revision->filename);
        $revision->filename = translate_file($revision->filename, $mode, $revision->temporary);
        if ($prevision->exists)
        {
          $f1 = escapeshellargs($prevision->filename);
          $f2 = escapeshellargs($revision->filename);
          if (`diff -q --binary "$f1" "$f2"`) $keep = true;
        }
        else
          $keep = true;
      }
      else // file_not_exists
      {
        $revision->exists = false;
        // the diff for a deleted file should be empty, avoid
        // making this a special case by pointing diff to the
        // previous filename
        $revision->date = $node->lastDate;
        $revision->filename = $prevision->filename;
        if ($prevision->exists) $keep = true;
      }

      // xxx: what about placeholder revision? (while a file
      // is originally added on a branch, its ancestors are
      // placeholder nodes
      
      if($keep)
      {
        if ($needsBranchTag)
        {
          
        }
        $revision->state = $revision->exists ? "Exp" : "dead";
        if ($node->posterior === $node->next) // cheesy way to see if the node is on the main thrunk
          $head = $i;
        $this->revisions[$i] =& $revision;
        $history[$depth] = $i;
      }
      else // this is a redundant or possibly node
      {
        if ($pr === false)
        {
          $parevision->zeroBranches = array_slice($revision->number, 0, -1)
        }
        else
        {
          
        }
        
        if($node->btag)
        {
          if ($node->anterior === false && $prevision->placeholder && count($prevision->branches) == 1)
          {
            $this->deletenode($frevisions,$node->posterior);
            $node->posterior = false;
            unset($pnode);
          }
          else
          {
            $f = array_search($i,$pnode->branches);
            if ($node->anterior !== false)
            {
              $pnode->branches[$f] = $node->anterior;
              $frevisions[$node->anterior]->btag = $node->btag;
            }
            else
              unset($pnode->branches[$f]);
          }
        }

        if (isset($pnode) && $node->date < $pnode->date)
        {
          $pnode->date = $node->date;
          $pnode->log = $node->log;
          $pnode->author = $node->author;
        }

        $this->deletenode($frevisions,$i);

      }
    }
    if (!$this->headRevision) warning("The CVS is tree is all branches and no trunk! CVS requires that you have at least one revision on the trunk to serve as the \"HEAD\" revision which is what all the other diffs in the file are ultimately based on. If you are sure you do not want any of your revisions to be on the trunk, you need to create a 1.1 revision and point it to a blank directory. If you are trying to emulate the results of a fresh CVS import command (which puts the files on vendor revision 1.1.1.1 AND on the trunk) just do these things: 1)Create a new 1.1 revision. 2)Point it to the same directory as your 1.1.1.1 revision. 3)Set the log entry to \"Initial revision\". 4)The branch tag on the 1.1.1.1 revision is equivalent to the \"vendor-tag\" argument of the CVS import command, so set that however you like it. 5)Do not create a tag for the 1.1 revision. Instead of a string, put false.");
  }

  function writeRCS($outdir)
  {
    if (!$this->revisions[$head]->exists)
    {
      $p = strrpos($filename,"/");
      $folder = substr($filename,0,$p) . "/Attic";
      @mkdir("$outdir$folder");
      $filename = "$folder/" . substr($filename,$p+1);
    }

    $fp = @fopen("$outdir$filename,v","wb") or die("Failed to open \"$outdir$filename,v\" for writing in " . __FILE__ . " on line " . __LINE__);

    fwrite($fp, "head\t" . implode(".",$this->revisions[$this->headRevision]->nrevision) . ";\n");
    if ($this->defaultBranch !== false && isset($this->revisions[$this->defaultBranch]))
      // XXX: apparently printing revision number not branch number
      fwrite($ftp, "branch\t" . implode(".",$this->revisions[$this->defaultBranch]->number) . ";\n");

    fwrite($fp, "access\t;\n");
    fwrite($fp, "symbols\t");

    $keys = array_reverse(array_keys($this->revisions));

    foreach($keys as $i)
    {
      $revision =& $this->revisions[$i];
      if ($revision->exists) // it should not be strictly neccessary to skip tags for dead files, but this seems to conform with observed cvs behavior
      foreach($revision->tags as $tag)
      if (strlen($tag) > 0)
        fwrite($fp, "\n\t$tag:" . implode(".", $this->revisions[$i]->nrevision));
      if (strlen($revision->btag) > 0)
      {
        if ($revision->exists || $this->next !== false || count($this->branches > 0) || !$this->pruneBranches)
        fwrite($fp, "\n\t" . $this->revisions[$i]->btag . ":" . implode(".", array_slice($this->revisions[$i]->nrevision,0,-1)));
      }
    }
    fwrite($fp,";\n");

    fwrite($fp, "locks\t;\nstrict\t;\ncomment\t@# @;\n");
    if ($mode == MODE_BINARY) fwrite($fp, "expand\t@b@;\n"); else fwrite($fp, "expand\t@o@;\n");
    fwrite($fp, "\n\n");

    foreach($keys as $i)
    {
      fwrite($fp, implode(".", $this->revisions[$i]->nrevision) . "\n");
      fwrite($fp, "date\t" . gmdate("Y.m.d.H.i.s",$this->revisions[$i]->date) . ";\tauthor " . $this->revisions[$i]->author . ";\tstate " . $this->revisions[$i]->state . ";\n");
      fwrite($fp, "branches\t");
      foreach($this->revisions[$i]->branches as $branch)
        fwrite($fp, "\n\t" . implode(".", $this->revisions[$branch]->nrevision));
      fwrite($fp, ";\n");

      $next = $this->revisions[$i]->next === false ? "" :implode(".", $this->revisions[$this->revisions[$i]->next]->nrevision);
      fwrite($fp, "next\t$next;\n\n");
    }
    fwrite($fp, "desc\n@@\n\n");

    $GLOBALS['NODE_BODY_COMPARE_LIST'] = $this->revisions;
    usort($keys, "node_body_compare");

    foreach($keys as $i)
    {
      fwrite($fp, implode(".", $this->revisions[$i]->nrevision) . "\n");
      fwrite($fp, "log\n@");
      if ($this->revisions[$i]->placeholder)
      {
        $p = strrpos($filename,"/");
        $thefile = $p === false ? $filename : substr($filename,$p+1);
        reset($this->revisions[$i]->branches);
        $thebranch = current($this->revisions[$i]->branches);
        fwrite($fp, "file " . str_replace("@", "@@", $thefile) . " was initially added on branch " . $thebranch->btag);
      }
      else
        fwrite($fp, str_replace("@", "@@", $this->revisions[$i]->log));
      fwrite($fp, "@\ntext\n@");

      if ($this->revisions[$i]->diff !== false)
        $pp = popen("diff -n -a --binary \"" . $this->revisions[$this->revisions[$i]->diff]->filename . " \" \"" . $this->revisions[$i]->filename . "\"","rb");
      else
        $pp = fopen($this->revisions[$i]->filename,"rb");

      while(!feof($pp))
      {
        $buffer = fread($pp, 8192);
        fwrite($fp, str_replace('@','@@', $buffer));
      }

      if ($this->revisions[$i]->diff !== false) pclose($pp); else fclose($pp);
      fwrite($fp, "@\n\n");
    }
    fclose($fp);


    foreach($keys as $i)
    {
      if ($this->revisions[$i]->temporary)
        unlink($this->revisions[$i]->filename);
    }
  }
  
  function DeleteNode($i)
  {
    $revision =& $this->revisions[$i];

    if ($revision->temporary) unlink($revision->filename);

    if ($revision->next !== false)
      $this->revisions[$revision->next]->diff = $revision->diff;

    if (!$revision->btag && $revision->diff !== false)
      $this->revisions[$revision->diff]->next = $revision->next;

    if ($revision->anterior !== false)
      $this->revisions[$revision->anterior]->posterior = $revision->posterior;

    if (!$revision->btag && $revision->posterior !== false)
      $this->revisions[$revision->posterior]->anterior = $revision->anterior;

    if ($revision->posterior !== false)
    {
      $t =& $this->revisions[$revision->posterior]->tag;
      $b =& $this->revisions[$revision->posterior]->branches;
      // push tag
      array_splice($t,0,0,$revision->tag);
      // push branches
      array_splice($b,count($b),0,$revision->branches);
      unset($t,$b);
    }

    foreach($revision->branches as $b)
      $this->revisions[$b]->diff = $this->revisions[$b]->posterior = $revision->posterior;

    unset($this->revisions[$i]);
  }
};

class FileRevision
{
  var $revision = array();
  var $next = false;
  var $diff = false;
  
  var $branchNo = 2; // next available child branch number to use in NodeList::prepareNodes()
  var $vendorBranchNo = 1; // next available vendor branch number
  var $branches = array();

  var $filename;
  var $exists = false;
  var $tag = array();
  var $btag = '';
  var $date = 0;
  var $author = '';
  var $state = '';
  var $placeholder = false;
  var $log = '';
  
  var $temporary = false;
 
  function FileRevision()
  {
    global $BLANKFILE;
    $this->filename = $BLANKFILE;
  }
};

class VersionList
{
  var $nodes;   // associative array of nodes
  var $files;   // associative array of all files that ever existed in the project. file paths are indices
  var $folders; // associative array of all folders that ever existed in the project. folder paths are indices

  var $defaultBranch; // array index of the head of the RCS default branch

  var $prune; // if set to true, new revision numbers will only be assigned to a file when it has been modified

  // PUBLIC FUNCTIONS

  function NodeList($nodes)
  {
    $this->prune = true;
    $this->pruneFirstBranch = false;
    $this->defaultbranch = false;

    foreach(array_keys($nodes) as $i)
    {
      $node =& $nodes[$i];

      $node->revision = array_map("make_integer",explode(".", $i));
      $node->srevision = implode('.', $revision);
      $depth = count($node->revision);

      if ($depth < 2)
        warning("\"$i\" is not a valid revision number. Revision numbers be series of digits separated by dots like 1.2, 1.31, 1.33.5.73, or 44.56 . A revision number has to have at least one dot.");
      else if ($depth % 2 != 0)
        warning("\"$i\" is not a valid revision number. (Revision numbers must have an even number of segments like 1.2 or 1.2.2.3 and NOT like 1.2.3)");
      else if (in_array(0, $node->revision, true))
        warning("\"$i\" is not a valid revision number. None of the numbers in a revision numbers can be zeros.");
  
      foreach($node->tag as $tag)
      if (strlen($tag) > 0 && !valid_tag($tag))
        warning("Revision \"$srevision\" has an invalid tag. A tag must begin with a letter and can be followed by letters, numbers, hypens, and underscores. Two reserved words \"BASE\" and \"HEAD\" cannot be used as tag names.");
  
      if (strlen($btag) > 0 && !valid_tag($btag))
        warning("Revision \"$srevision\" has an invalid branch tag. A tag must begin with a letter and can be followed by letters, numbers, hypens, and underscores. Two reserved words \"BASE\" and \"HEAD\" cannot be used as tag names.");
    }
    
    uasort($versions, "node_compare");



    $this->versions = $versions;
  }

  function display()
  {
    foreach($this->nodes as $v)
    {
      print("Revision $v->srevision\n");
      print("  Diff:       " . ($v->diff !== false ? $this->nodes[$v->diff]->srevision : "---" ) . "\n");
      print("  Next:       " . ($v->next !== false ? $this->nodes[$v->next]->srevision : "---" ) . "\n");
      if (count($v->branches) > 0)
      {
        print("  Branches:   ");
        $first = true;
        foreach($v->branches as $b)
        {
          if ($first) $first = false; else print("\n              ");
          print($this->nodes[$b]->srevision);
        }
        print("\n");
      }
      if ($v->btag) print("  Branch Tag: " . $v->btag . "\n");
      print("  Posterior:  " . ($v->posterior!== false ? $this->nodes[$v->posterior]->srevision : "---" ) . "\n");
      print("  Anterior:   " . ($v->anterior !== false ? $this->nodes[$v->anterior ]->srevision : "---" ) . "\n");
      print("\n");
    }
  }

  function ScanFolders($node = false, $folder = "")
  {
    global $OUTDIR;
    if ($node === false)
    {
      foreach(array_keys($this->nodes) as $i)
      {
        $this->nodes[$i]->lastDate = $this->ScanFolders($i);
      }
    }
    else
    {
      $root = $this->nodes[$node]->directory;
      $current = "$root$folder";
      $folderobj = opendir($current) or die ("\n\nOh no! opendir(\"$current\") failed!\n\n");

      $mtime = 0;

      while (($name = readdir($folderobj)) !== false)
      if ($name !== "." && $name !== "..")
      {
        $relative = "$folder/$name";
        $absolute = "$current/$name";

        if (is_dir($absolute))
        {
          if(!isset($this->folders[$relative]))
          {
            mkdir("$OUTDIR$relative", 0777) or die("Failed to create \"$OUTDIR$relative\".\nMake sure \"$OUTDIR\" exists and is completely empty before you run this script.");
            $this->folders[$relative] = true;
          }
          $timestamp = $this->ScanFolders($node, $relative);
        }
        else
        {
          $this->files[$relative] = true;
          $timestamp = filemtime($absolute);
        }
        if ($timestamp > $mtime) $mtime = $timestamp;
      }
      return $mtime;
    }
  }

  function save()
  {
    global $mode_abbrevs;
    reset ($this->files);
    while (list($path) = each($this->files))
    {
      $mode = get_mode(substr($filename,1));
      print(($mode_abbrevs[$mode] & MODE_TRANSLATE_MASK) .  " $filename ... "); ob_flush(); flush();
      $file =& new File($path);
      $this->preparenodes($file);
      HookInitFile($file, $this->nodes);
      $this->writeRCS($file);
      print("Done.\n"); ob_flush(); flush();      
    }
  }

  // PRIVATE HELPER FUNCTIONS

  }


function warning($warning)
{
  global $WARNED;
  $WARNED = true;
  print (wordwrap("WARNING: $warning\n\n"));
}
$WARNED = false;

function ends_with($name, $ending)
{
  return substr($name, -strlen($ending)) === $ending;
}

function make_integer($n)
{
  return (int)$n;
}

function valid_tag($tag)
{
  if ($tag == "HEAD" || $tag == "BASE") return false;
  for($i=0; $i < strlen($tag); ++$i)
  {
    $o = ord($tag[$i]);
    if ((65 <= $o && $o <= 90) || (97 <= $o && $o <= 122)) // a letter ?
      continue;
    else if ($i == 0) // only letters are allowed for the first character
      return false;
    else if (48 <= $o && $o <= 57) // a number?
      continue;
    else if ($o == 45) // hypen
      continue;
    else if ($o == 95) // underline
      continue;
    else
      return false;
  }
  return true;
}

function node_compare($a, $b)
{
  $ra = &$a->revision;
  $rb = &$b->revision;

  for($i=0;;++$i)
  if(isset($ra[$i]))
  {
    if (isset($rb[$i]))
    {
      if ($ra[$i] == $rb[$i]) continue;
      return $ra[$i] < $rb[$i] ? -1 : 1;
    }
    else
      return 1;
  }
  else if (isset($rb[$i]))
    return -1;
  else
    return 0;
}

function node_body_compare($a,$b)
/*
  The CVS co, export, and update commands can fail if they don't find the
  "text" (and "log") entries arranged the correct order. The arrangement that
  seemed to consistently work was where the diff's needed to construct any
  particular revision of the file would be arranged in the order they would
  eventually need to be applied. This way, CVS can go through the RCS file
  sequentially without ever having to jump backwards.

  Example: 1.5 1.4 1.4.2.1 1.3 1.3.2.1 1.3.2.2 1.2 1.1

  The code is a copy and paste of node_compare except that it uses a reverse
  ordering on the main trunk and a forward ordering on the branches.
*/
{
  global $NODE_BODY_COMPARE_LIST;

  $ra = $NODE_BODY_COMPARE_LIST[$a]->nrevision;
  $rb = $NODE_BODY_COMPARE_LIST[$b]->nrevision;
  $m = -1;

  for($i=0;;++$i)
  {
    if ($i > 1) $m = 1;
    if(isset($ra[$i]))
    {
      if (isset($rb[$i]))
      {
        if ($ra[$i] == $rb[$i]) continue;
        return $ra[$i] < $rb[$i] ? -$m : $m;
      }
      else
        return $m;
    }
    else if (isset($rb[$i]))
      return -$m;
    else
      return 0;
  }
}

function as_array($v)
{
  return is_array($tag) ? $tag : array($tag);
};

function array_eq($a, $b, $depth)
{
  return array_slice($a,0,$depth) == array_slice($b,0,$depth);
}

function variable_get(&$var, $default = NULL)
{
  if (isset($var)) return $var; else return $default;
}

function translate_file($filename, $mode, &$temporary)
{
  if ($mode == MODE_BINARY || $mode == MODE_SAFETEXT)
  {
    $temporary = false;
    return $filename;
  }

  if ($mode == MODE_TEXT)
  {
    $fn = tempnam("","");
    $temporary = true;

    $fr = fopen($filename, 'rb');
    $fw = fopen($fn, 'wb');

    $lastCR = false;

    while(!feof($fr))
    {
      $str = fread($fr, 8192);

      if ($lastCR && substr($str, 0, 1) == "\r")
        $str = substr($str, 1);

      $lastCR = substr($str, -1) == "\n";

      $str = str_replace("\r\n", "\n", $str);
      $str = str_replace("\r", "\n", $str);

      fwrite($fw, $str);
    }

    fclose($fr);
    fclose($fw);

    return $fn;
  }
  else if ($mode == MODE_NATIVETEXT)
  {
    $fn = tempnam("","");
    $temporary = true;

    $fr = fopen($filename, 'rt');
    $fw = fopen($fn, 'wb');

    while(!feof($fr))
    {
      $str = fread($fr, 8192);
      fwrite($fw, $str);
    }

    fclose($fr);
    fclose($fw);

    return $fn;
  }
  else
    print("Warning: Unknown mode $mode\n");
}

$BLANKFILE = tempnam("","");

if ($_SERVER['argc'] != 1)
{
  print("Usage: php cvsimport.php hooks.inc\n");
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

$nl = new NodeList($VERSIONS);
//$nl->display();
if ($WARNED)
  print("\n\nWarnings have been issued. Please correct any problems and try again.\n\n");
else
{
  $nl->ScanFolders();
  $nl->save();
}

unlink($BLANKFILE);

print("\nDone.\n");

?>