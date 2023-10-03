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

//Auth creds
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

echo "Analysing Reference Entities...";
analyzeReferenceEntities($client);

echo "Analysing Asset Families...";
analyzeAssetFamilies($client);

echo "Analysing Product Associations... This might take a while...";
analyzeProductAssociations($client);

/**
 * @param $client
 * @return void
 * Retrieve the number of records per reference entity.
 */
function analyzeReferenceEntities($client): void
{
    //REFERENCE ENTITIES + RECORDS
    $refApiRequest = $client->getReferenceEntityApi()->all();
    $referenceEntities = [];
    foreach ($refApiRequest as $referenceEntitiy) {
        $referenceEntities[$referenceEntitiy['code']] = null;
    }

    foreach ($referenceEntities as $key => $value) {
        $referenceEntityRecords = $client->getReferenceEntityRecordApi()->all($key);
        $count = 0;
        foreach ($referenceEntityRecords as $records) {
            $count++;
            $referenceEntities[$key] = $count ?? 0;
        }
        $count = 0;
    }

    asort($referenceEntities);

    echo "Reference Entities: \n";
    foreach ($referenceEntities as $key => $value) {
        echo "Name: " . $key . " - " . $value . " record(s). \n";
    }
    echo "End Reference Entities. \n";
}

/**
 * @param $client
 * @return void
 * Retrieve number of assets per asset family
 */
function analyzeAssetFamilies($client): void
{
    // ASSET FAMILIES + NUMBER OF ASSETS
    $assetFamilies = $client->getAssetFamilyApi()->all();
    $assetFamilyWithNumberOfAssets = [];
    foreach ($assetFamilies as $assetFamily) {

        $assetFamilyCode = $assetFamily['code'];
        $assetFamilyNamingConvention = $assetFamily['naming_convention'];
        $assetFamilyProductLinkRule = $assetFamily['product_link_rules'];
        $assetsForFamilyPageSize = $client->getAssetManagerApi()->all($assetFamilyCode);

        //Todo: This is stupid (There's got to be a way to count the number of assets!!!).
        $assetCount = 0;
        foreach ($assetsForFamilyPageSize as $asset) {
            $assetCount++;
        }

        $assetFamilyWithNumberOfAssets[$assetFamilyCode] = [
            'asset_count' => $assetCount,
            'naming_convention' => $assetFamilyNamingConvention,
            'product_link_rule' => $assetFamilyProductLinkRule,
        ];

        asort($assetFamilyWithNumberOfAssets);
    }

    echo "Breakdown of Asset Families: \n";
    foreach ($assetFamilyWithNumberOfAssets as $key => $value) {
        echo "Asset Family: " . $key . " - " . $value['asset_count'] . " Assets \n";
        if (isset($value['naming_convention']['pattern'])) {
            echo isset($value['naming_convention']) ? "Naming Convention: " . $value['naming_convention']['pattern'] . "\n" : "";
        }
        if (isset($value['product_link_rule'])) {
            foreach ($value['product_link_rule'] as $plr) {
                var_dump($plr);
            }
        }
    }
    echo "End of Asset Families.\n\n";
}

/**
 * @param $client
 * @return void
 * Retrieve product associations, order by number of associations.
 */
function analyzeProductAssociations($client): void
{
    //Build Search Query
    $searchBuilder = new SearchBuilder();
    //$searchBuilder->addFilter('enabled', '=', true);
    $searchFilters = $searchBuilder->getFilters();

    //PRODUCTS + ASSOCIATIONS
    // Get all products (all pages), save into $apiRequest object
    $apiRequest = $client->getProductApi()->all(100);
    $productWithAssocs = aggregateAssociationsPerProduct($apiRequest);

    echo("Products + Models Found: " . count($productWithAssocs) . "\n");

    //Ordered by number of associations.
    //Only show products with 1 or more associations.
    arsort($productWithAssocs);
    echo "Products with 1 or more associations: \n";
    foreach ($productWithAssocs as $key => $value) {
        if ($value > 0) {
            echo $_ENV["BASE_URI"] . "#/enrich/product/" . $key . " = " . $value . "\n";
        }
    }
}

/**
 * Function to retrieve the number of associations per product
 * @param $page
 * @param $productWithAssocs
 * @return array
 */
function aggregateAssociationsPerProduct($page): array
{
    //Run through each product, and capture their 'associations'.
    //Taking each assoc, count the number
    foreach ($page as $product) {

        $productWithAssocs[$product["uuid"]] = 0;

        //Get associations from the product
        if (isset($product["associations"])) {

            //Create a temp array of associations
            $associationGroups = array_fill_keys(
                array_values(
                    array_keys($product["associations"])
                ), 0
            );

            //Foreach association key, add up the number of products + product models found
            foreach ($associationGroups as $key => $value) {
                $productsFound = count($product["associations"][$key]["products"]);
                $productsModelsFound = count($product["associations"][$key]["product_models"]);
                $productWithAssocs[$product["uuid"]] += ($productsFound + $productsModelsFound);
            }
            echo "Product: " . $product['uuid'] . " - " . $productWithAssocs[$product['uuid']] . "associations \n";
        }
    }

    return $productWithAssocs;
}

function analyseProductFamilies($client)
{
    //Get Duplicate Family attributes
    //$familiesWithAttributeRequirements = [];
    //$families = $client->getFamilyApi()->all(100);
    ////$families[] = $client->getFamilyApi()->get('accessories');
    //
    //foreach ($families as $family) {
    //    $familiesWithAttributeRequirements[$family['code']] = [
    //        'attributes' => $family['attributes'],
    //        'attribute_requirements' => $family['attribute_requirements'],
    //    ];
    //    $attributeWithType = [];
    //    foreach ($family['attributes'] as $attribute) {
    //        $attributeType = $client->getAttributeApi()->get($attribute)['type'];
    //        $attributeWithType[] = [
    //            'attribute' => $attribute,
    //            'attribute_type' => $attributeType
    //        ];
    //    }
    //    $familiesWithAttributeRequirements[$family['code']]['attributes'] = $attributeWithType;
    //    $attributeWithType = [];
    //}
    //
    //$requiredAttribute = [];
    //
    //echo "Breakdown of Families and their attributes \n";
    //foreach ($familiesWithAttributeRequirements as $key => $values) {
    //    echo "Family: '" . $key . "'\n";
    //    foreach ($values['attributes'] as $attribute) {
    //        foreach ($values['attribute_requirements'] as $key => $value) {
    //            if (in_array($attribute['attribute'], $value)) {
    //                $requiredAttribute[$attribute['attribute']][] = $key;
    //            }
    //        }
    //
    //        echo $attribute['attribute'] . " - " . strtolower($ATTRIBUTE_TYPES[$attribute['attribute_type']]);
    //        if (isset($requiredAttribute[$attribute['attribute']])) {
    //            echo " (Required for channel(s): " . implode(", ", $requiredAttribute[$attribute['attribute']]) . ")";
    //        }
    //        echo "\n";
    //
    //        unset($requiredAttribute);
    //    }
    //}
    //echo "End of Families + Attributes \n\n";
    //die;
}

echo "end. \n";
