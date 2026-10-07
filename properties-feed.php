<?php

header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . "/config/db.php";

    $result = $conn->query(
        "SELECT id, property_name, property_type, location, property_status
         FROM properties
         WHERE is_published = 1
           AND property_status IN ('Available', 'Coming Soon')
         ORDER BY property_name ASC"
    );

    if ($result === false) {
        throw new RuntimeException("Unable to query property records.");
    }

    $properties = [];
    while ($property = $result->fetch_assoc()) {
        $properties[] = [
            "id" => (int) $property["id"],
            "property_name" => $property["property_name"],
            "property_type" => $property["property_type"],
            "location" => $property["location"],
            "property_status" => $property["property_status"],
        ];
    }

    echo json_encode($properties, JSON_UNESCAPED_SLASHES);
    $conn->close();
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log("Properties feed error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Unable to load current property records."]);
}
