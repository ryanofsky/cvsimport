<?

class RCSNode
{
  // public variables:
  
  var $directory; // folder containing that srevision
  var $srevision; // cvs style revision number in string form
  var $tag; // tag name to use for this revision
  var $btag; // branch tag name to use if this is the first node in a new branch
  var $log; // log entry to use for changes made in this revision
  var $date; // date to use for deletions in this srevision, false to use the date of the newest file
  var $author; // author string, false to use the default author
  
  // internal variables:
  
  var $state; 
  var $branches;
  var $next;
  var $diff;
  var $posterior;
  var $ulterior;
  var $revision; // cvs revision as an array of integers
  var $diff;
  var $placeholder;
  
  function RCSNode($directory, $srevision, $tag, $btag, $log)
  {
    GLOBAL $DEFAULT_AUTHOR, $BLANKFILE;
    
    $this->directory = $directory;
    $this->srevision = $srevision;
    $this->tag = $tag ? (is_array($tag) ? $tag : array($tag)) : array();
    $this->btag = $btag;
    $this->log = $log;
    $this->date = false;
    $this->author = $DEFAULT_AUTHOR;
    $this->state = "";
    $this->branches = array();
    $this->next = false;
    $this->diff = false;
    
    $this->posterior = false;
    $this->ulterior = false;
    
    $this->revision = array_map("make_integer",explode(".", $srevision));

    $this->branchno = 0;
    $this->exists = false;
    $this->filename = $BLANKFILE;
    $this->placeholder = false;
    
    $depth = count($this->revision);
    if ($depth < 2)
      warning("\"$srevision\" is not a valid revision number. Revision numbers be series of digits separated by dots like 1.2, 1.31, 1.33.5.73, or 44.56 . A revision number has to have at least one dot.");
    else if ($depth % 2 != 0)
      warning("\"$srevision\" is not a valid revision number. (Revision numbers must have an even number of segments like 1.2 or 1.2.2.3 and NOT like 1.2.3)");
    else if (in_array(0,$this->revision,true))
      warning("\"$srevision\" is not a valid revision number. None of the numbers in a revision numbers can be zeros.");
 
    foreach($this->tag as $tag)
    if (strlen($tag) > 0 && !valid_tag($tag))
      warning("Revision \"$srevision\" has an invalid tag. A tag must begin with a letter and can be followed by letters, numbers, hypens, and underscores. Two reserved words \"BASE\" and \"HEAD\" cannot be used as tag names.");  
    
    if (strlen($btag) > 0 && !valid_tag($btag))
      warning("Revision \"$srevision\" has an invalid branch tag. A tag must begin with a letter and can be followed by letters, numbers, hypens, and underscores. Two reserved words \"BASE\" and \"HEAD\" cannot be used as tag names.");  
  }
}

class DummyNode // used as a placeholder in NodeList::PrepareNodes
{
  var $exists;
  var $filename;
  var $date;

  function DummyNode()
  {
    global $BLANKFILE;
    $this->exists = false;
    $this->filename = $BLANKFILE;
    $this->date = 0;
  }
}

class NodeList
{
  var $nodes;   // array of nodes
  var $files;   // associative array of all files that ever existed in the project. file paths are indices
  var $folders; // associative array of all folders that ever existed in the project. folder paths are indices
  
  var $defaultbranch; // array index of the head of the RCS default branch

  var $prune; // if set to true, new revision numbers will only be assigned to a file when it has been modified

  // PUBLIC FUNCTIONS
  
