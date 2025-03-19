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
    if ($result == 0 && count($output) > 0)
        $status = 'ok';
    return array('status' => $status,'value' => $output,'with' => 'qrscan');
}

function opencv($file) {
    global $logprefix,$fh,$logsuffix;
    fwrite($fh, $logprefix.'OpenCV'.$logsuffix);
    exec('python3 /var/www/qrscan_opencv.py '.$file,$output,$result);
    $status = 'error';
    if ($result == 0 && count($output) > 0)
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
    if ($result == 0 && count($output) > 0)
        $status = 'ok';    
    return array('status' => $status,'value' => $output,'with' => 'zbarimg');
}

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization");

// Maximum file size (5MB)
$max_file_size = 256 * 1024 * 1024;

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['status' => 'error', ,'value' => '', 'message' => 'Only POST method is allowed']);
    exit;
}

// Validate content length
$content_length = $_SERVER['CONTENT_LENGTH'] ?? 0;
if ($content_length > $max_file_size) {
    header('HTTP/1.1 413 Payload Too Large');
    echo json_encode(['status' => 'error', ,'value' => '', 'message' => 'File too large. Maximum size is 5MB']);
    exit;
}

// Validate content type
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (!preg_match('/^image\/(jpeg|png|gif)$/', $content_type)) {
    header('HTTP/1.1 415 Unsupported Media Type');
    echo json_encode(['status' => 'error', ,'value' => '', 'message' => 'Only JPEG, PNG and GIF images are supported']);
    exit;
}

fwrite($fh, $logprefix . 'Starting QR scan'.$logsuffix);
$tmp_dir = sys_get_temp_dir().'/';
$filename = uniqid(mt_rand(), true) . '.jpg';
$file = $tmp_dir . $filename;

$input = file_get_contents("php://input");
if ($input === false) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', ,'value' => '', 'message' => 'Failed to read input']);
    exit;
}

if (file_put_contents($file, $input) === false) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['status' => 'error', ,'value' => '', 'message' => 'Failed to write temporary file']);
    if (file_exists($file)) unlink($file);
    exit;
}

// Validate that the file is actually an image
if (!getimagesize($file)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', ,'value' => '', 'message' => 'Invalid image file']);
    unlink($file);
    exit;
}

$result = qrscan($file);
if ($result['status'] == 'error') 
    $result = opencv($file);
if ($result['status'] == 'error') 
    $result = zbarimg($file);

if ($result['status'] == 'ok') 
    update_stats($result['with']);
else {
    header('HTTP/1.1 422 Unprocessable Entity');
    echo json_encode(['status' => 'error', ,'value' => '', 'message' => 'Unprocessable Entity']);
}

echo json_encode($result);

// Clean up temporary file
if (file_exists($file)) {
    unlink($file);
}
