<?

class Revision
{
  // folder containing this revision's files
  var $directory;
  
  // array of tag names to use for this revision
  var $tags; 

  // branch tag name to use if this is the first revision in a new branch
  var $btag; 
  
  // revision number as an array of integers
  var $number;
  
  // date of the newest file as unix timestamp
  var $date;
  
  function Revision($tag, $btag, $directory)
  {
    $this->tag = $tag ? as_array($tag) : array();
    $this->btag = $btag ? (string)$btag : false;
    $this->directory = $directory;
    $this->date = 0;
  }
  
  function setNumber($key)
  {
    $this->number = array_map("intval", explode(".", $key));    
  }
}

class RevisionList
{
  // associative array of revisions
  var $revisions; 
  
  // associative array of all files that ever existed in the project.
  // file paths are indices
  var $files;     
  
  // associative array of all folders that ever existed in the project.
  // folder paths are indices
  var $folders;   
  
  // folder where the CVS repository should be written
  var $outFolder;

  // true if warnings have been printed
  var $warned;

  function RevisionList(&$revisions, $outFolder)
  {
    $this->revisions =& $revisions;
    $this->outFolder = $outFolder;
    $this->warned = false;
    
    // yea, verily go forth and set fields. perform much error checking
    foreach (array_keys($revisions) as $i)
    {
      $revision =& $revisions[$i];
      $revision->setNumber($i);
      $depth = count($revision->number);

      if ($depth < 2)
        $this->warn("\"$i\" is not a valid revision number. Revision numbers "
          . "be series of digits separated by dots like 1.2, 1.31, 1.33.5.73, "
          . "or 44.56 . A revision number has to have at least one dot.");
      else if ($depth % 2 != 0)
        $this->warn("\"$i\" is not a valid revision number. (Revision numbers "
          . "must have an even number of segments like 1.2 or 1.2.2.3 and NOT "
          . "like 1.2.3)");
      else if (in_array(0, $revision->number, true))
        $this->warn("\"$i\" is not a valid revision number. None of the "
          . "numbers in a revision number can be zeros.");
  
      foreach($revision->tag as $tag)
      if (strlen($tag) > 0 && !$this->isTag($tag))
        $this->warn("Revision \"$srevision\" has an invalid tag. A tag must "
          . "begin with a letter and can be followed by letters, numbers, "
          . "hypens, and underscores. Two reserved words \"BASE\" and "
          . "\"HEAD\" cannot be used as tag names.");
  
      if (strlen($revision->btag) > 0 && !$this->isTag($revision->btag))
        $this->warn("Revision \"$srevision\" has an invalid branch tag. A tag "
          . "must begin with a letter and can be followed by letters, "
          . "numbers, hypens, and underscores. Two reserved words \"BASE\" "
          . "and \"HEAD\" cannot be used as tag names.");
    }
    
    // sort array
    uasort($revisions, "RevisionCompare");
    
    $history = array();
    foreach (array_keys($revisions) as $i)
    {
      $revision =& $revisions[$i];
      $depth = count($revision->number);

      $previous = $parent = null;

      if (isset($history[$depth])
        && RevisionIsSameBranch($revision->number, $history[$depth]->number))
        $previous =& $history[$depth];

      if (isset($history[$depth-2]) && $history[$depth-2]->number
        == array_slice($revision->number, 0, $depth-2))
        $parent =& $history[$depth-2];

      $newBranch = $depth != 2 && !isset($previous);

      if ($newBranch && !isset($parent))
        $this->warn("\"" .RevisionString($revision->number) . "\" needs a "
         . "parent revision to branch off of. Either change its revision "
         . "number or add in a new revision (even one with no files will do) " 
         . "like a \"" . RevisionString($revision->number, $depth-2) . "\".");

      if (!$newBranch && strlen($revision->btag) > 0)
        $this->warn("You supplied a branch tag for revision \"" 
         . RevisionString($revision->number) . "\" that cannot be used "
         . "because this revision is not at the head of a new branch. You"
         . "need to set it to the empty string (\"\") or false.");
      else if ($newBranch && strlen($revision->btag) == 0)
        $this->warn("Because version \"$version->snumber\" is at the head of "
         . "a new branch, you must give it a branch tag");
  
      $history[$depth] =& $revision;

      unset($previous, $parent);
    }
    
    if (!isset($history[2])) $this->warn("The CVS is tree is all branches and "
      . "no trunk! CVS requires that you have at least one revision on the "
      . "trunk to serve as the \"HEAD\" revision which is what all the other "
      . "diffs in the file are ultimately based on. If you are sure you do "
      . "not want any of your revisions to be on the trunk, you need to "
      . "create a 1.1 revision and point it to a blank directory. If you are "
      . "trying to emulate the results of a fresh CVS import command (which "
      . "puts the files on vendor revision 1.1.1.1 AND on the trunk) just do "
      . "these things:\n1)Create a new 1.1 revision.\n2)Point it to the same "
      . "directory as your 1.1.1.1 revision.\n3)Set the log entry to "
      . "\"Initial revision\".\n4)The branch tag on the 1.1.1.1 revision is "
      . "equivalent to the \"vendor-tag\" argument of the CVS import command, "
      . "so set that however you like it.\n5)Do not create a tag for the 1.1 "
      . "revision. Instead of a string, put false.\n6) Set the canPruneBranch "
      . "option to false on the 1.1.1.1 revision");
  }

