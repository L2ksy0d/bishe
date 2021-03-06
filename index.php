<?php

use Deobf\Banner;
use Deobf\Deobf;
use Deobf\DeobfFactory;
use Deobf\Output;
use Deobf\Feature\feature;
use Deobf\GlobalTable;
use Deobf\Yara;
use Symfony\Component\Finder\Finder;

require 'vendor/autoload.php';

ini_set('xdebug.max_nesting_level', 100000000000);
ini_set('memory_limit', '-1');

$finder = new Finder();
$output = new Output;
$feature = new feature;
$datatable = new GlobalTable;
$banner = new Banner;
$yara = [];
$banner->original();
if(in_array('-h',$argv)){
    $banner->help();
    exit;
}

$cmd_arr = getopt('p:y:');
$original = 0;
if(empty(getopt('p:'))){
    die("Enter the scan path after -p\n"); 
}else{
    if(is_dir($cmd_arr['p']) && file_exists($cmd_arr['p'])){
    $dir = $cmd_arr['p'];
    $flag = 0;
    }elseif(is_file($cmd_arr['p'])){
        $flag = 1;
        $filedir = substr($cmd_arr['p'],0,strripos($cmd_arr['p'], '/'));
        $filename = trim(strrchr($cmd_arr['p'], '/'),'/');
        if(!file_exists($filedir)){
            die("The path $cmd_arr[p] does not exist\n");
        }
    }
}

$orig = in_array('-o',$argv);
if($orig){
    $original = 1; 
}else{
    $original = 0;
}

if($flag){
    $finder->files()->in($filedir)->name($filename);
}else{
    $finder->files()->in($dir)->name('*.php');
}

foreach($finder as $file){
    $maxlenth = 0;
    $maxline = 0;
    $datatable->setvariablevalue("FILE",$file->getRealPath());
    $yara['filepath'] = $file->getRealPath();
    $datatable->setvariablevalue("DIR",dirname($file->getRealPath()));
    $file_name = $file->getPathname();
    $file_contents = file_get_contents($file);
    $php_script_pattern = '/<script language=\"php\">([\s\S]*?)<\/script>+/i';
    try {
        $factory = (new DeobfFactory)->create();
        //脚本标签
        $code = '<?php ';
        preg_match_all($php_script_pattern, $file_contents,$res_array);
        $code = $code . implode(PHP_EOL, $res_array[1]);
        if($code === '<?php '){
            $code = $file_contents;
        }
        $arr = explode("\n",$file_contents);
        foreach($arr as $key => $value){
            if($maxlenth < strlen($value)){
                $maxlenth = strlen($value);
                $maxline = $key + 1;
            }
        }
        $output->getcsvdata('MAX',$maxlenth);
        $output->getcsvdata('LT',strlen(str_replace("\n", '', $file_contents)));
        $output->getcsvdata('NAME',array(str_replace(",","%2C",$file_name)));
        $output->entropy($file_name);
        if(in_array('-f',$argv)){
            $feature->extract_feature($code);
        }
        $alarm = $factory->detect($file_name, $code,$original,$feature);
    } catch (PhpParser\Error $e) {
        //echo "aa";
    }
    if(in_array('-y',$argv)){
        $yara['yarapath'] = $cmd_arr['y'];
        $yaracheck = new Yara($yara);
        $yaracheck->command();
    }
}
    