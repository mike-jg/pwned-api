# Pwned API

This is meant as a simple way to interface with the service at https://haveibeenpwned.com/API/v2/

[![Build Status](https://travis-ci.org/mike-jg/pwned-api.svg?branch=master)](https://travis-ci.org/mike-jg/pwned-api)

## Installation

```
$ composer require mike-jg/pwned-api
```

## Basic Usage

The preferred way to interact with the API is to use the `Client::searchByRange` 
method, as this protects the value of the source password being searched for. 
See https://haveibeenpwned.com/API/v2/#SearchingPwnedPasswordsByRange

```php
<?php
use PwnedApi\Client;

$client = new Client();

$password = sha1("P@ssw0rd");
$rangeResult = $client->searchByRange($password);

// Boolean: was this password found?
echo $rangeResult->wasFound();
// How many times this password was found in the database
echo $rangeResult->getCount();
```

Alternatively you can also search by a specific password to see if it was found.
See https://haveibeenpwned.com/API/v2/#SearchingPwnedPasswordsByPassword

```php
<?php
use PwnedApi\Client;
   
$client = new Client();

$password = sha1("P@ssw0rd");
$rangeResult = $client->searchByPasswordHash($password);

// Boolean: was this password found?
echo $rangeResult->wasFound();
// How many times this password was found in the database
echo $rangeResult->getCount();
```

## Overriding the HTTP client

To specify a different HTTP client to use, e.g. if you need to inject proxy details.

```php
<?php
use PwnedApi\Client;

$client = new Client();
$client->setHttpClient(new GuzzleHttp\Client());
```