  function isTag($tag)
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

  function warning($warning)
  {
    $this->warned = true;
    print (wordwrap("WARNING: $warning\n\n"));
  }

  // scan input folders and create output directories
  function scan($revision = false, $folder = "")
  {
    if ($revision === false)
    {
      foreach(array_keys($this->revisions) as $i)
        $this->revisions[$i]->date = $this->scan($i);
    }
    else
    {
      $root = $this->revisions[$revision]->directory;
      $current = "$root$folder";
      $folderobj = opendir($current) or die ("\n\nopendir(\"$current\") "
        . "failed at " . __FILE__ . ":" . __LINE__ . "\n\n");

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
            mkdir("$OUTDIR$relative") or die("Failed to "
              . "create \"$OUTDIR$relative\".\nMake sure \"$OUTDIR\" exists "
              . "and is completely empty before you run this script.");
            $this->folders[$relative] = true;
          }
          $timestamp = $this->ScanFolders($revision, $relative);
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
    global $MODE_ABBREVIATIONS;
    reset ($this->files);
    while (list($path) = each($this->files))
    {
      $file =& new File($path);
      SetFileInfo($file);
      
      print(($MODE_ABBREVIATIONS[$file->mode & MODE_TRANSLATE_MASK])
        .  " " . substr($path, 1) . " ... "); 
      ob_flush(); flush();

      $file->setRevisions($this->revisions);
      $file->writeRCS($this->outFolder);

      print("Done.\n");
      ob_flush(); flush();      
    }
  }
}

class File
{
  // relative path to file in repository
  var $path;
  
  // some combination of MODE_ constants
  var $mode;

  // array of FileRevisions
  var $revisions = array();
  
  // This controls the "default branch" field at the top of the RCS file.
  // The setRevisions method will point it at the vendor branch with
  // the highest revision number or at nothing at all if there is a higher
  // revision number on the trunk. 
  var $defaultBranch;

  // This controls the value of the "head" field at the top of the RCS file.
  // setRevisions() just points it at the highest trunk revision.
  var $head;
  
  function File($path)
  {
    $this->path = $path;
  }

