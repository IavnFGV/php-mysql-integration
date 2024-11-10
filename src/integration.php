<?php

$input = json_decode(file_get_contents("php://input"), true);
include("config.php");
include("polyfill.php");

$logUrl .= "-webhook";
$log = [
    "input" => [
        "json" => $input,
        "post" => $_POST,
        "get" => $_GET
    ]
];

$result = ["state" => true];
$meta = $input["meta"];
$data = $input["data"];
$previous = $input["previous"];

if (is_null($meta)) {
    return respondWithError("'entity' is not supported. meta tag is null");
}

$meta_action = $meta["action"];
if ($meta_action !== "delete" && is_null($data)) {
    return respondWithError("'entity' is not supported. Data is null for meta.action={$meta_action}");
}

if (is_null($meta_action)) {
    return respondWithError("'action' is NULL");
}
$deal_id = $meta["entity_id"];
$MAX_DEAL_ID = 110474;
switch ($meta_action) {
    case "delete":
        processDelete($deal_id, $meta);
        exit;

    case "history_load":
        if ($deal_id > $MAX_DEAL_ID || isAlreadyInDatabase($deal_id)) {
            logAndExit("SKIPPED as id > {$MAX_DEAL_ID} or already processed", $meta);
        }
        break;
}

// Proceed with deal creation or update
$toSQL=[];
prepareSQLFields($data, $meta, $fieldNames);
insertOrUpdateDeal($fieldNames, $deal_id);
logFinalResult($meta);
echo json_encode($result);

// Functions
function respondWithError($message)
{
    global $log, $logUrl;
    $result["state"] = false;
    $result["error"]["message"][] = $message;
    $log["result"] = $result;
    echo json_encode($result);
    send_forward(json_encode($log), "{$logUrl}?state=false");
    return false;
}

function processDelete($dealId, $meta)
{
    global $result;
    $sql = "DELETE FROM `deals` WHERE `deal_id` = '{$dealId}'";
    executeQuery($sql, "delete");
    logOperation($meta, 'deleted deal');
    echo json_encode($result);
}

function isAlreadyInDatabase($deal_id)
{
    $sql = "SELECT `deal_id` FROM `deals` WHERE `deal_id` = {$deal_id}";
    $query = executeQuery($sql,"is_already_in_database");
    $row = mysqli_fetch_assoc($query);
    return !is_null($row['deal_id']) && intval($row['deal_id']) == $deal_id;
}

function logAndExit($message, $meta)
{
    global $logUrl, $result, $log;
    $log["history_load"] = $message;
    logOperation($meta, 'SKIPPED request');
    send_forward(json_encode($log), "{$logUrl}?state=false");
    echo json_encode($result);
    exit;
}

function prepareSQLFields($data, $meta, &$fieldNames)
{
    global $deal_id,$toSQL;
    addMetaFields($meta, $fieldNames);

    foreach ($data as $key => $value) {
        if ($key == "id") continue;

        if (str_contains($key, "custom_fields") && !is_null($value)) {
            foreach ($value as $element_key => $element) {
                if (!is_null($element["value"])) {
                    $toSQL[] = "`{$element_key}` = '{$element["value"]}'";
                    $fieldNames[] = $element_key;
                }
            }
            continue;
        }

        if (str_contains($key, "label_ids") && !is_null($value)) {
            $toSQL[] = "`{$key}` = '" . implode(",", $value) . "'";
            $fieldNames[] = $key;
            continue;
        }

        if (is_array($value)) continue;

        if (str_contains($key, "_time") && !is_null($value)) {
            addTimeFields($key, $value,$fieldNames);
            continue;
        }

        if (!is_null($value)) {
            $toSQL[] = "`{$key}` = '{$value}'";
            $fieldNames[] = $key;
        }
    }
}

