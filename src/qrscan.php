<?php
if (isset($_SERVER['X-Forwarded-For'])) $client = $_SERVER['X-Forwarded-For'];
else $client = $_SERVER['REMOTE_ADDR'];
$logprefix = date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].': ';
$logsuffix = "\n";

$fh = fopen('php://stdout', 'w');

function update_stats($type) {
    $previous = 0;
    $file = 'qrcode_'.$type.'.log';
    if (file_exists($file)) $previous = file_get_contents($file);
    file_put_contents($file, ++$previous);
}

function qrscan($file) {
    global $logprefix,$fh,$logsuffix;
    fwrite($fh, $logprefix.'QR scan'.$logsuffix);
    exec('qrscan '.$file,$output,$result);
    $status = 'error';
    if ($result == 0)
        $status = 'ok';
    return array('status' => $status,'value' => $output,'with' => 'qrscan');
}

function opencv($file) {
    global $logprefix,$fh,$logsuffix;
    fwrite($fh, $logprefix.'OpenCV'.$logsuffix);
    exec('python3 /var/www/qrscan_opencv.py '.$file,$output,$result);
    $status = 'error';
    if ($result == 0)
        $status = 'ok';    
    return array('status' => $status,'value' => $output,'with' => 'opencv');
}

function zbarimg($file) {
    global $logprefix,$fh,$logsuffix;
    fwrite($fh, $logprefix.'ZbarImg'.$logsuffix);
    exec('zbarimg '.$file,$output,$result);
    foreach ($output as $k => $o) {
        $output[$k] = str_replace('QR-Code:', '', $output[$k]);
    }
    $status = 'error';
    if ($result == 0)
        $status = 'ok';    
    return array('status' => $status,'value' => $output,'with' => 'zbarimg');
}

header('Content-Type: application/json; charset=utf-8');
header("Acess-Control-Allow-Origin: *");
header("Acess-Control-Allow-Methods: POST");
header("Acess-Control-Allow-Headers: Acess-Control-Allow-Headers,Content-Type,Acess-Control-Allow-Methods, Authorization");


fwrite($fh, $logprefix . 'Starting QR scan'.$logsuffix);
$tmp_dir = sys_get_temp_dir().'/';
$filename = uniqid(mt_rand(), true) . '.jpg';
$file = $tmp_dir . $filename;
file_put_contents($file, file_get_contents("php://input"));

$result = qrscan($file);
if ($result['status'] == 'error') $result = opencv($file);
if ($result['status'] == 'error') $result = zbarimg($file);

if ($result['status'] == 'ok') update_stats($result['with']);

echo json_encode($result);
unlink($file);