  // set up revisions for a particular file
  function setRevisions(&$revisions) 
  {
    $history = array();

    foreach(array_keys($revisions) as $key)
    {
      $revision =& $revisions[$key];
      $depth = count($revision->number);

      // find previous and parent revisions
      $previous = $parent = null;
      
      if (isset($history[$depth]) && RevisionIsSameBranch($revision->number,  
        $history[$depth]->revision->number))
        $previous =& $history[$depth];

      // parent should be set to closest non-ghost ancestor revision
      for ($i = $depth - 2; $i >= 2; $i -= 2)
      {
        if (isset($history[$i]) && !$history[$i]->isGhost)
        {
          assert($history[$i]->revision->number 
            == array_slice($revision->number, 0, $i));
          $parent =& $history[$i];
          break;
        }
      }

      $newBranch = $depth != 2 && !isset($previous);
      assert($newBranch xor !isset($parent));

      // find $compare, pointer to FileRevision to compare to
      if (isset($previous))
        $compare =& $previous;
      else if (isset($parent))
        $compare =& $parent;
      else
        $compare = null;

      $fileRev =& new FileRevision($this, $revision, $compare, $key);

      if (isset($previous))
      {
        if ($fileRev->changed || $fileRev->revision->canPrune)
        {
          if ($previous->isGhost)
            $previous->attach($fileRevs, $parent, null);
          $fileRev->attach($revisions, $parent, $previous);
        }
        else
        {
          if ($previous->isGhost)
            $parent->absorb($fileRev);
          else
            $previous->absorb($fileRev);
          unset ($fileRev);
        }
      }
      else // !isset($previous)
      {
        if ($fileRev->changed)
        {
          if (!isset($parent) && $fileRev->exists && $depth > 2)
          {
            assert(!isset($history[2]));
            $parent =& new FileRevision($this, $null, $null, null);
            $parent->author = $fileRev->author;
            $parent->date = $fileRev->date;
            list($folder, $file) = PathSplit($this->path);
            $parent->log = "file $file was initially added on branch "
              . "{$fileRev->btag}.\n";
            $parent->attach($revisions, $null, $null);
            $history[2] = $parent;
          }
          
          if ($fileRev->exists || $revision->tagDeletedBranch)
            $fileRev->attach($revisions, $parent, $previous);
          // otherwise fileRev becomes a "ghost" revision...
        }
        else
        {
          if ($fileRev->exists)
          { // potential "zero" revision
            if ($fileRev->revision->canPrune 
              && $fileRev->revision->canPruneBranch)
              $fileRev->isZero = true;
            $fileRev->attach($revisions, $parent, $previous);
          }        
          else
          {
            if (isset($parent)) $parent->absorb($fileRev);
            unset ($fileRev);
          }
        }
      } 
  
      if (isset($fileRev))
      {
        $history[$depth] =& $f;
        if (!$fileRev->isGhost && !$fileRev->isZero)
        {
          if ($depth == 4 && ($revision->number[2] % 2 == 1))
            $this->defaultBranch =& $fileRev;
          else if ($depth == 2 && isset($this->defaultBranch))
            unset($this->defaultBranch);
        }
        if ($depth == 2) $this->head =& $fileRev;
      }

      unset($r, $previous, $parent, $compare);
    }
  }
 
