<?php

require_once __DIR__ . '/vendor/autoload.php';

use \Akeneo\Pim\ApiClient\AkeneoPimClientBuilder;
use \Akeneo\Pim\ApiClient\Search\SearchBuilder;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$ATTRIBUTE_TYPES = [
    "pim_catalog_identifier" => "Identifier",
    "pim_catalog_text" => "Text Field",
    "pim_catalog_textarea" => "Test Area",
    "pim_catalog_simpleselect" => "Simple Select",
    "pim_catalog_multiselect" => "Multi Select",
    "pim_catalog_boolean" => "Boolean",
    "pim_catalog_date" => "Date",
    "pim_catalog_number" => "Number",
    "pim_catalog_metric" => "Metric",
    "pim_catalog_price_collection" => "Price Collection",
    "pim_catalog_image" => "Image",
    "pim_catalog_file" => "File",
    "pim_catalog_asset_collection" => "Asset Collection",
    "akeneo_reference_entity" => "Reference Entitiy",
    "akeneo_reference_entity_collection" => "Reference Entitiy Colleciton",
    "pim_reference_data_simpleselect" => "Reference Entity Simple Select",
    "pim_reference_data_multiselect" => "Reference Entitiy Multi Select",
    "pim_catalog_table" => "Table",
];

//Auth credentials
$baseUri = $_ENV["BASE_URI"];
$clientId = $_ENV["CLIENT_ID"];
$secret = $_ENV["SECRET"];
$username = $_ENV["USERNAME"];
$password = $_ENV["PASSWORD"];

$perPage = 10;
$associationGroups = null;
$productWithAssocs = [];

$clientBuilder = new AkeneoPimClientBuilder($baseUri);
$client = $clientBuilder->buildAuthenticatedByPassword(
    $clientId,
    $secret,
    $username,
    $password
);

//Authenticate
$token = $client->getToken();

//ATTRIBUTES
$allAttributes = [];
$attributeRequest = $client->getAttributeApi()->all();
foreach ($attributeRequest as $attr) {
    $allAttributes[] = $attr['code'];
}

//FAMILIES
$allFamilies = [];
$familiesRequest = $client->getFamilyApi()->all();
foreach ($familiesRequest as $fam) {
    foreach ($fam['attributes'] as $famAttr) {
        $allFamilies[] = $famAttr;
    }
}

/**
 * @param $allAttributes
 * @param $allFamilies
 * @param $familiesRequest
 * @return void
 */
function getUnusedProductAttributes($allAttributes, $allFamilies, $familiesRequest): void
{
    // USE CASE 1: UNUSED ATTRIBUTES
    $nonUsedAttributes = array_diff($allAttributes, $allFamilies);

    echo "\n\n\n";
    echo "Unused Attributes within the PIM: \n";

    foreach ($nonUsedAttributes as $attr) {
        echo $_ENV['BASE_URI'] . "#/configuration/attribute/" . red($attr) . "/edit \n";
    }

    $familiesWithAttributes = [];
    foreach ($familiesRequest as $family) {
        $familiesWithAttributes[$family['code']] = $family['attributes'];
    }

    //USE CASE 2: Families with the number of attributes
    $keyAttributesCount = [];
    foreach ($familiesWithAttributes as $key => $values) {
        $keyAttributesCount[$key] = count($values);
    }

    asort($keyAttributesCount);

    echo "\n\n\n";
    echo "Families with number of attributes: \n";

    foreach ($keyAttributesCount as $fam => $attrCount) {
        echo $_ENV["BASE_URI"] . "#/configuration/family/" . red($fam) . "/edit - " . red($attrCount) . "\n";
    }
}

/**
 * @param $familiesRequest
 * @return void
 */
function getDuplicateFamiles($familiesRequest): void
{
    $familiesWithAttributes = [];
    foreach ($familiesRequest as $family) {
        $familiesWithAttributes[$family['code']] = $family['attributes'];
    }
    // Get keys with exactly the same values
    $keysWithSameValues = getKeysWithSameValues($familiesWithAttributes);

    echo "\n\n\n";
    echo "\n\n\n";
    echo "Duplicate families with the exact same attributes (including required)... \n";
    foreach ($keysWithSameValues as $key => $value) {
        echo "The family: '" . red($key) . "' is a duplicate of: " . red(implode(', ', $value['duplicate_of'])) . "\n";
        echo "They share the following duplicate attributes: \n";
        foreach ($value['attributes'] as $attr) {
            echo $_ENV['BASE_URI'] . "#/configuration/attribute/" . red($attr) . "/edit \n";
        }
        echo "\n\n";
    }
}

