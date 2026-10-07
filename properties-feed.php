<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, max-age=0");

try {
    require_once __DIR__ . "/config/db.php";

    $result = $conn->query(
        "SELECT id, property_name, property_type, location, price, bedrooms, bathrooms, area,
                description, property_status, image
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
            "price" => $property["price"] !== null ? (float) $property["price"] : null,
            "bedrooms" => $property["bedrooms"] !== null ? (int) $property["bedrooms"] : null,
            "bathrooms" => $property["bathrooms"] !== null ? (int) $property["bathrooms"] : null,
            "area" => $property["area"] !== null ? (float) $property["area"] : null,
            "description" => $property["description"],
            "property_status" => $property["property_status"],
            "image" => $property["image"],
        ];
    }

    echo json_encode($properties, JSON_UNESCAPED_SLASHES);
    $conn->close();
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log("Properties feed error: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Unable to load current property records."]);
}
