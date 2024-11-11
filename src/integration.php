<?php

include("config.php");
include("polyfill.php");
$input = json_decode(file_get_contents("php://input"), true);


//foreach ($input as $input_deal) {
$input_deal = $input;
    $dealProcessor = new DealProcessor($sqlConnect, $logUrl,$input_deal);
    $dealProcessor->process();
//}
exit;


//$dealProcessor = new DealProcessor($sqlConnect, $logUrl);
//
//$dealProcessor->process();
class DealProcessor
{
    private $input;
    private $logUrl;
    private $log = [];
    private $result = ["state" => true];
    private $toSQL = [];
    private $fieldNames = [];
    private $MAX_DEAL_ID = 110474;
    private $sqlConnect;

    public function __construct($sqlConnect, $logUrl, $input_deal)
    {
        $this->input = $input_deal;
        $this->logUrl = $logUrl . "-webhook";
        $this->sqlConnect = $sqlConnect;
        $this->log["input"] = [
            "json" => $this->input,
            "post" => $_POST,
            "get" => $_GET
        ];
    }

    public function process()
    {
        $meta = $this->input["meta"] ?? null;
        $data = $this->input["data"] ?? null;
        $meta_action = $meta["action"] ?? null;
        $deal_id = $meta["entity_id"] ?? null;

        if (is_null($meta)) {
            return $this->respondWithError("'entity' is not supported. meta tag is null");
        }

        if ($meta_action !== "delete" && is_null($data)) {
            return $this->respondWithError("'entity' is not supported. Data is null for meta.action={$meta_action}");
        }

        if (is_null($meta_action)) {
            return $this->respondWithError("'action' is NULL");
        }

        switch ($meta_action) {
            case "delete":
                $this->processDelete($deal_id, $meta);
                return;
                break;
            case "history_load":
                if ($deal_id > $this->MAX_DEAL_ID || $this->isAlreadyInDatabase($deal_id)) {
                    $this->logAndExit("SKIPPED as id > {$this->MAX_DEAL_ID} or already processed", $meta);
                    return;
                }
                break;
        }
        $this->prepareSQLFields($data, $meta);
        $this->insertOrUpdateDeal($deal_id);
        $this->logFinalResult($meta);
        echo json_encode($this->result);
    }

    private function respondWithError($message)
    {
        $this->result["state"] = false;
        $this->result["error"]["message"][] = $message;
        $this->log["result"] = $this->result;
        $this->sendForward(json_encode($this->log), "{$this->logUrl}?state=false");
        echo json_encode($this->result);
    }

    private function processDelete($dealId, $meta)
    {
        $sql = "DELETE FROM `deals` WHERE `deal_id` = '{$dealId}'";
        $this->executeQuery($sql, "delete");
        $this->logOperation($meta, 'deleted deal');
        echo json_encode($this->result);
    }

    private function isAlreadyInDatabase($deal_id)
    {
        $sql = "SELECT `deal_id` FROM `deals` WHERE `deal_id` = {$deal_id}";
        $query = $this->executeQuery($sql, "is_already_in_database");
        $row = mysqli_fetch_assoc($query);
        return !is_null($row['deal_id']) && intval($row['deal_id']) == $deal_id;
    }

    private function logAndExit($message, $meta)
    {
        $this->log["history_load"] = $message;
        $this->logOperation($meta, 'SKIPPED request');
        $this->sendForward(json_encode($this->log), "{$this->logUrl}?state=false");
        echo json_encode($this->result);
    }

    private function prepareSQLFields($data, $meta)
    {
        $this->addMetaFields($meta);
        foreach ($data as $key => $value) {
            if ($key == "id") continue;

            if (str_contains($key, "custom_fields") && !is_null($value)) {
                foreach ($value as $element_key => $element) {
                    if (!is_null($element["value"])) {
                        $this->toSQL[] = "`{$element_key}` = '{$element["value"]}'";
                        $this->fieldNames[] = $element_key;
                    }elseif(!is_null($element["id"]) && $element["type"] =="enum" ){
                        $this->toSQL[] = "`{$element_key}` = '{$element["id"]}'";
                        $this->fieldNames[] = $element_key;
                    }
                }
                continue;
            }

            if (str_contains($key, "label_ids") && !is_null($value)) {
                $this->toSQL[] = "`{$key}` = '" . implode(",", $value) . "'";
                $this->fieldNames[] = $key;
                continue;
            }

            if (is_array($value)) continue;

            if (str_contains($key,"_time") && !is_null($value)) {
                $this->addTimeFields($key,$value);
                continue;
            }

            if (!is_null($value)) {
                $this->toSQL[] = "`{$key}` = '{$value}'";
                $this->fieldNames[] = $key;
            }

        }
    }