  function NodeList($nodes)
  {
    $this->prune = true;

    usort($nodes, "node_compare");
  
    $history = array();
    $this->defaultbranch = false;
    
    for($i = 0; $i < count($nodes); ++$i) // traverse the sorted list and fill in the next, diff, and branches members
    {
      $srevision = $nodes[$i]->srevision;
      $revision = $nodes[$i]->revision;
      $depth = count($revision);
      
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
          $nodes[$i]->next = $pr;       // point backwards to the previous revision
          $nodes[$i]->posterior = $pr;
          $nodes[$pr]->ulterior = $i;
        }  
      }
      else if($pr !== false && array_eq($revision,$nodes[$pr]->revision,$depth-1))
      {
        $nodes[$i]->diff = $pr;    // use a forward diff on a branch
        $nodes[$pr]->next = $i;        // previous node points to this one
        $nodes[$i]->posterior = $pr;
        $nodes[$pr]->ulterior = $i;
      }
      else if($pa === false) // problemo
        warning("\"$srevision\" needs a parent node to branch off of. Either change its revision number or add in a new node (even an empty one will do) like a \"" . implode(".",array_slice($revision,0,$depth-2)) . "\"" . ( $revision[$depth-3] != 1 ? " or a \"" . implode(".",array_slice($revision,0,$depth-3)) . ".1\"": "") . ".");
      else // this is a new branch
      {
        $nodes[$i]->diff = $pa;
        $nodes[$pa]->branches[] = $i;
        $needs_branch_tag = true;
        $nodes[$i]->posterior = $pa;
      }
      
