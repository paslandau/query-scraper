#query-scraper
[![Build Status](https://travis-ci.org/paslandau/query-scraper.svg?branch=master)](https://travis-ci.org/paslandau/query-scraper)

Scraping!

##Description

Coming soon...

##Requirements

- PHP >= 5.5
- Guzzle >= 5.0.3

##Installation

The recommended way to install query-scraper is through [Composer](http://getcomposer.org/).

    curl -sS https://getcomposer.org/installer | php

Next, update your project's composer.json file to include query-scraper:

    {
        "repositories": [ { "type": "composer", "url": "http://packages.myseosolution.de/"} ],
        "minimum-stability": "dev",
        "require": {
             "paslandau/query-scraper": "dev-master"
        }
    }

After installing, you need to require Composer's autoloader:
```php

require 'vendor/autoload.php';
```