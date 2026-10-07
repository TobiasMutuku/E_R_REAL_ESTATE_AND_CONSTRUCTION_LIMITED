<?php
declare(strict_types=1);

function siteSettingsDefaults(): array
{
    return [
        "company_name" => "E&R Real Estate and Construction Limited",
        "public_phone" => "+254 748 766 822",
        "public_email" => "errealestateconstruction@gmail.com",
        "office_address" => "Watamu Mall, Office 34, 1st Floor, Watamu, Kilifi County",
        "whatsapp_phone" => "254748766822",
    ];
}

function loadSiteSettings(mysqli $conn): array
{
    $settings = siteSettingsDefaults();
    $result = $conn->query("SELECT setting_key, setting_value FROM site_settings");

    if ($result === false) {
        throw new RuntimeException("Unable to load site settings.");
    }

    while ($setting = $result->fetch_assoc()) {
        if (array_key_exists($setting["setting_key"], $settings)) {
            $settings[$setting["setting_key"]] = $setting["setting_value"];
        }
    }

    return $settings;
}

function saveSiteSettings(mysqli $conn, array $settings): void
{
    $statement = $conn->prepare(
        "INSERT INTO site_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );

    if ($statement === false) {
        throw new RuntimeException("Unable to prepare site settings.");
    }

    foreach ($settings as $key => $value) {
        $statement->bind_param("ss", $key, $value);
        if (!$statement->execute()) {
            $statement->close();
            throw new RuntimeException("Unable to save site settings.");
        }
    }

    $statement->close();
}