// Function to compare arrays and find identical values
/**
 * @param $data
 * @return array
 */
function getKeysWithSameValues($data): array
{
    $result = [];
    foreach ($data as $key1 => $array1) {
        foreach ($data as $key2 => $array2) {
            if ($key1 !== $key2 && $array1 === $array2) {
                $result[$key1]['duplicate_of'][] = $key2;
                $result[$key1]['attributes'] = $array1;
            }
        }
    }
    return $result;
}

/**
 * @param $client
 * @return void
 */
function getAssetFamilies($client): void
{
    //USE CASE 4: ASSET FAMILIES
    $assetFamilies = $client->getAssetFamilyApi()->all();

    $assetFamiliesWithAttributes = [];
    foreach ($assetFamilies as $assetFamily) {
        $assetFamiliesWithAttributes[$assetFamily['code']]['structure'] = $assetFamily;
        $assetFamiliesWithAttributes[$assetFamily['code']]['asset_attributes'] = $client->getAssetAttributeApi()->all($assetFamily['code']);
    }

    //restructure array for duplicate finder method
    foreach ($assetFamiliesWithAttributes as $key => $value) {
        echo "Asset Family Code: " . red($key) . "\n";
        foreach ($value['asset_attributes'] as $attr) {
            echo " - ";
            if ($attr['is_required_for_completeness']) {
                echo "*";
            }
            echo red($attr['code']);
            echo " (" . $attr['type'] . ") ";
            if ($attr['value_per_locale'] || $attr['value_per_channel']) {
                echo "[";
                echo($attr['value_per_locale'] ? "localised " : "");
                echo($attr['value_per_channel'] ? "scopable" : "");
                echo "]";
            }
            if ($value['structure']['attribute_as_main_media'] == $attr['code']) {
                echo "--> Main media image";
            }
            echo "\n";
        }
        echo "\n";
    }
}

$referenceRequest = $client->getReferenceEntityApi()->all();
$referenceEntitiesWithAttributes = [];
foreach($referenceRequest as $referenceEntity) {
    $referenceEntitiesWithAttributes[$referenceEntity['code']]['structure'] = $referenceEntity;
    $referenceEntitiesWithAttributes[$referenceEntity['code']]['entity_attributes'] = $client->getReferenceEntityAttributeApi()->all($referenceEntity['code']);
    $referenceEntitiesWithAttributes[$referenceEntity['code']]['count'] = 0;

    $referenceEntityRecords = $client->getReferenceEntityRecordApi()->all($referenceEntity['code']);
    $count = 0;
    foreach ($referenceEntityRecords as $records) {
        $count++;
    }
    $referenceEntitiesWithAttributes[$referenceEntity['code']]['count'] = $count;

    //restructure array for duplicate finder method
    foreach ($referenceEntitiesWithAttributes as $key => $value) {
        echo "Reference Entity Code: " . red($key) . "\n";
        foreach ($value['entity_attributes'] as $attr) {
            echo " - ";
            if ($attr['is_required_for_completeness']) {
                echo "*";
            }
            echo red($attr['code']);
            echo " (" . $attr['type'] . ") ";
            if ($attr['value_per_locale'] || $attr['value_per_channel']) {
                echo "[";
                echo($attr['value_per_locale'] ? "localised " : "");
                echo($attr['value_per_channel'] ? "scopable" : "");
                echo "]";
            }
            if (isset($value['structure']['attribute_as_main_media']) && $value['structure']['attribute_as_main_media'] == $attr['code']) {
                echo "--> Main media image";
            }
            echo "\n";
        }
        echo "\n";
    }
}

var_dump($referenceEntitiesWithAttributes);


getUnusedProductAttributes($allAttributes, $allFamilies, $familiesRequest);
getDuplicateFamiles($familiesRequest);
getAssetFamilies($client);
//getReferenceEntitityFamilies($client);

function getReferenceEntitiyFamilies($client)
{
}
//USE CASE 4B: Reference Entitiy Families structure.

//USE CASE 5: REFERENCE ENTITIES - are reference entities used by attributes?
// (find the count of the number of times) -


echo "end. \n";
die;

/**
 * @param $string
 * @return string
 */
function red($string): string
{
    return "\033[31m" . $string . "\033[0m";
}








