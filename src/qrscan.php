<?php

header('Content-Type: application/json; charset=utf-8');
header("Acess-Control-Allow-Origin: *");
header("Acess-Control-Allow-Methods: POST");
header("Acess-Control-Allow-Headers: Acess-Control-Allow-Headers,Content-Type,Acess-Control-Allow-Methods, Authorization");

$tmp_dir = sys_get_temp_dir().'/';
$filename = uniqid(mt_rand(), true) . '.jpg';
$file = $tmp_dir . $filename;
file_put_contents($file, file_get_contents("php://input"));
exec('qrscan '.$file,$output,$result);
// echo $result.'-'.var_export($output, true);
$status = 'error';
if ($result == 0)
    $status = 'ok';

echo json_encode(array('status' => $status, 'value' => $output));
unlink($file);