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

  function Revision($tags, $btag, $directory)
  {
    $this->tags = $tags ? as_array($tags) : array();
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

  // array containing paths to all files that ever existed in the project.
  var $files;

  // folder where the CVS repository should be written
  var $outFolder;

  // true if warnings have been printed
  var $warned;

  function RevisionList(&$revisions, $outFolder)
  {
    $this->revisions =& $revisions;
    $this->outFolder = $outFolder;
    $this->warned = false;

    $usedTags = array();
    $usedBranches = array();

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
        $this->warn("\"$i\" is not a valid revision number. Revision numbers "
          . "must have an even number of segments like 1.2 or 1.2.2.3 and NOT "
          . "like 1.2.3");
      else if (in_array(0, $revision->number, true))
        $this->warn("\"$i\" is not a valid revision number. None of the "
          . "numbers in a revision number can be zeros.");

      foreach($revision->tags as $tag)
      if (strlen($tag) > 0 && !$this->isTag($tag))
        $this->warn("Revision \"$i\" has invalid tag \"$tag\". A tag must "
          . "begin with a letter and can be followed by letters, numbers, "
          . "hypens, and underscores. Two reserved words \"BASE\" and "
          . "\"HEAD\" cannot be used as tag names.");
      else if (isset($usedTags[$tag]))
        $this->warn("Revision \"$i\" has duplicate tag \"$tag\". This tag "
          . "was first used on revision {$usedTags[$tag]}");
      else
        $usedTags[$tag] = $i;

      if (strlen($revision->btag) > 0)
      if (!$this->isTag($revision->btag))
        $this->warn("Revision \"$i\" has invalid branch tag "
          . "\"$revision->btag\". A tag must begin with a letter and can be "
          . "followed by letters, numbers, hypens, and underscores. Two "
          . "reserved words \"BASE\" and \"HEAD\" cannot be used as tag "
          . "names.");
      else if (isset($usedBranches[$revision->btag]))
        $this->warn("Revision \"$i\" has duplicate branch tag "
          . "\"$revision->btag\". This tag was first used on revision "
          . "{$usedBranches[$revision->btag]}");
      else
        $usedBranches[$revision->btag] = $i;
          
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
        $this->warn("\"$i\" needs a parent revision to branch off of. Either "
         . "change its revision number or add in a parent revision (even one "
         . "with no files will do).");

      if (!$newBranch && strlen($revision->btag) > 0)
        $this->warn("You supplied a branch tag for revision \"$i\" that "
         . "cannot be used because this revision is not at the head of a new "
         . "branch. You need to set it to the empty string (\"\") or false.");
      else if ($newBranch && strlen($revision->btag) == 0)
        $this->warn("Because revision \"$i\" is at the head of a new branch, "
         . "you must give it a branch tag");

      $history[$depth] =& $revision;

      unset($previous, $parent);
    }

    if (!isset($history[2])) $this->warn("The CVS tree is all branches and "
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
      . "so set that however you like.\n5)Do not create a tag for the 1.1 "
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

  function warn($warning)
  {
    $this->warned = true;
    print (wordwrap("WARNING: $warning\n\n"));
  }

  // scan input folders and create output directories
  function scan($revision = false, $folder = "")
  {
    if ($revision === false)
    {
      $this->tempFiles = array();
      $this->tempFolders = array();
      foreach(array_keys($this->revisions) as $i)
        $this->revisions[$i]->date = $this->scan($i);
      unset($this->tempFiles, $this->tempFolders);
    }
    else
    {
      $root = $this->revisions[$revision]->directory;
      $current = "$root/$folder";
      $folderobj = opendir($current) or die ("\n\nopendir(\"$current\") "
        . "failed at " . __FILE__ . ":" . __LINE__ . "\n\n");

      $mtime = 0;

      while (($name = readdir($folderobj)) !== false)
      if ($name !== "." && $name !== "..")
      {
        $relative = strlen($folder) ? "$folder/$name" : $name;
        $absolute = "$current/$name";

        if (is_dir($absolute))
        {
          $fr =& $this->tempFolders[normcase($relative)];
          if(empty($fr))
          {
            mkdir("{$this->outFolder}/$relative") or die("Failed to create "
              . "\"{$this->outFolder}/$relative\".\nMake sure "
              . "\"{$this->outFolder}/\" exists and is completely empty "
              . "before you run this script.");
            $fr = true;
          }
          $timestamp = $this->scan($revision, $relative);
        }
        else
        {
          $fr =& $this->tempFiles[normcase($relative)];
          if (empty($fr))
          {
            $fr = true;
            $this->files[] = $relative; 
          }
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
    foreach($this->files as $path)
    {
      //!$path = "html/demo.html";
      $file =& new File($path);
      SetFileInfo($file);

      print(($MODE_ABBREVIATIONS[$file->mode & MODE_TRANSLATE_MASK])
        .  " $path ... ");
      ob_flush(); flush();

      $file->setRevisions($this->revisions);

      $file->writeRCS($this->outFolder);
      print("Done.\n");
      ob_flush(); flush();
    }
  }
  
  function checkout($dir = false, $branches = false)
  {
    list($cvsroot, $module) = PathSplit($this->outFolder);

    $failed = array();
    $skipped = array();
    
    foreach(array_keys($this->revisions) as $i)
    {
      $revision =& $this->revisions[$i];
      list($p, $folder) = PathSplit($revision->directory);

      if (count($revision->tags))
      {
        if ($dir) 
        {
          $r = cvs_export($cvsroot, $module, $revision->tags[0], 
            "$dir/$folder", $revision->directory);
            
          if ($r) $failed[] = $folder;
        }
        else
        {
          $d = ShellEscape($folder);
          print("cvs co -r {$revision->tags[0]} -d $d $module\n");
        }
      }
      else
        $skipped[] = $folder;
        
      if ($branches && $revision->btag)
      {
        if ($dir)
          cvs_export($cvsroot, $module, $revision->btag, "$dir/{$revision->btag}");
        else
        {
          print("cvs co -r {$revision->btag} -d {$revision->btag} $module\n\n");  
        }
      }
    }
    
    if ($dir)
    {
      if (count($failed))
      {
        print(wordwrap("The import was not successful. The following exported "
          . "versions differ from the originals: " . implode(", ", $failed)
          . "." . (count($skipped) ? (" The following revisions were skipped: "
          . implode(", ", $skipped) . ".") : "") . "\n"));
      }
      else
      {
        print(wordwrap("No import errors were detected." . (count($skipped)
         ? (" However, the following untagged versions could not be "
         . "verified: " . implode(", ", $skipped) . ".") : "") . "\n"));
      }
      return (bool)count($failed);
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

  // If true, assigns sequential revision numbers that go in the same order
  // as the specified repository revisions, otherwise uses the repository 
  // revision numbers in this file even though gaps may be created by pruning
  var $collapseRevisions = true;
  
  // If true, allows a branch to be based an ancestor revision instead of a
  // parent revision if the parent revision was pruned. If collapseRevisions
  // is false, then this value doesn't have any effect.
  var $collapseBranches = true;

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

      //debugout("revision $key");
      foreach(array_keys($history) as $h)
      {
        $hr =& $history[$h];
        //@debugout("  $h => " . FileRevisionString($hr));
      }

      // find previous revision, or parent revision if previous is null
      $previous = $parent = null;

      if (isset($history[$depth]) && (!isset($history[$depth]->revision) 
        || RevisionIsSameBranch($revision->number,
        $history[$depth]->revision->number)))
        $previous =& $history[$depth];
        
      // parent should be set to closest non-ghost non-zero ancestor revision
      for ($i = $depth - 2; $i >= 2; $i -= 2)
      {
        if (isset($history[$i]) && !$history[$i]->isGhost 
          && !$history[$i]->isZero)
        {
          $parent =& $history[$i];
          assert(!isset($parent->revision) || RevisionIsSameBranch(
            $parent->revision->number, array_slice($revision->number, 0, $i)));
          break;
        }
      }

      //@debugout("  previous = " . FileRevisionString($previous) . " parent = " . FileRevisionString($parent));

      $newBranch = $depth != 2 && !isset($previous);

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
        if ($fileRev->changed)
        {
          if ($previous->isGhost)
          {
            $p = array();
            $this->realParent($parent, $history, $depth, $fileRev, $previous->btag, $p);
            $parent =& $p[0];
            $previous->attach($this->revisions, $parent, $null);
          }
          $fileRev->attach($this->revisions, $parent, $previous);
        }
        else
        {
          $previous->absorb($fileRev);
          unset($fileRev);
        }
      }
      else // !isset($previous)
      {
        if (!$fileRev->changed && $fileRev->canPrune && $fileRev->canPruneBranch)
          $fileRev->isZero = true;
       
        if ($fileRev->exists || $fileRev->tagDeletedBranch)
        {
          $p = array();
          $this->realParent($parent, $history, $depth, $fileRev, $fileRev->btag, $p);
          $parent =& $p[0];
          $fileRev->attach($this->revisions, $parent, $previous);
        }
        // otherwise fileRev becomes a "ghost" revision... 
        
        // don't add file that hasn't been created yet
        if ($fileRev->isGhost && $depth == 2)
          unset($fileRev);        
      }

      if (isset($fileRev))
      {
        $history[$depth] =& $fileRev;
        if ($depth == 2 && !$fileRev->isGhost) $this->head =& $fileRev;
        
        // if latest revision is on a vendor branch, set default branch
        if ($depth == 4 && ($revision->number[2] % 2 == 1) 
          && !$fileRev->isGhost && !$fileRev->isZero)
          $this->defaultBranch =& $fileRev;
      }

      // if latest revision is on trunk, don't set default branch
      if ($depth == 2 && isset($this->defaultBranch))
        unset($this->defaultBranch);

      unset($r, $previous, $parent, $compare);
    }

    //$this->display();
  }

  // this function is called to correct the $parent parameter when
  // attaching a revision at the head of a new branch. If there is no
  // $parent and the depth is greater than two than a placeholder
  // parent will be created. Otherwise, it only has an effect when
  // collapsed branch numbers are not allowed and $parent is a zero
  // or ghost node
  //
  // due to a strange bug in php 4.3 where the return value of this
  // function mysteriously gets transformed from a null value to an
  // empty array, a reference to the real parent is placed at $p[0]
  // so the return value doesn't have to be trusted
  function & realParent(&$parent, &$history, $depth, &$fileRev, $btag, &$ret)
  {
    if (!isset($parent) && $depth > 2)
    {
      assert($fileRev->exists);
      assert(!isset($history[2]));
      $parent =& new FileRevision($this, $null, $null, false);
      $parent->author = $fileRev->author;
      $parent->date = $fileRev->date;
      list($folder, $file) = PathSplit($this->path);
      $parent->log = "file $file was initially added on branch $btag.\n";
      $parent->attach($this->revisions, $null, $null);
      $history[2] =& $parent;
    }

    if ($this->collapseRevisions && $this->collapseBranches)
    {
      $ret[0] =& $parent;
      return $parent;
    }
      
    // not allowed to collapse branches, so go back and change
    // any zero or ghost ancestor revisions to full-fledged
    // revisions
    
    $collapsed = false; // only used for the assertion below
    //debugout("finding real parent...");
    for ($hkey = 2; $hkey < $depth; $hkey += 2)
    {
      $hval =& $history[$hkey];
      //@debugout("hval = " . FileRevisionString($hval));
      if ($hval->isGhost)
      {
        $hval->attach($this->revisions, $p, $null);
        $collapsed = true;
      } 
      else if ($hval->isZero)
      {
        $hval->unZero($p);
        $collapsed = true;
      }
      else
        assert(!$collapsed);
        
      $p =& $hval;    
    }
    
    $ret[0] =& $p;
    return $p;
  }

  function display()
  {
    print("\n\n\nFile = {$this->path}\n");
    foreach(array_keys($this->revisions) as $i)
    {
      $nr =& $this->revisions[$i];
      print(RevisionString($nr->number) . " => ");
      print(FileRevisionString($nr));
      print("\n");

      print("  properties:");
      print($nr->exists ? " exists" : " doesn't exist");
      print($nr->changed ? ", changed" : ", not changed");
      if ($nr->isZero) print(", zero");
      print("\n");

      print ("  tags: " . implode(" ", $nr->tags) . "\n");
      print ("  branches:");
      foreach(array_keys($nr->branches) as $j)
        print (" " . RevisionString($nr->branches[$j]->number));
      print("\n");
      if ($nr->btag)
        print("  branch tag: {$nr->btag}\n");
      print("  diff: " . (isset($nr->diff) ? RevisionString($nr->diff->number)
        : 'None') . "\n");
      print("  next: " . (isset($nr->next) ? RevisionString($nr->next->number)
        : 'None') . "\n");
      print("  contents: {$nr->contents}\n");
    }
  }

  function writeRCS($outdir)
  {
    if (!$this->head->exists)
    {
      list($folder, $file) = PathSplit($this->path);
      if (strlen($folder))
        $folder = "$outdir/$folder/Attic";
      else
        $folder = "$outdir/Attic";
      @mkdir($folder);
      $rcsfile = "$folder/$file,v";
    }
    else
      $rcsfile = "$outdir/$this->path,v";

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

      $rn = RevisionString($revision->number, $revision->isZero ? -2 : false);
      foreach($revision->tags as $tag)
        if ($revision->exists || $revision->tagDeleted)
          fwrite($fp, "\n\t$tag:$rn");

      if ($revision->isZero)
      {
        $d = count($revision->number);
        if ($revision->btag)
        {
          if ($revision->next)
            fwrite($fp, "\n\t$revision->btag:$rn." . $revision->number[$d-2]);
          else
            fwrite($fp, "\n\t$revision->btag:$rn.0." . $revision->number[$d-2]);
        }
      }
      else
      {
        if ($revision->btag)
          fwrite($fp, "\n\t$revision->btag:"
            . RevisionString($revision->number, -1));
      }
    }
    fwrite($fp,";\n");

    fwrite($fp, "locks\t;\nstrict\t;\ncomment\t@# @;\n");
    if (($this->mode & MODE_TRANSLATE_MASK) == MODE_BINARY)
      fwrite($fp, "expand\t@b@;\n");
    else if (!($this->mode & MODE_SUBSTITUTION))
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
        $branch =& $revision->branches[$j];
        if ($branch->isZero) continue;
        fwrite($fp, "\n\t" . RevisionString($branch->number));
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
            . RevisionString($revision->number) . " (" 
            . FileRevisionString($revision) . ")\n");
        fwrite($fp, "*** empty log message ***\n");
      }

      fwrite($fp, str_replace("@", "@@", $revision->log));
      fwrite($fp, "@\ntext\n@");

      if (isset($revision->diff))
      {
        if ($revision->diff->contents !== false)
          $f1 = shellescape($revision->diff->contents);
        else
          $f1 = $GLOBALS['BLANKFILE'];
          
        if ($revision->contents !== false)
          $f2 = shellescape($revision->contents);
        else
          $f2 = $GLOBALS['BLANKFILE'];
          
        $cmd = "diff -n -a --binary $f1 $f2";
        $pp = popen($cmd, "rb")
          or die("Failed to execute '$cmd' at " . __FILE__ . ':' . __LINE__);
      }
      else if ($revision->contents !== false)
        $pp = fopen($revision->contents, "rb");
      else
        $pp = false;
      
      if ($pp)
      {
        while(!feof($pp))
        {
          $buffer = fread($pp, 8192);
          fwrite($fp, str_replace('@','@@', $buffer));
        }
        if (isset($revision->diff)) pclose($pp); else fclose($pp);
      }
      fwrite($fp, "@\n\n");
    }
    fclose($fp);

    foreach($keys as $i)
    {
      if ($this->revisions[$i]->isTemporary)
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
    $this->isGhost = true;

    if (isset($revision))
    {
      $this->revision =& $revision;
      $path = "{$revision->directory}/{$file->path}";
  
      // find $exists, $date, $contents, $isTemporary
      if (file_exists($path))
      {
        $this->exists = true;
        $this->date = filemtime($path);
        $this->contents = TranslateFile($path, $file->mode,
          $this->isTemporary);

        if (!empty($compare->exists))
        {
          $f1 = shellescape($compare->contents);
          $f2 = shellescape($this->contents);
          $this->changed = (bool)`diff -q --binary $f1 $f2`;
        }
        else
          $this->changed = true;
      }
      else
      {
        $this->exists = false;
        $this->date = $revision->date;
        $this->contents = false;
        $this->isTemporary = false;
        $this->changed = !empty($compare->exists);
      }
  
      $this->tags = $revision->tags;
      $this->btag = $revision->btag;
      SetFileRevisionInfo($this, $key);
    }
    else
    {
      assert(empty($compare) && empty($key));
      $this->revision = null;
      $this->exists = false;
      $this->contents = false;
      $this->isTemporary = false; 
      $this->changed = false;
      $this->tags = array();
      $this->btag = false;
    }
  }

  // add to array of FileRevisions
  function attach(&$revisions, &$parent, &$previous)
  {
    assert(!isset($parent) || !$parent->isGhost);
    assert(!isset($previous) || !$previous->isGhost);
    
    if (!$this->file->collapseRevisions)
      $this->number = $this->revision->number;
    
    if (isset($previous))
    {
      $depth = count($previous->number);
      assert($depth % 2 == 0);
      if (!isset($this->number))
      {
        $this->number = $previous->number;
        ++$this->number[$depth - 1];

        // preserve first number
        if ($depth == 2 && isset($previous->revision) 
          && $previous->revision->number[0] != $this->revision->number[0])
          $this->number = array($this->revision->number[0], 1);
      }

      if ($depth == 2) // revision is on main trunk
      {
        // because this is the main trunk use a reverse diff
        // and point backwards to the previous revision
        $this->diff = null;
        $this->next =& $previous;
        $previous->diff =& $this;
      }
      else
      {
        $this->diff =& $previous;
        $this->next = null;
        $previous->next =& $this; 
      }
      if ($this->contents === false) $this->contents = $previous->contents;
      if ($previous->isZero) $parent->branches[] =& $this;
    }
    else // !isset($previous)
    {
      if (isset($parent))
      {
        $depth = count($parent->number);
        if (!isset($this->number))
        {
          $this->number = $parent->number;
          $n = $this->isZero ? 0 : 1;
          if ($this->revision->number[count($this->revision->number)-2]%2 == 0)
          {
            array_push($this->number, $parent->nextBranch, $n);
            $parent->nextBranch += 2;
          }
          else
          {
            array_push($this->number, $parent->nextVendorBranch, $n);
            $parent->nextVendorBranch +=2;
          }
        }
        $this->diff =& $parent;
        $this->next = null;
        if ($this->contents === false) $this->contents = $parent->contents;
        if (!$this->isZero) $parent->branches[] =& $this;
      }
      else
      {
        if (!isset($this->number))
        {
          $r = isset($this->revision) ? $this->revision->number[0] : 1;
          $this->number = array($r, 1);
        }
        $this->diff = null;
        $this->next = null;
      }
    }
    $revisions[] =& $this;
    $this->branches = array();
    $this->isGhost = false;
    $this->nextBranch = 2;
    $this->nextVendorBranch = 1;
    if (count($this->number) == 2)
      $this->file->head =& $this;
    //debugout("attaching " . FileRevisionString($this));
  }

  // absorb information from unchanged revision
  function absorb(&$rev)
  {
    assert($rev->btag === false);
    //@debugout(FileRevisionString($this) . " absorbing " . FileRevisionString($rev));
    array_splice($this->tags, count($this->tags), 0, $rev->tags);
  }
  
  // change this FileRevision from a zero revision to a nonzero revision
  function unZero(&$parent)
  {
    assert($this->isZero);
    assert(!isset($this->next));
    
    $this->isZero = false;
    
    $depth = count($this->number) - 1;
    assert($depth > 2);
    
    $this->diff =& $parent;
    $parent->branches[] =& $this;
    
    if ($this->number[$depth - 1] == 0)
    {
      $branch = $this->number[$depth - 2];
      $this->number = $parent->number;
      array_push($this->number, $branch, 1);
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
  return $d == 2 || array_slice($n1, 0, $d - 1) == array_slice($n2, 0, $d - 1);
}

function RevisionString($r, $depth = false)
{
  assert(count($r) % 2 == 0);
  $p = $depth === false ? $r : array_slice($r, 0, $depth);
  return implode('.', $p);
}

function FileRevisionString(&$r, $depth = false)
{
  if (isset($r))
  {
    if (!isset($r->revision)) return "PlaceHolder";
    return RevisionString($r->revision->number);
  }
  return "null";
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


function FileEntry(&$entries, $filename)
{
  $key = normcase($filename);
  
  if (isset($entries[$key])) return $key;
 
  foreach(array_keys($entries) as $re)
  {
    if (!is_string($re) || $re[0] != '/') continue;
    if (preg_match($re, $filename))
      return $re;
  }
  return null;
}

function FileRevisionEntry($entries, $revisionKey, $filename)
{
  if (!is_array($entries)) return $entries;
  $t =& $entries[$revisionKey]; 
  if (!is_array($t)) return $t;  

  $key = FileEntry($t, $filename);
  return isset($key) ? $t[$key] : null;
}

function LoadEntries($filename)
{
  $fp = fopen($filename, "rt");
  $rev = null;
  for (;;)
  {
    $line = readline($fp);
  }
  fclose($fp);
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

      if ($lastCR && $str[0] == "\n")
        $str = substr($str, 1);

      $lastCR = substr($str, -1) == "\r";

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

function normcase($path)
{
  global $Win32;
  return $Win32 ? strtolower($path) : $path;
}

function debugout($str)
{
  print("$str\n");
  ob_flush();
  flush();
}

function shellescape($arg)
{
  // on unix
  // return escapeshellarg($arg);
  return '"' . str_replace('"', '""', $arg) .  '"';
}

function array_append(&$a1, $a2)
{
  if (is_array($a1))
    array_splice($a1, count($a1), 0, $a2); 
  else
    $a1 = $a2;
}

function compare_dirs($dir1, $dir2)
{
  if (!is_dir($dir1) || !is_dir($dir2)) return true;
  
  $d1 = ShellEscape($dir1);
  $d2 = ShellEscape($dir2);
  
  $cmdline = "diff --binary -rq $d1 $d2";
  return (bool)`$cmdline`;
}

function del_dir($dir, $recurse = true)
{
  $handle = opendir($dir); 
  if (!$handle)
    die("failed to open '$dir' at " . __FILE__ . ':' . __LINE__); 

  while($file = readdir($handle))
  {
    if ($file == "." || $file == "..") continue;
    $path = "$dir/$file";
    if (is_dir($path))
    {
      if ($recurse) del_dir($path);
    }
    else
    {
      if (!unlink($path))
        die("failed to delete '$path' at " . __FILE__ . ':' . __LINE__);
    }
  }
  closedir($handle);
  if (!rmdir($dir))
  {
    die("failed to delete '$dir' at " . __FILE__ . ':' . __LINE__);
  }
}

function del_cvs_dirs($dir)
{
  $handle = opendir($dir); 
  if (!$handle)
    die("failed to open '$dir' at " . __FILE__ . ':' . __LINE__); 

  while($file = readdir($handle))
  {
    if ($file == "." || $file == "..") continue;

    $path = "$dir/$file";
    if (!is_dir($path)) continue;

    if ($file == "CVS")
      del_dir($path, false);
    else
      del_cvs_dirs($path);
  }
  closedir($handle);
}

function cvs_export($cvsroot, $module, $rev, $codir, $testdir = false)
{
  @mkdir("$cvsroot/CVSROOT");
  
  $dir = ShellEscape($codir);
  $d = ShellEscape(":local:$cvsroot");
  $cmd = "cvs -q -d $d export -r $rev -d $dir $module";
  print("$cmd\n"); ob_flush();
  passthru($cmd);
  del_cvs_dirs($codir);
  
  if ($testdir) return compare_dirs($codir, $testdir);
}

// strip options out of an argument list. unrecognized options
// are left in place, options specifed more than once assume
// later values
//
// $argv is the argument list, an array of strings
//
// $options is an array of arrays describing recognized options.
//   each option array contains the short name, name, number of
//   required arguments, default argument value, example:
//
//   array
//   (  array('v', 'verbose', 0, false),
//      array('f', 'file', 1, false)
//   )
//
// $values is the array where found options are put. it is indexed
//   by option name. If an option was given with one argument, the
//   corresponding entry be a string containing the argument. If the
//   option was given with many arguments, the entry will be an array
//   of strings. If the option was specified with no arguments, the
//   entry will be set to the PHP constant, true. And if the option was
//   not specified, the entry holds the option's default value.

function strip_options($argv, $options, &$values)
{
  $short_names = $long_names = array();
  $values = array();
  
  foreach ($options as $key => $opt)
  {
    list($name, $abbr, $nargs, $default) = $opt;
    $values[$name] = $default;
    
    assert(!isset($short_names[$abbr]));
    $short_names[$abbr] =& $options[$key];
    assert(!isset($long_names[$name]));
    $long_names[$name] =& $options[$key];
  }
  
  $ret = array();
  $skip = 0;
  foreach($argv as $arg)
  {
    if ($skip)
    {
      --$skip;
      if (is_array($skip_copy)) $skip_copy[] = $arg; else $skip_copy = $arg;
      continue;
    }
    
    if ($arg[0] == '-')
    {
      if (isset($short_names[$arg[1]]))
      {
        $option =& $short_names[$arg[1]];
        $value = strlen($arg) == 2 ? true : substr($arg, 2);
      }
      else if ($arg[1] == '-')
      {
        $pos = strrpos($arg, '=');
        $k = $pos === false ? substr($arg, 2) : substr($arg, 2, $pos - 2);
        $option =& $long_names[$k];
        $value = $pos === false ? true : substr($arg, $pos + 1);
      }
      
      if (isset($option))
      {
        list($name, $abbr, $nargs, $default) = $option;
        
        $skip = $nargs;
        $skip_copy =& $values[$name];

        if ($nargs > 0 && $value !== true) --$skip;
        
        if ($nargs > 1)
          $values[$name] = $value === true ? array() : array($value);
        else
          $values[$name] = $value;
        
        continue;
      }
    }
    
    $ret[] = $arg;
  }
  
  return $ret;
}

?>