    private function addMetaFields($meta)
    {
        $this->toSQL[] = "`deal_id` = '{$meta["entity_id"]}'";
        $this->toSQL[] = "`correlation_id` = '{$meta["correlation_id"]}'";
        $this->toSQL[] = "`meta_id` = '{$meta["id"]}'";
        $this->fieldNames = array_merge($this->fieldNames, ["deal_id", "correlation_id", "meta_id"]);
    }

    private function insertOrUpdateDeal($dealId)
    {
        $this->checkAndCreateColumns();
        $sql = "INSERT INTO `deals` SET " . implode(", ", $this->toSQL);
        $this->executeQuery($sql, "insert");
        $this->updateHistoryLoad($dealId);
    }

    private function checkAndCreateColumns()
    {
        $sql = "SHOW COLUMNS FROM `deals`";
        $columns = array_column(mysqli_fetch_all(mysqli_query($this->sqlConnect, $sql), MYSQLI_ASSOC), "Field");

        foreach ($this->fieldNames as $field) {
            if (!in_array($field, $columns)) {
                $this->createColumn($field);
            }
        }
    }

    private function createColumn($field)
    {
        $type = "TEXT";
        if ($field === "f96ace3db32b364d4585d683d9ce708d128bdbd9") $type = "FLOAT";
        elseif ($field == "2c5ec293caa3c168eeca24869b8b2a0da661710d" ||
            $field == "1d609cb8de82b88497812f480c6c6b01859cd9c3" ||
            $field == "ae74cc2b1fa13e336202e634ae1ce15db671ee83" ||
            $field == "2084777055c8896173bf5046916a3a003c03abbe") $type = 'DATE';
        elseif (str_ends_with($field, "_timeUnix") || str_ends_with($field, "_id")) $type = "BIGINT";
        elseif (str_ends_with($field, "_time")) $type = "DATETIME";
        $sql = "ALTER TABLE `deals` ADD `" . mysqli_real_escape_string($this->sqlConnect, $field) . "` {$type} AFTER `value`";
        $this->executeQuery($sql, "createColumn");
    }

    private function executeQuery($sql, $type)
    {
        $query = mysqli_query($this->sqlConnect, $sql);
        $this->log[$type] = [
            "sql" => $sql,
            "affected" => mysqli_affected_rows($this->sqlConnect),
            "error" => mysqli_error($this->sqlConnect),
        ];
        return $query;
    }

    private function logOperation($meta, $description)
    {
        $sql = sprintf("INSERT INTO `loging` (`data`, `description`, `correlation_id`, `meta_id`) VALUES ('%s', '%s', '%s', '%s')",
            mysqli_real_escape_string($this->sqlConnect, json_encode($this->log, JSON_UNESCAPED_UNICODE)),
            $description,
            $meta["correlation_id"],
            $meta["id"]);
        $this->executeQuery($sql, "logOperation");
    }

    private function updateHistoryLoad($dealId)
    {
        if ($this->input["meta"]["action"] !== "history_load") return;
        $sql = "UPDATE `additional_values` SET `value` = {$dealId} WHERE `name` = 'history_load_last_id'";
        $this->executeQuery($sql, "update_history_load");
    }

    private function logFinalResult($meta)
    {
        $this->logOperation($meta, 'Final result logged');
    }

    private function sendForward($data, $url)
    {
        send_forward();
    }

    private function addTimeFields($key, $value)
    {
        $sysTimeZone = date_default_timezone_get();
        date_default_timezone_set("UTC");
        $tempDate = strtotime($value);
        date_default_timezone_set($sysTimeZone);

        $this->toSQL[] = "`{$key}` = '" . date("Y-m-d H:i:s", $tempDate) . "'";
        $this->toSQL[] = "`{$key}Unix` = '{$tempDate}'";

        $this->fieldNames[] = $key;
        $this->fieldNames[] = "{$key}Unix";
    }
}



?>