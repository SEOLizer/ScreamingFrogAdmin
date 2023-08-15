<?php

$config = [];
error_reporting(E_ERROR);

function escapeFilename($filename) {
#  $filename = str_replace(" ","%20",$filename);
#  $filename = str_replace("(","\(",$filename);
#  $filename = str_replace(")","\)",$filename);
#  $filename = str_replace("#","\#",$filename);
  return $filename;
}

function findDerbyDBString($dir) {
  $conf = file_get_contents($dir . "/.metadata/.plugins/org.eclipse.core.runtime/.settings/org.knime.database.prefs");
  $lines = explode("\n",$conf);
  $pathlist = [];
  foreach($lines as $line) {
    $data = explode("=",$line);
    $dp = explode("/",$data[0]);
    if ($dp[2] == 'paths') $pathlist[] = $data[0];
  }
  $derbyfound = false;
  $derbypath = '';
  $id = '';
  foreach($lines as $line) {
    $data = explode("=",$line);
    if (in_array($data[0],$pathlist)) {
      if (basename($data[1]) == 'derby.jar') {
	$derbyfound = true;
	$dp = explode("/",$data[0]);
	$id = $dp[1];
        $derbypath = $data[1];
      }
    }
  }
  return $id;
}

function addDerbySettings($knime_workDir,$driver_dir) {
  $id = 'derby';
  $d  = "drivers/" . $id . "/database_type=default\n";
  $d .= "drivers/" . $id . "/description=\n";
  $d .= "drivers/" . $id . "/driver_class=org.apache.derby.iapi.jdbc.AutoloadedDriver\n";
  $d .= "drivers/" . $id . "/paths/0=" . $driver_dir . "\n";
  $d .= "drivers/" . $id . "/url_template=jdbc\:<protocol>\://<host>\:<port>/<database>\n";
  $d .= "drivers/" . $id . "/version=10.16.0\n";
  $d .= "eclipse.preferences.version=1\n";
  file_put_contents($knime_workDir . "/.metadata/.plugins/org.eclipse.core.runtime/.settings/org.knime.database.prefs",$d,FILE_APPEND);
  echo($knime_workDir . "/.metadata/.plugins/org.eclipse.core.runtime/.settings/org.knime.database.prefs\n");
  echo("Derby-Driver installed. Make sure you restart KNIME\n");
}

function installDerby($knime_workDir) {
  mkdir("db-driver/");
  $tmpfile = 'db-driver/tmp.tmp';
  $url = 'https://dlcdn.apache.org//db/derby/db-derby-10.16.1.1/db-derby-10.16.1.1-bin.zip';
  if (file_put_contents($tmpfile, file_get_contents($url))) {
    echo "File downloaded successfully\n";
    $dir = 'derby';
    $oldDir = getcwd();
    chdir("db-driver");
    shell_exec("unzip tmp.tmp -d " . $dir . "/");
    unlink("tmp.tmp");
    chdir('..');    
    $ddir = $_SERVER["HOME"] . '/ScreamingFrogAdmin/db-driver/derby/db-derby-10.16.1.1-bin/lib/derby.jar';
    addDerbySettings($knime_workDir,$ddir);
  } else echo "File downloading failed.\n";  
}

function checkDerbyDriver($dir,$installOnly = false) {
  echo("Check Derby-Driver...\n");
  $id = findDerbyDBString($dir);
  if ($id == '') {
    echo("Derby-Driver not found. Do you want to install it now? (Y/n)");
    $a = trim(fgets(STDIN));
    if (($a == '') || ($a == 'Y')) installDerby($dir);
  } else {
    echo("Derby-Driver already installed.\n");
  }
  if (!$installOnly)
    return $id;
}

function rrmdir($dir) {
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
           rrmdir($dir. DIRECTORY_SEPARATOR .$object);
         else
           unlink($dir. DIRECTORY_SEPARATOR .$object);
       }
     }
     rmdir($dir);
   }
}

