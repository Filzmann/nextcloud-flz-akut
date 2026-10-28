<?php

declare(strict_types=1);

$root=dirname(__DIR__);$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/lib'));foreach($iterator as$file){if(!$file->isFile()||$file->getExtension()!=='php')continue;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()),$output,$code);if($code!==0)throw new RuntimeException(implode("\n",$output));}
$tests=[];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));foreach($iterator as$file){if($file->isFile()&&str_ends_with($file->getFilename(),'Test.php'))$tests[]=$file->getPathname();}sort($tests);foreach($tests as$test){passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($test),$code);if($code!==0)exit($code);}echo "flz_akut PHP tests passed\n";
