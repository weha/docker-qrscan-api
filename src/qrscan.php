<?php
if (isset($_SERVER['X-Forwarded-For'])) $client = $_SERVER['X-Forwarded-For'];
else $client = $_SERVER['REMOTE_ADDR'];
$logprefix = date('Y-m-d H:i:s').' '.$client.': ';
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
    // Try to run without D-Bus
    exec('zbarimg --nodbus '.$file,$output,$result);
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

// Maximum file size (256MB)
$max_file_size = 256 * 1024 * 1024;

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fwrite($fh, $logprefix . 'Error: Invalid request method - ' . $_SERVER['REQUEST_METHOD'] . $logsuffix);
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['status' => 'error', 'value' => [], 'message' => 'Only POST method is allowed']);
    exit;
}

// Validate content length
$content_length = $_SERVER['CONTENT_LENGTH'] ?? 0;
if ($content_length > $max_file_size) {
    fwrite($fh, $logprefix . 'Error: File too large - ' . $content_length . ' bytes' . $logsuffix);
    header('HTTP/1.1 413 Payload Too Large');
    echo json_encode(['status' => 'error', 'value' => [], 'message' => 'File too large. Maximum size is 256MB']);
    exit;
}

// Validate content type
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
fwrite($fh, $logprefix . 'Content-Type: ' . $content_type . $logsuffix);

fwrite($fh, $logprefix . 'Starting QR scan'.$logsuffix);
$tmp_dir = sys_get_temp_dir().'/';
$filename = uniqid(mt_rand(), true) . '.jpg';
$file = $tmp_dir . $filename;

$input = file_get_contents("php://input");
if ($input === false) {
    fwrite($fh, $logprefix . 'Error: Failed to read input' . $logsuffix);
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'value' => [], 'message' => 'Failed to read input']);
    exit;
}

if (file_put_contents($file, $input) === false) {
    fwrite($fh, $logprefix . 'Error: Failed to write temporary file' . $logsuffix);
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['status' => 'error', 'value' => [], 'message' => 'Failed to write temporary file']);
    if (file_exists($file)) unlink($file);
    exit;
}

// Validate content type
if (!preg_match('/^image\/(jpeg|png|gif)$/i', $content_type)) {
    // Try to get content type from file if header is not set
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file);
    finfo_close($finfo);
    
    fwrite($fh, $logprefix . 'MIME Type from file: ' . $mime_type . $logsuffix);
    
    if (!preg_match('/^image\/(jpeg|png|gif)$/i', $mime_type)) {
        fwrite($fh, $logprefix . 'Error: Unsupported media type - ' . $mime_type . $logsuffix);
        header('HTTP/1.1 415 Unsupported Media Type');
        echo json_encode(['status' => 'error', 'value' => [], 'message' => 'Only JPEG, PNG and GIF images are supported']);
        unlink($file);
        exit;
    }
}

// Validate that the file is actually an image
if (!getimagesize($file)) {
    fwrite($fh, $logprefix . 'Error: Invalid image file' . $logsuffix);
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'value' => [], 'message' => 'Invalid image file']);
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
    fwrite($fh, $logprefix . 'Error: Failed to process QR code with all methods' . $logsuffix);
    header('HTTP/1.1 422 Unprocessable Entity');
    echo json_encode(['status' => 'error', 'value' => [], 'message' => 'Unprocessable Entity']);
}

echo json_encode($result);

// Clean up temporary file
if (file_exists($file)) {
    unlink($file);
}