  function writeRCS($outdir)
  {
    if (!$this->revisions[$this->head]->exists)
    {
      list($folder, $file) = PathSplit($this->path);
      $folder = $outdir . $folder . "/Attic";
      @mkdir($folder);
      $rcsfile = "$folder/$file";
    }
    else
      $rcsfile = "$outdir$this->path,v";

    $fp = @fopen($rcsfile, "wb") or die("Failed to open \"$rcsfile\" for " 
      . "writing at " . __FILE__ . ":" . __LINE__);

    fwrite($fp, "head\t" . RevisionString($this->head->number) . ";\n");
    
    if (isset($this->defaultBranch))
      fwrite($fp, "branch\t" . RevisionString($this->defaultBranch->number, -1)
        . ";\n");

    fwrite($fp, "access\t;\nsymbols\t");

    uasort($this->revisions, 'RevisionBodyCompare');

    $keys = array_keys($this->revisions);
    foreach($keys as $i)
    {
      $revision =& $this->revisions[$i];
      if ($revision->isZero)
      {
        $rn = RevisionString($revision->number, -2);
        $d = count($revision->number);
        foreach($revision->tags as $tag)
          fwrite($fp, "\n\t$tag:$rn");
        if ($revision->btag)
          fwrite($fp, "\n\t$revision->btag:$rn.0." + $revision->number($d-2));
      }
      else
      {
        $rn = RevisionString($revision->number);
        foreach($revision->tags as $tag)
          fwrite($fp, "\n\t$tag:$rn");
        if ($revision->btag)
          fwrite($fp, "\n\t$revision->btag:"
            . RevisionString($revision->number, -1));
      }
    }
    fwrite($fp,";\n");

    fwrite($fp, "locks\t;\nstrict\t;\ncomment\t@# @;\n");
    if ($mode & MODE_TRANSLATE_MASK == MODE_BINARY) 
      fwrite($fp, "expand\t@b@;\n");
    else if (!($mode & MODE_SUBSTITUTION))
      fwrite($fp, "expand\t@o@;\n");
    fwrite($fp, "\n\n");

    foreach($keys as $i)
    {
      $revision =& $this->revisions[$i];
      if ($revision->isZero) continue;
      
      $state = $revision->exists ? "Exp" : "dead";
      fwrite($fp, RevisionString($revision->number) . "\n");
      fwrite($fp, "date\t" . gmdate("Y.m.d.H.i.s", $revision->date) 
        . ";\tauthor {$revision->author};\tstate $state;\n");
      fwrite($fp, "branches\t");
      foreach(array_keys($revision->branches) as $j)
      {
        $n = RevisionString($revision->branches[$j]->number);
        fwrite($fp, "\n\t$n");
      }
      fwrite($fp, ";\n");

      $next = isset($revision->next) ? RevisionString($revision->next->number)
        : "";
      fwrite($fp, "next\t$next;\n\n");
    }
    
    fwrite($fp, "desc\n@@\n\n");
    
    foreach($keys as $i)
    {
      $revision =& $this->revisions[$i];
      if ($revision->isZero) continue;
      
      fwrite($fp, RevisionString($revision->number) . "\n");
      fwrite($fp, "log\n@");
      if (!isset($revision->log) || $revision->log === false)
      {
        if (!isset($revision->log))
          print("Warning: No log message set on revision " 
            . RevisionString($revision->revision->number));
        fwrite($fp, "*** empty log message ***\n");
      }
      
      fwrite($fp, str_replace("@", "@@", $revision->log));
      fwrite($fp, "@\ntext\n@");

      if ($revision->exists)
      {
        if (isset($revision->diff))
        {
          if ($revision->diff->exists)
            $f1 = escapeshellargs($revision->diff->contents);
          else
            $f1 = $GLOBALS['BLANKFILE'];
          $f2 = escapeshellargs($revision->contents);
          $pp = popen("diff -n -a --binary \"$f1\" \"$f2\"", "rb");
        }
        else
          $pp = fopen($revision->contents,"rb");

        while(!feof($pp))
        {
          $buffer = fread($pp, 8192);
          fwrite($fp, str_replace('@','@@', $buffer));
        }
        if (isset($revision->diff)) pclose($pp); else fclose($pp);
        fwrite($fp, "@\n\n");
      }
    }
    fclose($fp);

    foreach($keys as $i)
    {
      if ($this->revisions[$i]->temporary)
        unlink($this->revisions[$i]->contents);
    }
  }
};


class FileRevision
{
  // pointer to associated File object
  var $file;
  
  // pointer to associated Revision object
  var $revision;
  
  // revision number expressed as an array of integer
  var $number;
  
  // array of tags
  var $tags;
  
  // branch tag, or NULL
  var $btag;

  // true if the file exists in this revision
  var $exists;
  
  // path to file containing this revision's contents
  var $contents;
  
  // true if contents is a temporary file that needs to be deleted
  var $isTemporary;

  // unix timestamp for this file revision
  var $date;

  // pointer to FileRevision which this will be diffed against, or null
  var $diff;
    
  // pointer to FileRevision which will be diffed against this revision
  var $next;

  // array of pointers to revisions which branch off of this one
  var $branches;

  // next available child branch number
  var $nextBranch; 
  
  // next available vendor branch number
  var $nextVendorBranch; 

  // true if hasn't been added as revision in file's revision tree
  var $isGhost;
  
  // true if this represents a special zero revision (revision like
  // 1.5.0.2 that does not have a log entry or a diff but is given
  // a tag indicating that presence of a branch with no changes)
  var $isZero;

  // If true, don't save revisions of this file that haven't changed
  var $canPrune = true;
  
  // If true and this file doesn't have any changes on a particular branch,
  // then no actual revision entries or logs will be saved on that branch.
  // Instead the branch will be tagged using CVS's special zero-revision
  // notation (i.e. branchtag:1.4.0.4 to indicate a branch 1.4.4 with no
  // with no changes)
  // Only has an effect when canPrune is true, and this revision is at the
  // head of the branch containing no changes for this file.
  var $canPruneBranch = true;
  
  // Put tags on deleted revisions
  var $tagDeleted = false;
  
  // Include a branch tag even when this file doesn't exist on a branch.
  // Only has an effect when this revision is at the head of the branch.
  var $tagDeletedBranch = false;

