<?

class RCSNode
{
  // public variables:
  
  var $directory; // folder containing that version
  var $version; // cvs style revision number of this version
  var $tag; // tag name to use for this revision
  var $btag; // branch tag name to use if this is the first node in a new branch
  var $log; // log entry to use for changes made in this revision
  var $date; // date to use for deletions in this version, false to use the date of the newest file
  var $author; // author string, false to use the default author
  
  // internal variables:
  
  var $state; 
  var $branches;
  var $next;
  var $previous;
  var $aversion; // version in array form
  
  function RCSNode($directory, $version, $tag, $btag, $log)
  {
    GLOBAL $DEFAULT_AUTHOR;
    
    $this->directory = $directory;
    $this->version = $version;
    $this->tag = $tag;
    $this->btag = $btag;
    $this->log = $log;
    $this->date = false;
    $this->author = $DEFAULT_AUTHOR;
    $this->state = false;
    $this->branches = array();
    $this->next = false;
    $this->previous = false;
  }
}

///////////////////////////////////////////////////////////////////////////////
/*                        BEGIN SCRIPT CUSTOMIZATIONS                        */

$DEFAULT_AUTHOR = "Administrator";

$VERSIONS = array
(
  new RCSNode("L:\temp\revisions\2001-01-03", "1.1", "OLD_2001-01-03", false, "WCES after Fall 2000 Evaluations"),
  new RCSNode("L:\temp\revisions\2001-01-15", "1.2", "OLD_2001-01-15", false, "Added PHP Based Oracle and Unstable Question Set Editor"),
  new RCSNode("L:\temp\revisions\2001-01-25", "1.1.2.1", "OLD_2001-01-03_with-oracle", "OLD_2001-01-03_branch", "Added PHP Based Oracle"),
  new RCSNode("L:\temp\revisions\2001-02-28", "1.3", "OLD_2001-02-28", false, "Restructuring. Moved some files around."),
  new RCSNode("L:\temp\revisions\2001-03-10", "1.4", "OLD_2001-03-10", false, "Fixes to the database and import code"),
  new RCSNode("L:\temp\revisions\2001-03-21", "1.5", "OLD_2001-03-21", false, "Changed capitalization of table names"),
  new RCSNode("L:\temp\revisions\2001-03-31", "1.6", "OLD_2001-03-31", false, "Fixes to oracle and professor reporting"),
  new RCSNode("L:\temp\revisions\2001-04-03", "1.7", "OLD_2001-04-03", false, "Changed professor question editor and added search to oracle"),
  new RCSNode("L:\temp\revisions\2001-04-20", "1.8", "OLD_2001-04-20", false, "Added professor listing, secure login. Opened for final evaluations"),
  new RCSNode("L:\temp\revisions\2001-04-22", "1.9", "OLD_2001-04-22", false, "Added TA questions"),
  new RCSNode("L:\temp\revisions\2001-04-30", "1.10", "OLD_2001-04-30", false, "Added mass mailing. Also some reporting fixes."),
  new RCSNode("L:\temp\revisions\2001-05-19", "1.11", "OLD_2001-05-19", false, "Added TA & ABET reporting.")
);

$VERSIONS = array
(
  new RCSNODE("","1.2","","",""),
  new RCSNODE("","1.2.2.1","","",""),
  new RCSNODE("","1.2.2.2","","",""),
  new RCSNODE("","1.2.2.3","","",""),
  new RCSNODE("","1.2.4.1","","",""),
  new RCSNODE("","1.2.4.2","","",""),
  new RCSNODE("","1.2.4.3","","",""),
  new RCSNODE("","1.2.4.2.2.1","","",""),
  new RCSNODE("","1.2.4.2.2.2","","",""),
);

function is_binary($filename)
{
  global $is_binary_endings;
  for($i = -3; $i >= -5; --$i)
    if (isset($is_binary_endings[substr($filename, $i)])) return false;
  return !ends_with($filename,".backup") && !ends_with($filename,".working");
}

$is_binary_endings = array
(
  ".html" => 1, ".php"  => 1, ".inc"  => 1, ".bak" => 1, ".asp" => 1, ".asa" => 1,
  ".~dfm" => 1, ".~dpr" => 1, ".~pas" => 1, ".cfg" => 1, ".cls" => 1, ".dfm" => 1,
  ".dof"  => 1, ".hack" => 1, ".eml"  => 1, ".js"  => 1, ".new" => 1, ".old" => 1,
  ".pas"  => 1, ".prj"  => 1, ".scc"  => 1, ".txt" => 1, ".vbp" => 1, ".vbw" => 1
);

/*                         END SCRIPT CUSTOMIZATIONS                         */
///////////////////////////////////////////////////////////////////////////////

function warning($warning)
{
  global $warned;
  $warned = true;
  print (wordwrap("WARNING: $warning\n\n"));
}

function ends_with($name, $ending)
{
  return substr($name, -strlen($ending)) === $ending;
}

