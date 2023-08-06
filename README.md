# AAPanel-PHP
AAPanel-PHP is a library that allows you to interact with your AAPanel account using PHP. It provides a simple and easy-to-use API that you can use to manage your AAPanel account, such as creating and managing users, domains, and websites.

This README file will guide you through the steps on how to use the AAPanel-PHP library.

## Prerequisites

* You must have an AAPanel account.
* You must have enabled the API in your AAPanel account.
* You must have your API secret key.
* You must know your AAPanel IP address and port.

## Instructions

1. Enable the API in your AAPanel account.
2. Generate a new API secret key.
3. Enter your public IP address in the IP whitelist.
4. Save your changes.

5. Create a new file and paste the following code:

```php
<?php

include_once 'api/api_aapanel_mitha.php';

$aapanel = new aapanel_api();

$aapanel->key = 'YOUR_API_KEY';
$aapanel->url = 'YOUR_AAPANEL_IP_AND_PORT';

?>
```

# Composer
This will be soon published as a composer package


# Method list :

## Show Logs

```

$aapanel->logs();

```

## Add Site

```

$aapanel->addSite($domain, $path, $desc, $type_id = 0, $type = 'php', $phpversion = '73', $port = '80', $ftp = null, $ftpusername = null, $ftppassword = null, $sql = null, $userdbase = null, $passdbase = null, $setSsl = 0, $forceSsl = 0);

```

## Add Sub Domain (must install DNS Manager with domain ready)

```

$aapanel->addSubDomain($subdomain,$maindomain,$iptarget);

```

## Delete Sub Domain (must install DNS Manager with domain ready)

```

$aapanel->deleteSubDomain($subdomain, $mainDomain, $iptarget);

```

## Update Sub Domain (must install DNS Manager with domain ready)

```

$aapanel->modifySubDomain($subdomain, $mainDomain, $iptarget, $id)

```

## unzip file (file must exist in server)

```
$aapanel->unzip($sourcefilepath,$destinationpath,$password = null);

```

## force HTTPS for site (site must exist in website tab)

```
$aapanel->forceHTTPS($sitename);

```

## apply SSL for new domain/subdomain (site must exist in website tab)

```

$aapanel->applySSL($domain, $id_site);

```

## Site list

```

$aapanel->siteList($limit,$page,$projectType,$search = null);

```

## Disable Site

```

$aapanel->disableSite($id_site,$domain);

```

## Enable Site

```

$aapanel->enableSite($id_site,$domain);

```

## Import database file

$file = complete path

```

$aapanel->importDbase($file, $dbasename);

```

## Edit file body

```

$aapanel->safeFileBody($databody,$filepath);

```