function checkSpiderConfig($dir) {
  $dat = file_get_contents($dir . '/spider.config');
  $dat = explode("\n",$dat);
  $dbmode = false;
  foreach($dat as $line) {
    $d = explode("=",$line);
    if (($d[0] == "storage.mode") && ($d[1] == "DB")) {
      $dbmode = true;
      break;
    }
  }
  return $dbmode;
} 

function findDerbyDir($crawlDir) {
  $str = '';
  foreach(scandir($crawlDir) as $file) if (substr($file,0,8) == 'results_') $str = $crawlDir . $file . "/sql";
  return $str;
}

function randomName() {
    $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
    $pass = '';
    for ($i = 0; $i < 8; $i++) {
        $n = rand(0, strlen($alphabet)-1);
        $pass = $pass . substr($alphabet,$n,1);
    }
    return $pass;
}

function setupKNIMEWorkflow($frogDir,$knimeDir) {
  if (file_exists("tmp/tmp.tmp")) {
    $dir = randomName();
    $oldDir = getcwd();
    chdir("tmp");
    shell_exec("unzip tmp.tmp -d " . $dir . "/");
    chdir($dir);
    $setupvars = [];
    $setupdata = [];
    $setupfile = file_get_contents("setup.ini");
    $lines = explode("\n",$setupfile);
    foreach($lines as $line) $setupdata[] = explode(":",$line); 
    foreach($setupdata as $line) if (strpos($line[0], '{%') !== false) {
      $e = explode("=",$line[0]);
      echo($e[0] . ": ");
      if ($line[1] == 'SFLOOKUP') {
	$var = [];
        $var['name'] = $e[0];
	$var['var'] = $e[1];
        $var['value'] = getCrawlStr($frogDir);
	$var['changes'] = escapeFilename($line[2]);
        $setupvars[] = $var;
      } else if ($line[1] == 'dblookup/derby') {
	$var = [];
        $var['name'] = $e[0];
        $var['var'] = $e[1];
        $var['value'] = checkDerbyDriver($knimeDir);
        $var['changes'] = escapeFilename($line[2]);
        $setupvars[] = $var;
        checkDerbyDriver($dir);
      } else {
	$var = [];
        $var['name'] = $e[0];
        $var['var'] = $e[1];
        $var['value'] = trim(fgets(STDIN));
        $var['changes'] = escapeFilename($line[2]);
        $setupvars[] = $var;
      }
      echo("\n");
    }
    $projectname = '';
    foreach($setupvars as $var) {
      if ($var["changes"] != '') {
        if (file_exists($var["changes"])) {
          $dat = file_get_contents($var["changes"]);
	  $dat = str_replace($var["var"],$var["value"],$dat);
	  file_put_contents($var["changes"],$dat);
        } else echo("file not found: " . $var["changes"] . "\n");
      }
      if ($var["name"] == "Projektname") $projectname = $var["value"];
    }
    chdir("..");
    rename($dir,$knimeDir . "/" . $projectname);
    echo("KNIME Workplace added. Refresh KNIME Workplace view to update it.\n");
    chdir($oldDir);
  }
}

function downloadKNIMEWorkflow($id,$frogDir,$knimeDir) {
  mkdir("tmp/");
  $tmpfile = 'tmp/tmp.tmp';
  if (file_put_contents($tmpfile, file_get_contents('https://app.seolizer.de/knimeRepro/?action=download&id=' . $id))) {
    echo "File downloaded successfully\n";
    setupKNIMEWorkflow($frogDir,$knimeDir);
  } else echo "File downloading failed.\n";
}

function readKNIMERepro($frogDir,$knimeDir) {
  $repData = file_get_contents('https://app.seolizer.de/knimeRepro/');
  $j = json_decode($repData,true);
  echo("-------------------------------\n");
  echo("SEOLizer KNIME-Workflow-Repository\n");
  echo("-------------------------------\n");
  echo("Nr.\t\tTitle\n");
  $datList = [];
  foreach($j as $w) {
    echo($w["id"] . "\t\t" . $w["title"]."\n");
    $datList[] = $w;
  }
  echo("KNIME-Repro:");
  $id = trim(fgets(STDIN));
  if (is_numeric($id)) {
    if ($id != 0) {
      $c = 0;
      foreach($datList as $w) {
        $c++;
        if ($w["id"] == $id) {
          echo("Workflow-Download:\n");
          echo("Title: " . $w["title"] . "\n");
          downloadKNIMEWorkflow($id,$frogDir,$knimeDir);
        }
      }
    }
  }  
}

