<?php

$dir      = __DIR__ . '\\..\\..';
$out      = __DIR__ . '\\..\\plugins\\';
$exclude  = [ '.', '..', '.git', 'umsp-repo' ];
$d        = dir($dir);
$x        = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
$single   = "<umspplugin>\n#####</umspplugin>\n";
$manifest = "<manifest>\n#####</manifest>\n";
$pre      = "<pre>\n#####</pre>\n";
$full     = '';
$list     = '';

while (false !== ( $entry = $d->read() )) {
         $current = $dir . '\\' . $entry;
    if (( array_search($entry, $exclude) === false )
    && ( filetype($current) == 'dir' )) {
        if (ask($entry)) {
            //create tgz package
            $cmd = "7z a -ttar \"$out$entry.tar\" \"$current\" -xr!ftpsync.settings -xr!*_url_extractor.php";
            shell_exec($cmd);

            $cmd = "7z a \"$out$entry.tgz\" \"$out$entry.tar\"";
            unlink("$out$entry.tgz");
            shell_exec($cmd);
            unlink("$out$entry.tar");
        }
        //generate xml
        $in = file_get_contents($current . '\\info.php');
        preg_match_all("/#\s*meta-(.+?)=\"(.+?)\"/", $in, $plugin);
        $xml = '';
        for ($i = 0; $i < count($plugin[0]); $i++) {
            $xml .= '    <' . $plugin[1][$i] . '>' . htmlspecialchars($plugin[2][$i]) . '</' . $plugin[1][$i] . '>' . PHP_EOL;
        }
        $xml   = str_replace('#####', $xml, $single);
        $full .= $xml;
        $list .= "$entry\n";
        file_put_contents("$out$entry.xml", "$x$xml");
    }
}
$d->close();

//save manifest
$full = str_replace('#####', $full, $manifest);
file_put_contents($out . '..\\manifest.xml', "$x$full");

//save php list
$list = str_replace('#####', $list, $pre);
file_put_contents($out . '..\\plugins.php', $list);

function ask($id)
{
    echo "Pack $id? y/n: ";
    $inp = trim(fgets(STDIN));
    if (preg_match('/^y$/i', $inp)) {
        return true;
    }return false;
}