  var $author;
  var $log;

  function FileRevision(&$file, &$revision, &$compare, $key)
  {
    $this->file =& $file;
    
    if (isset($revision))
    {
      $this->revision =& $revision;
  
      $path = $revision->directory . $file->path;
  
      // find $exists, $date, $contents, $isTemporary
      if (file_exists($path))
      {
        $this->exists = true; 
        $this->date = filemtime($path);
        $this->contents = TranslateFile($path, $file->mode, 
          $this->isTemporary);
      }
      else
      {
        $this->exists = false;
        $this->date = $revision->lastDate; 
        $this->contents = $this->isTemporary = false;
      }
  
      // find $changed
      if ($this->exists)
      {
        if (!empty($compare->exists))
        {
          $f1 = escapeshellargs($compare->contents);
          $f2 = escapeshellargs($contents);
          $this->changed = (bool)`diff -q --binary "$f1" "$f2"`;
        }
        else
          $this->changed = true;
      }
      else
        $this->changed = !empty($compare->exists);
 
      $this->tags = $this->exists || $this->revision->tagDeleted ? 
        $revision->tags : array(); 
      $this->btag = $revision->btag;
      SetFileRevisionInfo($this, $key);
    }
    else
    {
      $this->revision = null;
      $this->exists = false;
      $this->contents = $this->isTemporary = false;
      $this->changed = false;
      $this->tags = array();
      $this->btag = false;
    }
  
    $this->isGhost = true;


  } 

  // add to array of FileRevisions
  function attach(&$revisions, &$parent, &$previous)
  {
    if (isset($previous))
    {
      $depth = count($previous->number);
      assert($depth % 2 == 0);
      $this->number = $previous->number;
      ++$this->number[$depth - 1];
        
      if ($depth == 2) // revision is on main trunk
      {
        // because this is the main trunk use a reverse diff 
        // and point backwards to the previous revision
        $this->diff = null;       
        $this->next =& $previous; 
        $previous->diff =& $this; 
        if (!$previous->contents)
          $previous->contents = $this->contents;
        
        // preserve first number
        if ($previous->revision->number[0] != $this->revision->number[0])
          $this->number = array($this->revision->number[0], 1);
      }
      else
      {
        $this->diff =& $previous;
        $this->next = null;
        $previous->next =& $this;
        if (!$this->contents) 
          $this->contents = $previous->contents;
      }
    }
    else // !isset($previous)
    {
      if (isset($parent))
      {
        $depth = count($parent->number);
        $this->number = $parent->number;
        if ($this->number[$depth-2] % 2 == 0)
        {
          array_push($this->number, $parent->nextBranch, 1);
          $parent->nextBranch += 2;
        }
        else
        {
          array_push($this->number, $parent->nextVendorBranch, 1);
          $parent->nextVendorBranch +=2;
        }

        $this->diff =& $parent;
        $this->next = null;
        $parent->branches[] =& $this;
      }
      else
      {
        $r = isset($this->revision) ? $this->revision->number[0] : 1;
        $this->number = array($r, 1); 
        $this->diff = null;
        $this->next = null;
      }
    }
    $revisions[$this->revision->key] =& $this;
    $this->branches = array();
    $this->isGhost = false;
    $this->nextBranch = 2;
    $this->nextVendorBranch = 1;
    if (count($this->number) == 2)
      $this->file->head =& $this;
  }
  
  // absorb information from unchanged revision
  function absorb(&$rev)
  {
    assert($this->btag === false);
    array_splice($this->tags, count($this->tags), 0, $rev->tags);
  }

  function display()
  {
    foreach($this->revisions as $v)
    {
      print("Revision $v->srevision\n");
      print("  Diff:       " . ($v->diff !== false ? $this->revisions[$v->diff]->srevision : "---" ) . "\n");
      print("  Next:       " . ($v->next !== false ? $this->revisions[$v->next]->srevision : "---" ) . "\n");
      if (count($v->branches) > 0)
      {
        print("  Branches:   ");
        $first = true;
        foreach($v->branches as $b)
        {
          if ($first) $first = false; else print("\n              ");
          print($this->revisions[$b]->srevision);
        }
        print("\n");
      }
      if ($v->btag) print("  Branch Tag: " . $v->btag . "\n");
      print("  Posterior:  " . ($v->posterior!== false ? $this->revisions[$v->posterior]->srevision : "---" ) . "\n");
      print("  Anterior:   " . ($v->anterior !== false ? $this->revisions[$v->anterior ]->srevision : "---" ) . "\n");
      print("\n");
    }
  }
  
}