function readCrawlData($crawlDir) {
  $data = [];   
  $data['crawlDir'] = $crawlDir;
  $crawlData = file_get_contents($crawlDir . "DbSeoSpiderFileKey");
  $lines = explode("\n",$crawlData);
  foreach($lines as $line) {
    $dat = explode("=",$line);
    if (count($dat) > 1) {
      $data[$dat[0]] = stripcslashes($dat[1]);
    };
    if (substr($line,0,1) == '#') {
      $data['date'] = substr($line,1,strlen($line));
    }
  }
  return $data;
}

function getCrawlStr($frogDir,$id = '') {
  $str = '';
  $crawlList = scandir($frogDir);
  $datList = [];
  foreach($crawlList as $crawl) if (substr($crawl,0,1) != '.') $datList[] = readCrawlData($frogDir . $crawl . "/");
  if ($id == "") {
    $c = 0;
    foreach($datList as $crawl) {
      $c++;
      echo($c . " " . $crawl["url"] . " - " . $crawl["date"] . "\n");
    }
    echo("----------------------------\n");
    echo("Crawl-Nummer: ");
    $id = trim(fgets(STDIN));
  }
  if ($id != '') {
    $c = 0;
    foreach($datList as $crawl) {
      $c++;
      if ($c == $id) $str = "jdbc:derby:" . findDerbyDir($crawl["crawlDir"]);
    }
  }
  return $str;
}

function getCrawls($frogDir,$id = '') {
  $crawlList = scandir($frogDir);
  $datList = [];
  foreach($crawlList as $crawl) if (substr($crawl,0,1) != '.') $datList[] = readCrawlData($frogDir . $crawl . "/");
  if ($id == "") {
    $c = 0;
    foreach($datList as $crawl) {
      $c++;
      echo($c . " " . $crawl["url"] . " - " . $crawl["date"] . "\n");
    }
    echo("----------------------------\n");
    echo("Crawl-Nummer: ");
    $id = trim(fgets(STDIN));
  }
  if ($id != '') {
    $c = 0;
    foreach($datList as $crawl) {
      $c++;
      if ($c == $id) {
        echo("Crawldaten:\n");
        echo("URL: " . $crawl["url"] . "\n");
        echo("Datum: " . $crawl["date"] . "\n");
        $derbyurl = findDerbyDir($crawl["crawlDir"]);
        echo("Derby-Verbindungsstring: jdbc:derby:" . $derbyurl . "\n");
      }
    }
  }
}

function exportCrawls($frogDir,$id = '',$removeCrawl = false) {
  $crawlList = scandir($frogDir);
  $datList = [];
  foreach($crawlList as $crawl) if (substr($crawl,0,1) != '.') $datList[] = readCrawlData($frogDir . $crawl . "/");
  if ($id == "") {   
    $c = 0; 
    foreach($datList as $crawl) {
      $c++;
      echo($c . " " . $crawl["url"] . " - " . $crawl["date"] . "\n");
    }
    echo("----------------------------\n");
    echo("Crawl-Nummer: ");
    $id = trim(fgets(STDIN));
  }
  if ($id != '') {
    $c = 0;
    foreach($datList as $crawl) {
      $c++;
      if ($c == $id) {
        echo("Crawldaten:\n");
        echo("URL: " . $crawl["url"] . "\n");
        $exportFileName = str_replace('/','',str_replace('.','-',str_replace('://','-',$crawl['url']))) . '.dbseospider';
        echo("Datum: " . $crawl["date"] . "\n");
        $derbyurl = findDerbyDir($crawl["crawlDir"]);
        echo("ExportFilename: " . $exportFileName . "\n");
        $oldDir = getcwd();
        chdir($crawl['crawlDir']);
        shell_exec('zip -r ' . $oldDir . '/' . $exportFileName . ' *');
        chdir($oldDir);
        if ($removeCrawl) rrmdir($crawl["crawlDir"]);
      }
    }
  }   
}


