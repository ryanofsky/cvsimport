<?

///////////////////////////////////////////////////////////////////////////////
/*                        BEGIN SCRIPT CUSTOMIZATIONS                        */

function get_mode($filename)
{
  global $is_binary_endings;
  for($i = -4; $i >= -4; --$i)
    if (isset($is_binary_endings[substr($filename, $i)])) return MODE_BINARY;
  return MODE_SAFETEXT;
}

$is_binary_endings = array
(
  ".gif" => 1, ".jpg"  => 1
);

$DEFAULT_AUTHOR = "algore";

$VERSIONS = array
(
 "1.1" => new Revision("M:/temp/homeworks/os/1", "hw1-1",        "", "original shell.", "russ" ),
 "1.2" => new Revision("M:/temp/homeworks/os/2", "hw1-2",        "", "added pinfo", "osteam" ),
 "1.3" => new Revision("M:/temp/homeworks/os/3", "hw1-submit1",  "", "added readme's and patch", "osteam" ),
 "1.4" => new Revision("M:/temp/homeworks/os/4", "hw1-submit2",  "", "fixed readme", "osteam" ),
 "1.5" => new Revision("M:/temp/homeworks/os/5", "hw2-1",        "", "Imported nieh's user threading code", "nieh" ),
 "1.6" => new Revision("M:/temp/homeworks/os/6", "hw2-2",        "", "friday, oct 18", "osteam" )
);

$OUTDIR = "L:/server/shares/cvsroot/os";


function FileInfo($filename, &$revisions)
{
  
}

function FileRevisionInfo($filename, &$fileinfo, $revision, &$revisions)
{
  
}

?>