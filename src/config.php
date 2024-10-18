<?php

ini_set('max_execution_time', '1700');
set_time_limit(1700);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

$logUrl = "https://log.mufiksoft.com/naukroom-sql";


function send_request(string $url, array $header = [], string $type = "GET", array $param = [], string $raw = "json"): string {
    $descriptor = curl_init($url);
    if ($type != "GET") {
        if ($raw == "json") {
            curl_setopt($descriptor, CURLOPT_POSTFIELDS, json_encode($param));
            $header[] = "Content-Type: application/json";
        } else if ($raw == "form") {
            curl_setopt($descriptor, CURLOPT_POSTFIELDS, http_build_query($param));
            $header[] = "Content-Type: application/x-www-form-urlencoded";
        } else {
            curl_setopt($descriptor, CURLOPT_POSTFIELDS, $param);
        }
    }
    $header[] = "User-Agent: M-Soft Integration(https://mufiksoft.com)";
    curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($descriptor, CURLOPT_HTTPHEADER, $header);
    curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $type);
    $itog = curl_exec($descriptor);
    curl_close($descriptor);
    return $itog;
}
function send_bearer($url, $token, $type = "GET", $param = []){
    $descriptor = curl_init($url);
    $headers = [
        "User-Agent: M-Soft Integration",
        // "Content-Type: application/json",
        "Authorization: Bearer ".$token
    ];
    if ($type != "GET") {
        $headers[] = "Content-Type: application/json";
    }
    curl_setopt($descriptor, CURLOPT_POSTFIELDS, json_encode($param));
    curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($descriptor, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $type);
    $itog = curl_exec($descriptor);
    curl_close($descriptor);
    return $itog;
}

function send_forward($inputJSON, $link){
// do nothing
}

function send_forward1($inputJSON, $link){
    $request = "POST";
    $descriptor = curl_init($link);
    curl_setopt($descriptor, CURLOPT_POSTFIELDS, $inputJSON);
    curl_setopt($descriptor, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($descriptor, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($descriptor, CURLOPT_CUSTOMREQUEST, $request);
    $itog = curl_exec($descriptor);
    curl_close($descriptor);
    return $itog;
}
function getFondySignature( $merchant_id , $password , $params = array() ){
    $params['merchant_id'] = $merchant_id;
    $params = array_filter($params,'strlen');
    ksort($params);
    $params = array_values($params);
    array_unshift( $params , $password );
    $params = join('|',$params);
    return(sha1($params));
}

// DataBase conecting
$server = "mysql";
$username = "naukroom_usr";
$password = "sk2GU3NXnqsqA472";
$database = "naukroom";

mysqli_report(MYSQLI_REPORT_OFF);
$sqlConnect = mysqli_connect($server, $username, $password, $database,3306);
if ($sqlConnect === false) {
    $result["state"] = false;
    $result["error"]["message"] = "error connecting to MySQL";
    echo json_encode($result);
    exit;
}
mysqli_set_charset($sqlConnect, "utf8mb4");
mysqli_options($sqlConnect, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

$url = "https://".$_SERVER["HTTP_HOST"].dirname($_SERVER["PHP_SELF"]);
$url = explode("?", $url);
$url = $url[0];
if (substr($url, -1) != "/") {
    $url = $url."/";
}

if (strlen($url) < 20 && file_exists("thisUrl")) {
    $url = file_get_contents("thisUrl");
} else if (strlen($url) > 20) {
    $result["writeurl"] = file_put_contents("thisUrl", $url);
}


if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}