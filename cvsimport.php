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

// on unix, this is equivalent to MODE_SAFETEXT, on PC and MAC this is almost
// exactly the same as MODE_TEXT. This mode can be used on text files that
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

$mode_abbrevs = array(MODE_BINARY => 'b', MODE_TEXT => 't', MODE_SAFETEXT => 's', MODE_NATIVETEXT => 'v');

class File
{
  var $path;
  var $mode;
  var $submode;
  
  function File($path)
  {
    $this->path = $path;
  }
};

class FileRevision
{
  var $exists = false;
  var $revision = array();
  var $tag = array();
  var $btag = '';
  var $date = 0;
  var $author = '';
  var $state = '';
  var $branches = array();
  var $placeholder = false;
  var $next = false;
  var $diff = false;
  var $log = '';
  var $filename;
  var $temporary = false;

  var $branchno;
  function FileRevision()
  {
    global $BLANKFILE;
    $this->filename = $BLANKFILE;
  }
};



class Revision
{
  // public variables:

  var $directory; // folder containing that srevision
  var $tag; // tag name to use for this revision
  var $btag; // branch tag name to use if this is the first node in a new branch
  var $log;
  var $author;
  
  // internal variables:

  var $srevision; // stringified revision number
  var $revision; // cvs revision as an array of integers
  var $date = false; // date of the newest file
  var $branches = array();
  var $next = false;
  var $diff = false;
  var $posterior = false;
  var $anterior = false;

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

class NodeList
{
  var $nodes;   // associative array of nodes
  var $files;   // associative array of all files that ever existed in the project. file paths are indices
  var $folders; // associative array of all folders that ever existed in the project. folder paths are indices

  var $defaultbranch; // array index of the head of the RCS default branch

  var $prune; // if set to true, new revision numbers will only be assigned to a file when it has been modified

  // PUBLIC FUNCTIONS

