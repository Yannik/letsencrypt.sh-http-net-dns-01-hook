#!/usr/bin/env php
<?php
use LayerShifter\TLDExtract\Extract;
use Yannik\HttpNetApi;


function getRootDomain($hostname) {
    $extract = Extract::get($hostname);
    return $extract->domain . '.' . $extract->tld;
}

function findZone($api, $hostname)
{
    $sld = getRootDomain($hostname);
    $options = new \stdClass();
    $options->filter = [ 'subFilterConnective' => 'OR',
        'subFilter' => [
            [ 'field' => "ZoneName", 'value' => $sld ],
            [ 'field' => "ZoneName", 'value' => "*.$sld" ]
        ]];
    $zones = [];
    foreach ($api->zonesFindRaw($options)->response->data as $result) {
        $zones[] = $result->zoneConfig->name;
    };
    return $zones;
}

require 'vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$dotenv->required('APIKEY');

$method = $argv[1];
$domain = $argv[2];

$api = new Yannik\HttpNetApi\HttpNetApi($_ENV['APIKEY']);

$zone = findZone($api, $domain)[0];
$zoneConfig = ['name' => $zone];


if ($method == "deploy_challenge") {
    $recordsToAdd = [ [ 'name' => '_acme-challenge.' . $domain, 'type' => 'TXT', 'content' => $argv[4], 'ttl' => 60 ] ];
    $api->zoneUpdate($zoneConfig, $recordsToAdd);
}

if ($method == "clean_challenge") {
    $existingRecords = $api->recordsFindByHostname('_acme-challenge.' . $domain);
    $recordsToDelete = [];
    foreach ($existingRecords->response->data as $record) {
        if ($record->name == '_acme-challenge.' . $domain && $record->type == "TXT") {
            $recordsToDelete[] = ['id' => $record->id];
        }
    }

    $api->zoneUpdate($zoneConfig, [], $recordsToDelete);
}