function addMetaFields($meta, &$fieldNames)
{
    global $toSQL;
    $toSQL[] = "`deal_id` = '{$meta["entity_id"]}'";
    $toSQL[] = "`correlation_id` = '{$meta["correlation_id"]}'";
    $toSQL[] = "`meta_id` = '{$meta["id"]}'";

    $fieldNames[] = "deal_id";
    $fieldNames[] = "correlation_id";
    $fieldNames[] = "meta_id";
}

function addTimeFields($key, $value, &$fieldNames)
{
    global $toSQL;
    $sysTimeZone = date_default_timezone_get();
    date_default_timezone_set("UTC");
    $tempDate = strtotime($value);
    date_default_timezone_set($sysTimeZone);

    $toSQL[] = "`{$key}` = '" . date("Y-m-d H:i:s", $tempDate) . "'";
    $toSQL[] = "`{$key}Unix` = '{$tempDate}'";

    $fieldNames[] = $key;
    $fieldNames[] = "{$key}Unix";
}

function insertOrUpdateDeal( $fieldNames, $deal_Id)
{
    global $toSQL;
    checkAndCreateColumns($fieldNames);
    $sql = "INSERT INTO `deals` SET " . implode(", ", $toSQL);
    executeQuery($sql, "insert");
    updateHistoryLoad($deal_Id);
}

/**
 * @param $deal_Id
 * @return void
 */
function updateHistoryLoad($deal_Id): void
{
    global $meta_action;
    if($meta_action!="history_load"){
        return;
    }
    $sql = "UPDATE `additional_values` SET `value` = {$deal_Id} WHERE `name` = 'history_load_last_id'";
    executeQuery($sql,"update_history_load");
}

function checkAndCreateColumns($fieldNames)
{
    global $sqlConnect;
    $sql = "SHOW COLUMNS FROM `deals`";
    $columns = array_column(mysqli_fetch_all(mysqli_query($sqlConnect, $sql), MYSQLI_ASSOC), "Field");

    foreach ($fieldNames as $field) {
        if (!in_array($field, $columns)) {
            createColumn($field);
        }
    }
}

function createColumn($field)
{
    global $sqlConnect;
    $type = "TEXT";
    if ($field === "f96ace3db32b364d4585d683d9ce708d128bdbd9") $type = "FLOAT";
    elseif ($field == "2c5ec293caa3c168eeca24869b8b2a0da661710d" ||
        $field == "1d609cb8de82b88497812f480c6c6b01859cd9c3" ||
        $field == "ae74cc2b1fa13e336202e634ae1ce15db671ee83" ||
        $field == "2084777055c8896173bf5046916a3a003c03abbe") $type = 'DATE';
    elseif (str_ends_with($field, "_timeUnix") || str_ends_with($field, "_id")) $type = "BIGINT";
    elseif (str_ends_with($field, "_time")) $type = "DATETIME";

    $sql = "ALTER TABLE `deals` ADD `" . mysqli_real_escape_string($sqlConnect, $field) . "` {$type} AFTER `value`";
    executeQuery($sql, "createColumn");
}

function executeQuery($sql, $type)
{
    global $sqlConnect, $log;
    $query = mysqli_query($sqlConnect, $sql);
    $log[$type] = [
        "sql" => $sql,
        "affected" => mysqli_affected_rows($sqlConnect),
        "error" => mysqli_error($sqlConnect),
    ];
    return $query;
}

function logOperation($meta, $description)
{
    global $sqlConnect, $log, $logUrl;
    $sql = sprintf("INSERT INTO `loging` (`data`, `description`, `correlation_id`, `meta_id`) VALUES ('%s', '%s', '%s', '%s')",
        mysqli_real_escape_string($sqlConnect, json_encode($log, JSON_UNESCAPED_UNICODE)),
        $description,
        $meta["correlation_id"],
        $meta["id"]);
    $query = executeQuery($sql, "logOperation");
    if (!$query) {
        send_forward(json_encode($log), "{$logUrl}?state=false");
    }
}

function logFinalResult($meta)
{
    logOperation($meta, 'Final result logged');
}

?>
