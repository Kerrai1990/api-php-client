<?php

require_once __DIR__ . '/vendor/autoload.php';

use Akeneo\Pim\ApiClient\AkeneoPimClientBuilder;
use Akeneo\Pim\ApiClient\Search\SearchBuilder;
use Akeneo\Pim\ApiClient\Exception;
use Dotenv\Dotenv;

class AkeneoAnalyzer
{
    private $client;

    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Initialise client with env creds for PIM instance.
     * Ensure read-only key for security
     * @return void
     */
    private function initializeClient(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        try {
            $clientBuilder = new AkeneoPimClientBuilder($_ENV['BASE_URI']);
            $this->client = $clientBuilder->buildAuthenticatedByPassword(
                $_ENV['CLIENT_ID'],
                $_ENV['SECRET'],
                $_ENV['USERNAME'],
                $_ENV['PASSWORD']
            );
        } catch (Exception\UnauthorizedHttpException $e) {
            echo "Authentication failed: " . $e->getMessage();
        } catch (Exception\ClientErrorHttpException $e) {
            echo "Client error: " . $e->getMessage();
        } catch (Exception\ServerErrorHttpException $e) {
            echo "Server error: " . $e->getMessage();
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            echo "Connection error (e.g., DNS issues, timeouts): " . $e->getMessage();
        } catch (Exception\InvalidArgumentException $e) {
            echo "Invalid argument: " . $e->getMessage();
        } catch (Exception\HttpException $e) {
            echo "HTTP error: " . $e->getMessage();
        } catch (\Exception $e) {
            echo "General error: " . $e->getMessage();
        }
    }

    public function analyzeReferenceEntities(): void
    {
        echo "######################\n";
        echo "# Reference Entities #\n";
        echo "######################\n";

        $referenceEntities = $this->getReferenceEntities();
        asort($referenceEntities);

        echo "Reference Entities: \n";
        foreach ($referenceEntities as $key => $value) {
            echo "- " . $this->formatRed($key) . " - $value record(s).\n";
        }
        echo "End of Reference Entities. \n\n";
    }

    private function getReferenceEntities(): array
    {
        $refApiRequest = $this->client->getReferenceEntityApi()->all();
        $referenceEntities = [];

        foreach ($refApiRequest as $entity) {
            $referenceEntityRecords = $this->client->getReferenceEntityRecordApi()->all($entity['code']);
            $count = 0;
            foreach ($referenceEntityRecords as $entityForCount) {
                $count++;
            }
            $referenceEntities[$entity['code']] = $count; //count($referenceEntityRecords);
        }

        return $referenceEntities;
    }

    public function analyzeAssetFamilies(): void
    {
        echo "######################\n";
        echo "####### Assets #######\n";
        echo "######################\n";

        $assetFamilies = $this->getAssetFamilies();

        echo "Breakdown of Asset Families: \n";
        foreach ($assetFamilies as $code => $data) {
            echo "- " . $this->formatRed($code) . " - {$data['asset_count']} Assets\n";
        }

        echo "\nProduct Link Rules: \n";
        foreach ($assetFamilies as $code => $data) {
            echo "- $code\n";
            if (isset($data['naming_convention']['pattern'])) {
                echo "Naming Convention: " . $this->formatRed($data['naming_convention']['pattern']) . "\n";
            }

            if (isset($data['product_link_rule'])) {
                foreach ($data['product_link_rule'] as $rule) {
                    var_dump($rule);
                }
            }
        }

        echo "End of Asset Families.\n\n";
    }

    private function getAssetFamilies(): array
    {
        $assetFamilies = $this->client->getAssetFamilyApi()->all();
        $result = [];

        foreach ($assetFamilies as $family) {
            $assets = $this->client->getAssetManagerApi()->all($family['code']);

            $count = 0;
            foreach ($assetFamilies as $assetsForCount) {
                $count++;
            }

            $result[$family['code']] = [
                'asset_count' => $count,
                'naming_convention' => $family['naming_convention'] ?? null,
                'product_link_rule' => $family['product_link_rules'] ?? null,
            ];
        }

        ksort($result);
        return $result;
    }

    public function analyzeProductAssociations(): void
    {
        echo "######################\n";
        echo "# Product Associations #\n";
        echo "######################\n";

        $productAssociations = $this->getProductAssociations();
        arsort($productAssociations);

        echo "Products with 20 or more associations: \n";
        foreach ($productAssociations as $product => $count) {
            if ($count > 20) {
                echo $_ENV['BASE_URI'] . "#/enrich/product/" . $this->formatRed($product) . " = $count\n";
            }
        }
    }

    private function getProductAssociations(): array
    {
        $searchBuilder = new SearchBuilder();
        $searchFilters = $searchBuilder->getFilters();

        $products = $this->client->getProductApi()->all(100);
        $productAssociations = [];

        foreach ($products as $product) {
            $productAssociations[$product['uuid']] = 0;

            if (isset($product['associations'])) {
                foreach ($product['associations'] as $assoc) {
                    $productAssociations[$product['uuid']] += count($assoc['products'] ?? []) + count($assoc['product_models'] ?? []);
                }
            }
        }

        return $productAssociations;
    }

    private function formatRed(string $text): string
    {
        return "\033[31m$text\033[0m";
    }
}

// Usage
$analyzer = new AkeneoAnalyzer();
$analyzer->analyzeReferenceEntities();
$analyzer->analyzeAssetFamilies();
$analyzer->analyzeProductAssociations();
