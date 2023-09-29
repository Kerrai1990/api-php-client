# Akeneo API Client Product Associations

### This is a docker service to return the number of associations per product within a pim instance

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
Products + Models Found: 794
Associations found for product... 
a6160990-0035-481d-aac7-6febb6885b9f = 9
0e9b84b7-31d4-4c02-9d2f-53cdae88d18e = 3
d07ad043-16a5-489f-8786-d5b3673ee959 = 2
end. 
```

The response gives back the UUID of the product, and the number of associations it has.
The number of associations are shown in descending order.