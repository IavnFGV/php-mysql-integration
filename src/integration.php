<?php

$input = json_decode(file_get_contents("php://input"), true);
include("config.php");
$logUrl .= "-webhook";

$log["input"] = [
    "json" => $input,
    "post" => $_POST,
    "get" => $_GET
];
$result["state"] = true;

$meta=$input["meta"];
$data=$input["data"];

send_forward(json_encode($log), $logUrl."?state=false");

if($meta== NULL || $data == NULL){
    $result["state"] = false;
    $result["error"]["message"][] = "'entity' is not supported";
    echo json_encode($result);
    send_forward(json_encode($log), $logUrl."?state=false");
    exit;
}


$meta_action = $meta["action"];

if($meta_action == NULL ){
    $result["state"] = false;
    $result["error"]["message"][] = "'action' is NULL";
    echo json_encode($result);
    send_forward(json_encode($log), $logUrl."?state=false");
    exit;
}

if($meta_action == "delete"){
    $sql = "DELETE FROM `deals` WHERE `dealId` = '".$data["id"]."'";
    $delete = mysqli_query($sqlConnect, $sql);
    $log["delete"] = [
        "sql" => $sql,
        "query" => $delete,
        "affected" => mysqli_affected_rows($sqlConnect),
        "error" => mysqli_error($sqlConnect),
    ];
    $sql = "INSERT INTO `loging` (`data`, `description`) VALUES ('".mysqli_real_escape_string($sqlConnect, json_encode($log, JSON_UNESCAPED_UNICODE))."', 'deleted deal')";
    $insert = mysqli_query($sqlConnect, $sql);
    if ($insert == false) {
        send_forward(json_encode($log), $logUrl."?state=false");
    }
    echo json_encode($result);
    exit;
}


foreach ($data as $key => $value) {
    if(str_contains($key,"custom_fields")) {
        if($value != NULL){
            foreach ($value as $element_key => $element) {
                if($element["value"]==NULL){
                    continue;
                }
                $toSQL[] = "`".$element_key."` = '".$element["value"]."'";
                $fieldNames[]= $element_key;
            }
        }
        continue;
    }
    if(is_array( $value )){
        // echo 'Skipped for '.$key;
        continue;
    }
    // echo 'Your key is: ' . $key . ' and the value of the key is:' . $value;
    if(str_contains($key, "_time" )){
        if($value==NULL){
            continue;
        }
        $sysTimeZone = date_default_timezone_get();
        date_default_timezone_set("UTC");
        $tempDate = strtotime($value);
        date_default_timezone_set($sysTimeZone);
        $toSQL[] ="`".$key."` = '".date("Y-m-d H:i:s", $tempDate)."'";
        $toSQL[] ="`".$key."Unix` = '".$tempDate."'";
        $fieldNames[]= $key;
        $fieldNames[]= "{$key}Unix";
        continue;
    }
    if($value != NUll){
        $toSQL[] = "`".$key."` = '".$value."'";
        $fieldNames[]= $key;
    }

}


// Додавання стовпців
{
    $sql = "SHOW COLUMNS FROM `deals`";
    $getColumns = mysqli_fetch_all(mysqli_query($sqlConnect, $sql), MYSQLI_ASSOC);
    foreach ($getColumns as $oneColumn) {
        $allColumns[] = $oneColumn["Field"];
    }

    foreach($fieldNames as $field){
        if(!in_array($field,$allColumns)){

            if(str_contains("_timeUnix",$field)){
                $sql = "ALTER TABLE `deals` ADD `customId_".mysqli_real_escape_string($sqlConnect, $oneField["id"])."` timestamp AFTER `tags`";
                $add = mysqli_query($sqlConnect, $sql);
                $log["createColumn"][] = [
                    "sql" => $sql,
                    "add" => $add,
                    "error" => mysqli_error($sqlConnect),
                ];
                if ($add != true) {
                    $log["columnStatus"][] = "errorCreate";
                    continue;
                }
            }
            if(str_contains("_time",$field)){
                $sql = "ALTER TABLE `deals` ADD `customId_".mysqli_real_escape_string($sqlConnect, $oneField["id"])."` TEXT NOT NULL AFTER `tags`";
                $add = mysqli_query($sqlConnect, $sql);
                $log["createColumn"][] = [
                    "sql" => $sql,
                    "add" => $add,
                    "error" => mysqli_error($sqlConnect),
                ];
                if ($add != true) {
                    $log["columnStatus"][] = "errorCreate";
                    continue;

                }
            }


            $sql = "ALTER TABLE `deals` ADD `customId_".mysqli_real_escape_string($sqlConnect, $oneField["id"])."` TEXT NOT NULL AFTER `tags`";
            $add = mysqli_query($sqlConnect, $sql);
            $log["createColumn"][] = [
                "sql" => $sql,
                "add" => $add,
                "error" => mysqli_error($sqlConnect),
            ];
            if ($add != true) {
                $log["columnStatus"][] = "errorCreate";
                continue;
            }
        }
    }


}


// Додавання в таблицю
$sql = "INSERT INTO `deals` SET ".implode(", ", $toSQL);
$insert = mysqli_query($sqlConnect, $sql);
$log["to sql"] = [
    "request" => $sql,
    "insert" => $insert,
    "id" => mysqli_insert_id($sqlConnect),
    "error" => mysqli_error($sqlConnect),
];

$sql = "INSERT INTO `loging` (`data`) VALUES ('".mysqli_real_escape_string($sqlConnect, json_encode($log, JSON_UNESCAPED_UNICODE))."')";
$insert = mysqli_query($sqlConnect, $sql);
if ($insert == false) {
    send_forward(json_encode($log), $logUrl."?state=false");
}
echo json_encode($result);
?>