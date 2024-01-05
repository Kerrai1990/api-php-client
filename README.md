# Akeneo API Client Stats

This is a docker service to return:
- Reference Entities + number of records.
- Asset Familes, number of assets + any PLR setup.
- The number of associations per product within a pim instance.

## Setup

#### Unzip the file, and run:

```cp .env.example .env``` 

Update the `.env` file with the required connection information.

Now create the docker image This will install dependencies + composer packages:

```docker-compose up```

You should get the following response: 

```product_assocs_api_client-php_client-1 exited with code 0```

Run the following command to run the API call:

```docker-compose run php_client php client.php```

### Example Output

```
######################
# Reference Entities #
######################
Analysing Reference Entities... 
Reference Entities: 
- generic_color - 16 record(s). 
- fits - 17 record(s). 
- supplier - 49 record(s). 
- color - 102 record(s). 
End of Reference Entities.

######################
####### Assets #######
######################
Analysing Asset Families... 
Breakdown of Asset Families: 
- products - 1791 Assets 
- test_family - 1 Assets 
- youtube - 0 Assets

 Product Link Rules: 
- products
Naming Convention: /((?P<parent_ref>.*)--)?(?P<product_ref>.*)--(?P<attribute_ref>.*)-.*/
/home/docker/client/client.php:154:
array(2) {
  'product_selections' =>
  array(1) {
    [0] =>
    array(5) {
      'field' =>
      string(3) "sku"
      'value' =>
      string(15) "{{product_ref}}"
      'locale' =>
      NULL
      'channel' =>
      NULL
      'operator' =>
      string(1) "="
    }
  }
  'assign_assets_to' =>
  array(1) {
    [0] =>
    array(4) {
      'attribute' =>
      string(17) "{{attribute_ref}}"
      'locale' =>
      NULL
      'channel' =>
      NULL
      'mode' =>
      string(3) "add"
    }
  }
}


######################
# Prod Associations ##
######################
Analysing Product Associations... This might take a while... 
Products + Models Found: 794
Products with 1 or more associations: 
https://hemm.support.cloud.akeneo.com/#/enrich/product/a6160990-0035-481d-aac7-6febb6885b9f = 9
https://hemm.support.cloud.akeneo.com/#/enrich/product/0e9b84b7-31d4-4c02-9d2f-53cdae88d18e = 3
https://hemm.support.cloud.akeneo.com/#/enrich/product/d07ad043-16a5-489f-8786-d5b3673ee959 = 2
end. 
 
```