function make_integer($n)
{
  return (int)$n;
}
function init_nodes(&$nodelist)
{
  usort($nodelist, "init_nodes_compare");

  $lastnodes = array();
  $lastdepth = 0;
  $head = false;
  
  for($i = 0; $i < count($nodelist); ++$i) // traverse the sorted list and fill in the next, previous, and branches members
  {
    // NOTE: This is actually not as complicated as it may look. Most of this
    // code consists of error checking. When you strip out the if branches
    // that lead only to warning() calls you wind up with something much
    // simpler.
 
    $nodelist[$i]->aversion = $currentnode = array_map("make_integer",explode(".", $nodelist[$i]->version));
    $currentdepth = count($currentnode);
    
    if ($currentdepth < 2)
      warning("\"" . $nodelist[$i]->version . "\" is not a valid revision numbers. Revision numbers be series of digits separated by dots like 1.2, 1.31, 1.33.5.73, or 44.56 . A revision number has to have at least one dot.");
    else if ($currentdepth % 2 != 0)
      warning("\"" . $nodelist[$i]->version . "\" is not a valid revision number. (Revision numbers must have an even number of segments like 1.2 or 1.2.2.3 and NOT like 1.2.3)");

    if ($currentdepth == 2) $head = $i;
    
    if($currentdepth > $lastdepth) // we are on a new branch
    {
      if (isset($lastnodes[$currentdepth - 2]))
      {
        $parentnode = $lastnodes[$currentdepth - 2];
        $nodelist[$i]->previous = $parentnode;
        $nodelist[$parentnode]->branches[] = $i;
      }
      else if ($currentdepth > 2)
        warning("\"" . $nodelist[$i]->version . "\" needs a parent node to branch off of. Either change its revision number or add in a new node (even an empty one will do) like a \"" . implode(".",array_slice($currentnode,0,$currentdepth-2)) . "\"" . ( ((int)$currentnode[$currentdepth-3]) != 1 ? " or a \"" . implode(".",array_slice($currentnode,0,$currentdepth-3)) . ".1\"": "") . ".");
    }
    else if (isset($lastnodes[$currentdepth]))
    {
      if ($currentdepth == 2) // reverse diffs (therefore reverse ordering) are used on the main trunk
      {
        $nodelist[$lastnodes[2]]->previous = $i; 
        $nodelist[$i]->next = $lastnodes[2];
      }
      else
      {
        $nodelist[$lastnodes[$currentdepth]]->next = $i; 
        $nodelist[$i]->previous = $lastnodes[$currentdepth];
      }
    }
    else
      warning("There is no node that precedes both \"" . $nodelist[$i]->version . "\" and \"" . $nodelist[$lastnodes[$lastdepth]]->version . "\". You need to either change your numbering or create one.");
      
    $lastnodes[$currentdepth] = $i;
    $lastdepth = $currentdepth;
  };
  
  if ($head !== false)
    return $head;
  else
    warning("The CVS is tree is all branches and no trunk! CVS requires that you have at least one revision on the trunk to serve as the \"HEAD\" revision which is what all the other diffs in the file are ultimately based on. If you are sure you do not want any of your revisions to be on the trunk, you need to create a 1.1 revision and point it to a blank directory. If you are trying to emulate the results of a fresh CVS import command (which puts the files on vendor revision 1.1.1.1 AND on the trunk) just do these things: 1)Create a new 1.1 revision. 2)Point it to the same directory as your 1.1.1.1 revision. 3)Set the log entry to \"Initial revision\". 4)The branch tag on the 1.1.1.1 revision is equivalent to the \"vendor-tag\" argument of the CVS import command, so set that however you like it. 5)Do not create a tag for the 1.1 revision. Instead of a string, put false.");
}

function init_nodes_compare($a, $b)
{
  $pa = $pb = 0;
  $la = strlen($a->version); $lb = strlen($b->version);
  for(;;)
  {
    $va = init_nodes_compare_get_next($a->version, $pa, $la);
    $vb = init_nodes_compare_get_next($b->version, $pb, $lb);
    if ($va !== $vb)
      return $va < $vb ? -1 : 1;
    else if ($pa >= $la && $pb >= $lb)
    {
      warning("Can't have two versions called \"" . $a->version . "\" and \"" . $b->version . "\". Give them different numbers.");
      return 0;
    }  
  }  
}

function init_nodes_compare_get_next($str, &$pos, $len)
{
  $f = true; // BOOLEAN, true when running through the loop for the first time
  $r = "";   // STRING, holds the return value
  for(;;)
  {
    // CHAR, holds the current character
    $c = $pos >= $len ? chr(0) : $str[$pos];

    // INTEGER, holds the ASCII value of the current character
    $o = ord($c);              
    
    // INTEGER, holds the ASCII value of the current character
    $d = 48 <= $o && $o <= 57; // BOOLEAN, true when the current character is a digit
  
    if (!$f && !$d) return (int) $r;

    $r .= $c;
    ++$pos;
    
    if ($f && !$d) return $r;
    $f = false;
  }
}

init_nodes($VERSIONS);

foreach($VERSIONS as $v)
{
  print("Revision $v->version\n");
  print("  Previous: " . ($v->previous !== false ? $VERSIONS[$v->previous]->version : "---" ) . "\n");
  print("  Next:     " . ($v->next     !== false ? $VERSIONS[$v->next    ]->version : "---" ) . "\n");
  print("  Branches: ");
  $first = true;
  foreach($v->branches as $b)
  {
    if ($first) $first = false; else print("\n            ");
    print($VERSIONS[$b]->version);
  }
  print("\n\n");
}

function SetFolder($folder = "")
{
  global $ROOT, $DATEROOT;
  
  $latest = 0;
  
  if ($folder) $folder .= "/";
  
  $folderobj = opendir("$ROOT/$folder") or die ("\n\nOh no! opendir(\"$ROOT/$folder\") failed!\n\n");
 
  while (($name = readdir($folderobj)) !== false)
  if ($name !== "." && $name !== "..")
  {
    $fname = "$folder$name";
    $cname = "$ROOT/$fname";
    $timestamp = 0;
    if (is_dir($cname))
      SetFolder($fname);
    else
    {
      //$timestamp = filemtime("$DATEROOT/$fname");
      $timestamp = filemtime($cname) + 1;
      print(date("r",$timestamp) . "\t  $cname\n");
      touch($cname,$timestamp);
    }
  }
}

?>