  function NodeList($nodes)
  {
    $this->prune = true;
    $this->defaultbranch = false;

    uasort($nodes, "node_compare");
    $history = array();

    foreach(array_keys($nodes) as $i) // traverse the sorted list and fill in the next, diff, and branches members
    {
      $node =& $nodes[$i];
   
      $revision = $node->revision = array_map("make_integer",explode(".", $i));
      $srevision = $node->srevision = implode('.', $revision);
      $depth = count($node->revision);
      if ($depth < 2)
        warning("\"$i\" is not a valid revision number. Revision numbers be series of digits separated by dots like 1.2, 1.31, 1.33.5.73, or 44.56 . A revision number has to have at least one dot.");
      else if ($depth % 2 != 0)
        warning("\"$i\" is not a valid revision number. (Revision numbers must have an even number of segments like 1.2 or 1.2.2.3 and NOT like 1.2.3)");
      else if (in_array(0,$node->revision,true))
        warning("\"$i\" is not a valid revision number. None of the numbers in a revision numbers can be zeros.");
  
      foreach($node->tag as $tag)
      if (strlen($tag) > 0 && !valid_tag($tag))
        warning("Revision \"$srevision\" has an invalid tag. A tag must begin with a letter and can be followed by letters, numbers, hypens, and underscores. Two reserved words \"BASE\" and \"HEAD\" cannot be used as tag names.");
  
      if (strlen($btag) > 0 && !valid_tag($btag))
        warning("Revision \"$srevision\" has an invalid branch tag. A tag must begin with a letter and can be followed by letters, numbers, hypens, and underscores. Two reserved words \"BASE\" and \"HEAD\" cannot be used as tag names.");

      if ($revision == array(1,1,1,1)) $this->defaultbranch = $i;

      // $pr is the index of the previous node on the current branch
      // $pa is the index of this branch's parent node
      $pr = isset($history[$depth]) ? $history[$depth] : false;
      $pa = isset($history[$depth - 2]) ? $history[$depth - 2] : false;

      $needs_branch_tag = false;

      if($depth == 2) // revision is on the main trunk
      {
        if ($pr !== false) // not the very first revision
        {
          $nodes[$pr]->diff = $i;   // use a reverse diff because this is the main trunk
          $node->next = $pr;       // point backwards to the previous revision
          $node->posterior = $pr;
          $nodes[$pr]->anterior = $i;
        }
      }
      else if($pr !== false && array_eq($revision,$nodes[$pr]->revision,$depth-1))
      {
        $node->diff = $pr;    // use a forward diff on a branch
        $nodes[$pr]->next = $i;        // previous node points to this one
        $node->posterior = $pr;
        $nodes[$pr]->anterior = $i;
      }
      else if($pa === false) // problemo
        warning("\"$srevision\" needs a parent node to branch off of. Either change its revision number or add in a new node (even an empty one will do) like a \"" . implode(".",array_slice($revision,0,$depth-2)) . "\"" . ( $revision[$depth-3] != 1 ? " or a \"" . implode(".",array_slice($revision,0,$depth-3)) . ".1\"": "") . ".");
      else // this is a new branch
      {
        $node->diff = $pa;
        $nodes[$pa]->branches[] = $i;
        $needs_branch_tag = true;
        $node->posterior = $pa;
      }

      if (!$needs_branch_tag && strlen($node->btag) > 0)
        warning("You supplied a branch tag for revision \"$srevision\" that cannot be used because this revision is not at the head of a new branch. You need to set it to the empty string (\"\") or false.");
      else if ($needs_branch_tag && strlen($node->btag) == 0)
        warning("Because revision \"$srevision\" is at the head of a new branch, you must to give it a branch tag");

      $history[$depth] = $i;
    };

    if (!isset($history[2])) warning("The CVS is tree is all branches and no trunk! CVS requires that you have at least one revision on the trunk to serve as the \"HEAD\" revision which is what all the other diffs in the file are ultimately based on. If you are sure you do not want any of your revisions to be on the trunk, you need to create a 1.1 revision and point it to a blank directory. If you are trying to emulate the results of a fresh CVS import command (which puts the files on vendor revision 1.1.1.1 AND on the trunk) just do these things: 1)Create a new 1.1 revision. 2)Point it to the same directory as your 1.1.1.1 revision. 3)Set the log entry to \"Initial revision\". 4)The branch tag on the 1.1.1.1 revision is equivalent to the \"vendor-tag\" argument of the CVS import command, so set that however you like it. 5)Do not create a tag for the 1.1 revision. Instead of a string, put false.");

    $this->nodes = $nodes;
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

  function preparenodes($file) // set up nodes for a particular file
  {
    $file->revisions = array();

    foreach(array_keys($this->nodes) as $i)
    {
      $node = &$this->nodes[$i];
      $depth = count($node->revision);
      
      $fnode = new FileRevision();
      
      // establish $pfnode (reference to posterior node) and $node->nrevision (new revision number)
      if($fnode->posterior === false)
      {
        $pfnode =& new FileRevision();
        $fnode->revision = array($node->revision[0],1);
      }
      else
      {
        $pfnode =& $file->revisions[$node->posterior];
        $fnode->revision = $pfnode->revision;
        if ($node->btag)
        {
          // new number should be even or odd just like old number
          $pfnode->branchno += ((($pnode->branchno % 2 == 0)
            xor ($node->revision[$depth-2] % 2 == 0)) ? 1 : 2);
          $fnode->revision[] = $pfnode->branchno;
          $fnode->revision[] = 1;
        }
        else
          ++$fnode->revision[count($node->nrevision)-1];
      }

      // find out if this node can be eliminated because it is redundant
      // set exists, filename, date, exists, state, placeholder fields
      $keep = false;
      $fnode->filename = $node->directory . $file->path;
      if (file_exists($fnode->filename))
      {
        $fnode->exists = true;
        $fnode->filename = translate_file($fnode->filename, $mode, $fnode->temporary);
        $fnode->date = filemtime($node->filename);
        if ($pfnode->exists)
        {
          $f1 = $pfnode->filename;
          $f2 = $fnode->filename;
          if (`diff -q --binary "$f1" "$f2"`) $keep = true;
        }
        else
          $keep = true;
      }
      else // file_not_exists
      {
        $fnode->exists = false;
        // the diff for a deleted file tells what the file looked like before it was deleted
        $fnode->filename = $pfnode->filename;
        $fnode->date = $node->lastDate;
        if ($pfnode->exists) $keep = true;
      }

      if ($node->posterior === false && !$keep && count($fnode->branches) > 0)
      {
        $keep = true;
        // can't delete this node unless all branch nodes are deleted
        $fnode->placeholder = true;
      }

      if ($node->btag && $node->exists)
        $keep = true;

      if (!$this->prune) $keep = true;

      if($keep)
      {
        $fnode->state = $fnode->exists ? "Exp" : "dead";
        if ($node->posterior === $node->next) // cheesy way to see if the node is on the main thrunk
          $head = $i;
        $file->node[$i] =& $fnode;
      }
      else // this is a redundant node that will be deleted
      {
        if($node->btag)
        {
          if ($node->anterior === false && $pfnode->placeholder && count($pfnode->branches) == 1)
          {
            $this->deletenode($fnodes,$node->posterior);
            $node->posterior = false;
            unset($pnode);
          }
          else
          {
            $f = array_search($i,$pnode->branches);
            if ($node->anterior !== false)
            {
              $pnode->branches[$f] = $node->anterior;
              $fnodes[$node->anterior]->btag = $node->btag;
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

        $this->deletenode($fnodes,$i);

      }
    }
  }

  function DeleteNode(&$fnodes, $i)
  {
    $node = &$fnodes[$i];

    if ($node->temporary) unlink($node->filename);

    if ($node->next !== false)
      $fnodes[$node->next]->diff = $node->diff;

    if (!$node->btag && $node->diff !== false)
      $fnodes[$node->diff]->next = $node->next;

    if ($node->anterior !== false)
      $fnodes[$node->anterior]->posterior = $node->posterior;

    if (!$node->btag && $node->posterior !== false)
      $nodes[$node->posterior]->anterior = $node->anterior;

    if ($node->posterior !== false)
    {
      $t = &$fnodes[$node->posterior]->tag;
      $b = &$fnodes[$node->posterior]->branches;
      array_splice($t,0,0,$node->tag);
      array_splice($b,count($b),0,$node->branches);
      unset($t,$b);
    }

    foreach($node->branches as $b)
      $fnodes[$b]->diff = $fnodes[$b]->posterior = $node->posterior;

    unset($fnodes[$i]);
  }

  function writeRCS($filename, $mode, &$fnodes, $head)
  {
    global $OUTDIR, $NODE_BODY_COMPARE_LIST;

    if (!$fnodes[$head]->exists)
    {
      $p = strrpos($filename,"/");
      $folder = substr($filename,0,$p) . "/Attic";
      @mkdir("$OUTDIR$folder");
      $filename = "$folder/" . substr($filename,$p+1);
    }

    $fp = @fopen("$OUTDIR$filename,v","wb") or die("Failed to open \"$OUTDIR$filename,v\" for writing in " . __FILE__ . " on line " . __LINE__);
    $keys = array_reverse(array_keys($fnodes));

    fwrite($fp, "head\t" . implode(".",$fnodes[$head]->nrevision) . ";\n");
    if ($this->defaultbranch !== false && isset($fnodes[$this->defaultbranch]))
      fwrite($ftp, "branch\t" . implode(".",$fnodes[$this->defaultbranch]->nrevision) . ";\n");

    fwrite($fp, "access\t;\n");
    fwrite($fp, "symbols\t");

    foreach($keys as $i)
    {
      if ($fnodes[$i]->exists) // it should not be strictly neccessary to skip tags for dead files, but this seems to conform with observed cvs behavior
      foreach($fnodes[$i]->tag as $tag)
      if (strlen($tag) > 0)
        fwrite($fp, "\n\t$tag:" . implode(".", $fnodes[$i]->nrevision));
      if (strlen($fnodes[$i]->btag) > 0)
        fwrite($fp, "\n\t" . $fnodes[$i]->btag . ":" . implode(".", array_slice($fnodes[$i]->nrevision,0,-1)));
    }
    fwrite($fp,";\n");

    fwrite($fp, "locks\t;\nstrict\t;\ncomment\t@# @;\n");
    if ($mode == MODE_BINARY) fwrite($fp, "expand\t@b@;\n"); else fwrite($fp, "expand\t@o@;\n");
    fwrite($fp, "\n\n");

    foreach($keys as $i)
    {
      fwrite($fp, implode(".", $fnodes[$i]->nrevision) . "\n");
      fwrite($fp, "date\t" . gmdate("Y.m.d.H.i.s",$fnodes[$i]->date) . ";\tauthor " . $fnodes[$i]->author . ";\tstate " . $fnodes[$i]->state . ";\n");
      fwrite($fp, "branches\t");
      foreach($fnodes[$i]->branches as $branch)
        fwrite($fp, "\n\t" . implode(".", $fnodes[$branch]->nrevision));
      fwrite($fp, ";\n");

      $next = $fnodes[$i]->next === false ? "" :implode(".", $fnodes[$fnodes[$i]->next]->nrevision);
      fwrite($fp, "next\t$next;\n\n");
    }
    fwrite($fp, "desc\n@@\n\n");

    $NODE_BODY_COMPARE_LIST = $fnodes;
    usort($keys, "node_body_compare");

    foreach($keys as $i)
    {
      fwrite($fp, implode(".", $fnodes[$i]->nrevision) . "\n");
      fwrite($fp, "log\n@");
      if ($fnodes[$i]->placeholder)
      {
        $p = strrpos($filename,"/");
        $thefile = $p === false ? $filename : substr($filename,$p+1);
        reset($fnodes[$i]->branches);
        $thebranch = current($fnodes[$i]->branches);
        fwrite($fp, "file " . str_replace("@", "@@", $thefile) . " was initially added on branch " . $thebranch->btag);
      }
      else
        fwrite($fp, str_replace("@", "@@", $fnodes[$i]->log));
      fwrite($fp, "@\ntext\n@");

      if ($fnodes[$i]->diff !== false)
        $pp = popen("diff -n -a --binary \"" . $fnodes[$fnodes[$i]->diff]->filename . " \" \"" . $fnodes[$i]->filename . "\"","rb");
      else
        $pp = fopen($fnodes[$i]->filename,"rb");

      while(!feof($pp))
      {
        $buffer = fread($pp, 8192);
        fwrite($fp, str_replace('@','@@', $buffer));
      }

      if ($fnodes[$i]->diff !== false) pclose($pp); else fclose($pp);
      fwrite($fp, "@\n\n");
    }
    fclose($fp);


    foreach($keys as $i)
    {
      if ($fnodes[$i]->temporary)
        unlink($fnodes[$i]->filename);
    }
  }
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