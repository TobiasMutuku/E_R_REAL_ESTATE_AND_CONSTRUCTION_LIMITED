INSERT INTO properties (
    property_name,
    property_type,
    location,
    price,
    bedrooms,
    bathrooms,
    area,
    description,
    property_status,
    image,
    is_published
)
SELECT
    'Coastal Villa Concept 01 (Illustrative)',
    'House',
    'Location to be confirmed',
    NULL,
    NULL,
    NULL,
    NULL,
    'ILLUSTRATIVE ONLY - concept placeholder, not verified property inventory. No verified property photo is available. Confirm location, ownership, specifications, price and availability with E&R.',
    'Coming Soon',
    NULL,
    1
WHERE NOT EXISTS (
    SELECT 1 FROM properties
    WHERE property_name = 'Coastal Villa Concept 01 (Illustrative)'
);

INSERT INTO properties (
    property_name,
    property_type,
    location,
    price,
    bedrooms,
    bathrooms,
    area,
    description,
    property_status,
    image,
    is_published
)
SELECT
    'Coastal Villa Concept 02 (Illustrative)',
    'House',
    'Location to be confirmed',
    NULL,
    NULL,
    NULL,
    NULL,
    'ILLUSTRATIVE ONLY - concept placeholder, not verified property inventory. No verified property photo is available. Confirm location, ownership, specifications, price and availability with E&R.',
    'Coming Soon',
    NULL,
    1
WHERE NOT EXISTS (
    SELECT 1 FROM properties
    WHERE property_name = 'Coastal Villa Concept 02 (Illustrative)'
);

INSERT INTO properties (
    property_name,
    property_type,
    location,
    price,
    bedrooms,
    bathrooms,
    area,
    description,
    property_status,
    image,
    is_published
)
SELECT
    'Coastal Apartment Concept 01 (Illustrative)',
    'Apartment',
    'Location to be confirmed',
    NULL,
    NULL,
    NULL,
    NULL,
    'ILLUSTRATIVE ONLY - concept listing, not verified property inventory. The architectural rendering is not a photograph of an available property. Confirm location, ownership, specifications, price and availability with E&R.',
    'Coming Soon',
    'img/5825572544151490572.jpg',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM properties
    WHERE property_name = 'Coastal Apartment Concept 01 (Illustrative)'
);

INSERT INTO properties (
    property_name,
    property_type,
    location,
    price,
    bedrooms,
    bathrooms,
    area,
    description,
    property_status,
    image,
    is_published
)
SELECT
    'Coastal Land Concept 01 (Unverified)',
    'Land',
    'Location to be confirmed',
    NULL,
    NULL,
    NULL,
    NULL,
    'UNVERIFIED OPPORTUNITY - concept placeholder, not a confirmed plot for sale. No land image, title, boundaries, location, price or availability has been verified. Contact E&R before relying on any property information.',
    'Coming Soon',
    NULL,
    1
WHERE NOT EXISTS (
    SELECT 1 FROM properties
    WHERE property_name = 'Coastal Land Concept 01 (Unverified)'
);

INSERT INTO properties (
    property_name,
    property_type,
    location,
    price,
    bedrooms,
    bathrooms,
    area,
    description,
    property_status,
    image,
    is_published
)
SELECT
    'Coastal Land Concept 02 (Unverified)',
    'Land',
    'Location to be confirmed',
    NULL,
    NULL,
    NULL,
    NULL,
    'UNVERIFIED OPPORTUNITY - concept placeholder, not a confirmed plot for sale. No land image, title, boundaries, location, price or availability has been verified. Contact E&R before relying on any property information.',
    'Coming Soon',
    NULL,
    1
WHERE NOT EXISTS (
    SELECT 1 FROM properties
    WHERE property_name = 'Coastal Land Concept 02 (Unverified)'
);

UPDATE properties
SET image = NULL,
    property_type = 'House',
    description = 'ILLUSTRATIVE ONLY - concept placeholder, not verified property inventory. No verified property photo is available. Confirm location, ownership, specifications, price and availability with E&R.'
WHERE property_name IN (
    'Coastal Villa Concept 01 (Illustrative)',
    'Coastal Villa Concept 02 (Illustrative)'
)
AND image IN (
    'img/5825572544151490570.jpg',
    'img/5825572544151490571.jpg'
);
