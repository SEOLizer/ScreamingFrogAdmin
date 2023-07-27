<?php

$config = [];
error_reporting(E_ERROR);

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
    echo('Could not determine the directory. Please enter it manually: ');
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