function getSFDir() {
  $dir = '';
  if (file_exists($_SERVER["HOME"] . '/.ScreamingFrogSEOSpider/machine-id.txt')) {
    $dir = $_SERVER["HOME"] . '/.ScreamingFrogSEOSpider';
  } else {
    echo('Could not determine the Screaming Frog directory. Please enter it manually: ');
    $dir = trim(fgets(STDIN));
    echo("\n");
  }
  return $dir;
}

function getKNIMEDir() {
  $dir = '';
  if (file_exists($_SERVER["HOME"] . '/knime-workspace/.metadata/version.ini')) {
    $dir = $_SERVER["HOME"] . '/knime-workspace';
  } else { 
    echo('Could not determine the KNIME Workplace directory. Please enter it manually: ');
    $dir = trim(fgets(STDIN));
    echo("\n");
  }
  return $dir;
}

function saveConfig($config) {
  file_put_contents($_SERVER["HOME"] . '/.sfconf.ini', json_encode($config));
}

function readConfig() {
  if (file_exists($_SERVER["HOME"] . '/.sfconf.ini'))
    $config = json_decode(file_get_contents($_SERVER["HOME"] . '/.sfconf.ini'),true);
  return $config;
}

function printHelp() {
  echo("Commands:\n\n");
  echo("derby = Check Derby-DB-Driver and/or install driver\n");
  echo("getdata = List crawls and return crawl-meta-data\n");
  echo("export = Export and remove crawl\n");
  echo("licence = list licence informationen\n");
  echo("help = Print this help\n");
  echo("quit, exit or q = exit\n");
}

function showLicence($dir) {
  $data = file_get_contents($dir . "/licence.txt");
  $lic = explode("\n",$data);
  echo("Licence-Information\n");
  echo("-----------------------\n");
  echo("Name: " . $lic[0] . "\n");
  echo("Licence: " . $lic[1] . "\n");
  echo("-----------------------\n");
}

$config = readConfig();
if ($config['sf_workdir'] == '') {
  $config['sf_workdir'] = getSFDir();
  $config['knime_workdir'] = getKNIMEDir();
  saveConfig($config);
}

$command = $argv[1];
echo("-----------------------------------\n");
echo("Screaming Frog Admin V1.1\n");
echo("-----------------------------------\n");
if (!checkSpiderConfig($config['sf_workdir'])) {
  echo("Screaming Frog not in db mode. Make sure you use the Screamin Frog in DB Store mode.\n");
  die(0);
}
if ($command == '') {
  printHelp();
  do {
    echo("command:");
    $command = strtolower(trim(fgets(STDIN)));
    switch ($command) {
      case 'help': printHelp(); break;
      case 'getdata': getCrawls($config['sf_workdir'] . '/ProjectInstanceData/'); break;
      case 'export': exportCrawls($config['sf_workdir'] . '/ProjectInstanceData/'); break;
      case 'knime': readKNIMERepro($config['sf_workdir'] . '/ProjectInstanceData/',$config['knime_workdir']); break;
      case 'licence': showlicence($config['sf_workdir']); break;
      case 'install derby': echo(checkDerbyDriver($config['knime_workdir'],true)); break;
    }
  } while (($command != "quit") && ($command != "exit") && ($command != "q"));
} else {
  switch ($command) {
      case 'help': printHelp(); break;
      case 'getdata': getCrawls($config['sf_workdir'] . '/ProjectInstanceData/',$argv[2]); break;
      case 'export': exportCrawls($config['sf_workdir'] . '/ProjectInstanceData/',$argv[2]); break;
      case 'licence': showlicence($config['sf_workdir']); break;
      case 'install derby': echo(checkDerbyDriver($config['knime_workdir'],true)); break;
  }
}

