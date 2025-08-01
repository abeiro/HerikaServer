<?php

function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") {
    $options = $obj->getAllFk($fieldName);
    $html = "<label for='{$fieldName}'>{$labelText}</label><br>";
    $html = "<br>";
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
