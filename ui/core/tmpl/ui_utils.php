<?php

function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") {
    $options = $obj->getAllFk($fieldName);
    if (!is_array($options) || count($options)===0) {
        if ($fieldName === 'llm_formatter_id') {
            try {
                $options = $GLOBALS["db"]->fetchAll("SELECT id, COALESCE(NULLIF(label,''), model) AS label FROM core_llm_connector ORDER BY LOWER(COALESCE(NULLIF(label,''), model)) ASC");
            } catch (Throwable $_e) { $options = []; }
        }
    }
    $html = "<label for='{$fieldName}'>{$labelText}</label><br>";
    $html .= "<select name='{$fieldName}' id='{$fieldName}'>";
    $html .= "<option value=''>-- Select {$labelText} --</option>";
    foreach ($options as $opt) {
        $id = htmlspecialchars($opt["id"]);
        $label = htmlspecialchars($opt["label"]);
        $selected = $selectedValue == $opt["id"] ? "selected" : "";
        $html .= "<option value='{$id}' {$selected}>{$label}</option>";
    }
    $html .= "</select><br><br>";
    return $html;
}

function getSelectOptions($obj, $fieldName) {
    $options = $obj->getAllFk($fieldName);
    if (!is_array($options) || count($options)===0) {
        if ($fieldName === 'llm_formatter_id') {
            try {
                $options = $GLOBALS["db"]->fetchAll("SELECT id, COALESCE(NULLIF(label,''), model) AS label FROM core_llm_connector ORDER BY LOWER(COALESCE(NULLIF(label,''), model)) ASC");
            } catch (Throwable $_e) { $options = []; }
        }
    }
    $result = [];
    foreach ($options as $opt) {
        $result[] = [
            'id' => htmlspecialchars($opt["id"]),
            'label' => htmlspecialchars($opt["label"])
        ];
    }
    return $result;
}


function getGlobalParms() {
   
}

?>
