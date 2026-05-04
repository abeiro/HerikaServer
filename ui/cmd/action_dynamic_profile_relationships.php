<?php

header('Content-Type: application/json');

echo json_encode([
    "status" => "error",
    "message" => "Dynamic relationship profile updates are deprecated. Relationship state is managed by the relationship metadata system."
]);