      if (!$needs_branch_tag && strlen($nodes[$i]->btag) > 0)
        warning("You supplied a branch tag for revision \"$srevision\" that cannot be used because this revision is not at the head of a new branch. You need to set it to the empty string (\"\") or false.");
      else if ($needs_branch_tag && strlen($nodes[$i]->btag) == 0)
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
      print("  Ulterior:   " . ($v->ulterior !== false ? $this->nodes[$v->ulterior ]->srevision : "---" ) . "\n");
      print("\n");
    }  
  }
  
  function ScanFolders($node = false, $folder = "")
  {
    global $OUTDIR;
    if ($node === false)
    {
      for($i=0; $i < count($this->nodes); ++$i)
      {
        $date = $this->ScanFolders($i);
        if ($this->nodes[$i]->date === false) $this->nodes[$i]->date = $date;
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
    reset ($this->files);
    while (list($filename) = each($this->files))
    {
      $fnodes = $this->nodes;
      $isbinary = is_binary(substr($filename,1));
      print(($isbinary ? "B" : "A") .  " $filename ... ");
      $this->preparenodes($filename, $isbinary, $fnodes, $head);
      $this->writeRCS($filename, $isbinary, $fnodes, $head);
      print("Done.\n");
    }
  }

  // PRIVATE HELPER FUNCTIONS
  
  function preparenodes($filename, $isbinary, &$fnodes, &$head) // set up nodes for a particular file
  {
    $sfn = substr($filename,1);
    $keys = array_keys($fnodes);
    foreach($keys as $i)
    {
      $node = &$fnodes[$i];
      $depth = count($node->revision);
      
      $node->useme4tag = $node->taglist[$sfn];
      //print("for '$sfn' revision $i tag = '$node->useme4tag'\n");

      // establish $pnode (reference to posterior node) and $node->nrevision (new revision number)
      if($node->posterior === false)
      {
        $pnode = new DummyNode();
        $node->nrevision = array($node->revision[0],1);
      }
      else
      {
        $pnode = &$fnodes[$node->posterior];
        $node->nrevision = $pnode->nrevision;
        if($node->btag)
        {
          $node->nrevision[] = $pnode->branchno + ((($pnode->branchno % 2 == 0) xor ($node->revision[$depth-2] % 2 == 0)) ? 1 : 2);
          $node->nrevision[] = 1;
        }
        else
          ++$node->nrevision[count($node->nrevision)-1];
      }  
      
      // can this node be eliminated? is it redundant?
      $keep = false; 
      if (file_exists($node->directory . $filename))
      {
        $node->exists = true;
        $node->filename = $node->directory . $filename;
        $node->date = filemtime($node->filename);
        if ($pnode->exists)
        {
          $b = $isbinary ? "--binary" : "";
          $f1 = $pnode->filename;
          $f2 = $node->filename;
          if (`diff -q $b "$f1" "$f2"`) $keep = true;
        }
        else
          $keep = true;  
      }
      else // file_not_exists
      {
        $node->filename = $pnode->filename; // the diff for a deleted file tells what the file looked like before it was deleted
        if ($pnode->exists) $keep = true;
      }  
    
      if ($node->posterior === false && !$keep && count($node->branches) > 0)
      {
        $keep = true;
        $node->placeholder = true; // can't delete this node unless all branch nodes are deleted
      }  
    
      if ($node->btag && $node->exists)
        $keep = true;
      
      if (!$this->prune) $keep = true;
                  
      if($keep)
      {
        $node->state = $node->exists ? "Exp" : "dead";
        if ($node->posterior === $node->next) // cheesy way to see if the node is on the main thrunk
          $head = $i;
      }
      else // this is a redundant node that will be deleted
      {
        if($node->btag)
        {
          if ($node->ulterior === false && $pnode->placeholder && count($pnode->branches) == 1)
          {
            $this->deletenode($fnodes,$node->posterior);
            $node->posterior = false;
            unset($pnode);
          }  
          else
          {
            $f = array_search($i,$pnode->branches);
            if ($node->ulterior !== false)
            {
              $pnode->branches[$f] = $node->ulterior;
              $fnodes[$node->ulterior]->btag = $node->btag;
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
  
  function deletenode(&$fnodes, $i)
  {
    $node = &$fnodes[$i];
    
    if ($node->next !== false)
      $fnodes[$node->next]->diff = $node->diff;
      
    if (!$node->btag && $node->diff !== false)
      $fnodes[$node->diff]->next = $node->next;
    
    if ($node->ulterior !== false)
      $fnodes[$node->ulterior]->posterior = $node->posterior;
      
    if (!$node->btag && $node->posterior !== false)  
      $nodes[$node->posterior]->ulterior = $node->ulterior;
    
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
  
  function writeRCS($filename, $isbinary, &$fnodes, $head)
  {
    global $OUTDIR, $DEFAULT_AUTHOR, $NODE_BODY_COMPARE_LIST;
    
    if (!$fnodes[$head]->exists)
    {
      $p = strrpos($filename,"/");
      $folder = substr($filename,0,$p) . "/Attic";
      @mkdir("$OUTDIR$folder",0777);
      $filename = "$folder/" . substr($filename,$p+1);
    }
    
    $fp = @fopen("$OUTDIR$filename,v","wb") or die("Failed to open \"$OUTDIR$filename,v\" for writing in " . __FILE__ . " on line " . __LINE__);
    $keys = array_reverse(array_keys($fnodes));

    fwrite($fp, "head\t" . implode(".",$fnodes[$head]->nrevision) . ";\n");
    if ($this->defaultbranch !== false && isset($fnodes[$this->defaultbranch]))
      fwrite($ftp, "branch\t" . implode(".",$fnodes[$this->defaultbranch]->nrevision) . ";\n");
  
    fwrite($fp, "access\t;\n");
    fwrite($fp, "symbols\t");

    //foreach($keys as $i)
    //{
    //  if ($fnodes[$i]->exists) // it should not be strictly neccessary to skip tags for dead files, but this seems to conform with observed cvs behavior
    //  foreach($fnodes[$i]->tag as $tag)
    //  if (strlen($tag) > 0)  
    //    fwrite($fp, "\n\t$tag:" . implode(".", $fnodes[$i]->nrevision));
    //  if (strlen($fnodes[$i]->btag) > 0)
    //    fwrite($fp, "\n\t" . $fnodes[$i]->btag . ":" . implode(".", array_slice($fnodes[$i]->nrevision,0,-1)));
    //}    
    fwrite($fp,";\n");  

    fwrite($fp, "locks\t;\nstrict\t;\ncomment\t@# @;\n");
    if ($isbinary) fwrite($fp, "expand\t@b@;\n");
    fwrite($fp, "\n\n");

    foreach($keys as $i)
    {
      fwrite($fp, implode(".", $fnodes[$i]->nrevision) . "\n");
      fwrite($fp, "date\t" . gmdate("Y.m.d.H.i.s",$fnodes[$i]->date) . ";\tauthor " . $DEFAULT_AUTHOR . ";\tstate " . $fnodes[$i]->state . ";\n");
      fwrite($fp, "branches\t");
      foreach($fnodes[$i]->branches as $branch)
        fwrite($fp, "\n\t" . implode(".", $fnodes[$branch]->nrevision));
      fwrite($fp, ";\n");
      
      $next = $fnodes[$i]->next === false ? "" :implode(".", $fnodes[$fnodes[$i]->next]->nrevision);
      fwrite($fp, "next\t$next;\n\n");
    }
    fwrite($fp, "desc\n@@\n\n");
    
    if ($isbinary)
    {
      $search = "@";
      $replace = "@@";
    }
    else
    {
      $search = array("@","\r\n");
      $replace = array("@@","\n");
    }
    
    $NODE_BODY_COMPARE_LIST = $fnodes;
    usort($keys, "node_body_compare");
    $lastdate = 0;
    foreach($keys as $i)
    {
      if ($fnodes[$i]->useme4tag && $fnodes[$i]->date > $lastdate)
      {
        $lastdate = $fnodes[$i]->date;
      }  
      
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
        //fwrite($fp, str_replace("@", "@@", $fnodes[$i]->log));
        fwrite($fp, str_replace("@", "@@", $fnodes[$i]->useme4tag));
        
      fwrite($fp, "@\ntext\n@");

      if ($fnodes[$i]->diff !== false)
        $pp = popen("diff -n -a " . ($isbinary ? "--binary " : "") . "\"" . $fnodes[$fnodes[$i]->diff]->filename . " \" \"" . $fnodes[$i]->filename . "\"","rb");
      else
        $pp = fopen($fnodes[$i]->filename,"rb");  

      $leftovers = "";
      while(!feof($pp))
      {
        $buffer = fread($pp, 4096);
        if (!$isbinary && substr($buffer,-1) == "\r")
        {
          $buffer = substr($buffer,0,-1);
          $scrap = "\r";
        }
        else
          $scrap = "";
        fwrite($fp, str_replace($search,$replace,$leftovers . $buffer));    
        $leftovers = $scrap;
      }

      if ($fnodes[$i]->diff !== false) pclose($pp); else fclose($pp);
      fwrite($fp, "$leftovers@\n\n");
    }
    fclose($fp); 
    print("\n                              touch  -d \"" . date("Y-m-d H:i:s",$lastdate) . "\" " . substr($filename,1) . ",v\n");        
    touch("$OUTDIR$filename,v",$lastdate);
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

function array_eq($a, $b, $depth)
{
  return array_slice($a,0,$depth) == array_splice($b,0,$depth);
}

///////////////////////////////////////////////////////////////////////////////
/*                        BEGIN SCRIPT CUSTOMIZATIONS                        */

$DEFAULT_AUTHOR = "russ";

$VERSIONS = array
(
  new RCSNode("S:/lostcities/backup/0010"    , "1.01"    , "T0010" , "", ""),
  new RCSNode("S:/lostcities/backup/0018"    , "1.02"    , "T0018" , "", ""),
  new RCSNode("S:/lostcities/backup/0019"    , "1.03"    , "T0019" , "", ""),
  new RCSNode("S:/lostcities/backup/0020"    , "1.04"    , "T0020" , "", ""),
  new RCSNode("S:/lostcities/backup/0030"    , "1.05"    , "T0030" , "", ""),
  new RCSNode("S:/lostcities/backup/0040"    , "1.06"    , "T0040" , "", ""),
  new RCSNode("S:/lostcities/backup/0050"    , "1.07"    , "T0050" , "", ""),
  new RCSNode("S:/lostcities/backup/0060"    , "1.08"    , "T0060" , "", ""),
  new RCSNode("S:/lostcities/backup/0070"    , "1.09"    , "T0070" , "", ""),
  new RCSNode("S:/lostcities/backup/0080"    , "1.10"    , "T0080" , "", ""),
  new RCSNode("S:/lostcities/backup/0090"    , "1.11"    , "T0090" , "", ""),
  new RCSNode("S:/lostcities/backup/0100"    , "1.12"    , "T0100" , "", ""),
  new RCSNode("S:/lostcities/backup/0110"    , "1.13"    , "T0110" , "", ""),
); 

// 459 log write

$foldernames = array();

$fp = fopen ("S:/lostcities/backup/out.csv","r");
$nodeidx = array();
for($i =0; $data = fgetcsv ($fp, 8192, ","); ++$i)
{
  if ($i == 0)
  {
    foreach($data as $k => $v)
    {
      print("head = $k => $v\n");
      if ($k != 0)
      {
        foreach(array_keys($VERSIONS) as $vkey)
        {
          //print ("tag = " . $VERSIONS[$vkey]->tag[0] . "\n");
          if ($VERSIONS[$vkey]->tag[0] == "T$v")
          {
            $nodeidx[$k] = $vkey;
            $VERSIONS[$vkey]->taglist = array();
            break;
          }
        }
      }
    }
    continue;
  }
  $filename = $data[0];
  foreach($data as $k => $v)
  {
    if ($k == 0) continue;
    $VERSIONS[$nodeidx[$k]]->taglist[$filename] = $v;
    //print("revision $nodeidx[$k] $filename = '$v'\n");
  } 
}
$OUTDIR = "S:/lostcities/backup/out";

function is_binary($name)
{
  return false;
}

/*                         END SCRIPT CUSTOMIZATIONS                         */
///////////////////////////////////////////////////////////////////////////////


// function abc($ra,$rb)
// {
//   for($i=0;;++$i)                           
//   {                                         
//     if ($i > 1) $m = 1;                     
//     if(isset($ra[$i]))                      
//     {                                       
//       if (isset($rb[$i]))                   
//       {                                     
//         if ($ra[$i] == $rb[$i]) continue;   
//         return $ra[$i] < $rb[$i] ? -$m : $m;
//       }                                     
//       else                                  
//         return $m;                          
//     }                                       
//     else if (isset($rb[$i]))                
//       return -$m;                           
//     else                                    
//       return 0;                             
//   }                                         
// }
// 
// function abcd($a)
// {
//   print( $a . "\n");
// }
//   
// abcd(abc(  array(1,4)      ,  array(1,4,2,1)   ));
// abcd(abc(  array(1,4,2,1)  ,  array(1,4    )   ));
// abcd(abc(  array(1,6)      ,  array(1,6,2,1)   ));
// abcd(abc(  array(1,6,2,1)  ,  array(1,6    )   ));
  
$BLANKFILE = tempnam("","");

$nl = new NodeList($VERSIONS);
//$nl->display();
if ($WARNED)
  print("\n\nWarnings have been issued. Please correct any problems and try again.\n\n");
else
{
  $nl->ScanFolders();
  $nl->save();
} 

//
//$filename = "/include/VHGraph1_0/class.graph1";
//$fnodes = $nl->nodes;
//$isbinary = false;
//print(($isbinary ? "B" : "A") .  " $filename ... \n");
//$nl->preparenodes($filename, $isbinary, $fnodes, $head);
//$nl->writeRCS($filename, $isbinary, $fnodes, $head);
//
//print("\nDone.\n");
//
 
unlink($BLANKFILE);
    
?>