function RevisionCompare($a, $b)
{
  $ra = &$a->number;
  $rb = &$b->number;

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

function RevisionBodyCompare($a, $b)
/*
  The CVS co, export, and update commands can fail if they don't find the
  "text" (and "log") entries arranged the correct order. The arrangement that
  seemed to consistently work was where the diff's needed to construct any
  particular revision of the file would be arranged in the order they would
  eventually need to be applied. This way, CVS can go through the RCS file
  sequentially without ever having to jump backwards.

  Example: 1.5 1.4 1.4.2.1 1.3 1.3.2.1 1.3.2.2 1.2 1.1

  The code is a copy and paste of RevisionCompare except that it uses a reverse
  ordering on the main trunk and a forward ordering on the branches.
*/
{
  $ra = &$a->number;
  $rb = &$b->number;
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

function RevisionIsSameBranch($n1, $n2)
{
  $d = count($n1);
  if ($d != count($n2)) return false;
  return $d == 2 || array_slice($n1, 0, $d) == array_slice($an, 0, $d);
}

function RevisionString($r, $depth = false)
{
  assert(count($r) % 2 == 0);
  $p = $depth === false ? $r : array_slice($r, 0, $depth);
  return implode('.', $p); 
}

function PathSplit($path)
{
  $p = strrpos($path, "/");
  if ($p === false)
    return array('', $path);
  else
    return array(substr($path, 0, $p), substr($path, $p+1));
}

function FileExtension($filename)
{
  $p = strrpos($filename, ".");
  if ($p === false)
    return "";
  else
    return substr($filename, $p+1);
}

define("MODE_TRANSLATE_MASK", 3);

// binary file that should be marked as such in cvs (given kb flag)
define("MODE_BINARY", 0);

// text file. any CRLF's and CR's will be replaced by LF's. This mode should be
// be used for any Unix, Mac, or PC text file. It should not be used for
// binary files because it will corrupt them.
define("MODE_TEXT", 1);

// text file that should be copied into cvs literally (without replacing CRLF's
// and CR's with LF's
// Use this mode when you are unsure if a file is a unix text file or if it 
// contains binary data. In this mode, binary files will not be corrupted on 
// import, and you can always mark them with -kb later to prevent them from 
// being corrupted on checkout with non-unix cvs clients. This mode should not 
// be used for files that contain MS-DOS or mac line endings, otherwise those 
// line endings will be copied literally into cvs. It only works correctly for
// UNIX text files because the CVS happens to use the UNIX format internally.
define("MODE_SAFETEXT", 2);

// on unix, this is equivalent to MODE_SAFETEXT, on PC and MAC this should be
// the same as MODE_TEXT. This mode can be used on text files that
// are in the format corresponding to the current operating system. The script
// will open these files in "rt" mode and allow the system's C library to 
// perform and translations. This mode is included for completeness only, there 
// shouldn't be any reason to use it unless you are running this on operating 
// system which stores text files in an unknown format.
define("MODE_NATIVETEXT", 3);

// this can be ORed with MODE_TEXT, MODE_SAFETEXT, or MODE_NATIVETEXT to enable
// RCS keyword substitution on the file, by default it will be disabled. If the
// file is marked as MODE_BINARY, this flag will be ignored.
define("MODE_SUBSTITUTION", 4);

$MODE_ABBREVIATIONS = array
( MODE_BINARY => 'b', MODE_TEXT => 't',
  MODE_SAFETEXT => 's', MODE_NATIVETEXT => 'v'
);

function TranslateFile($filename, $mode, &$temporary)
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

function as_array($v)
{
  return is_array($v) ? $v : array($v);
};

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
  print("\n\nWarnings have been issued. Please correct any problems and try again.\n\n");
else
{
  $BLANKFILE = tempnam("","");
  $nl->scan();
  $nl->save();
  unlink($BLANKFILE);
}

print("\nDone.\n");

?>