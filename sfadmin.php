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
  echo("getdata = List crawls and return crawl-meta-data\n\n");
  echo("export = Export and remove crawl\n\n");
  echo("licence = list licence informationen\n\n");
  echo("help = Print this help\n\n");
  echo("quit, exit or q = exit\n\n");
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
echo("Screaming Frog Admin V1.0\n");
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
    }
  } while (($command != "quit") && ($command != "exit") && ($command != "q"));
} else {
  switch ($command) {
      case 'help': printHelp(); break;
      case 'getdata': getCrawls($config['sf_workdir'] . '/ProjectInstanceData/',$argv[2]); break;
      case 'export': exportCrawls($config['sf_workdir'] . '/ProjectInstanceData/',$argv[2]); break;
      case 'licence': showlicence($config['sf_workdir']); break;
  }
}

