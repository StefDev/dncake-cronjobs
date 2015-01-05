dncake-cronjobs
===============

Cronjobs used for dncake project

Installation
------------

* Twitter

  - Aquire  [TwitterAPIExchange.php](https://github.com/J7mbo/twitter-api-php) from J7mbo and put it in `twitter/` subfolder.

  - Create database connection file `dbconn.php` like so and put it in e.g. the `inc/` folder on your server.

    ```php
    <?php
    define("DNDBHOST", "...");
    define("DNDBUSER", "...");
    define("DNDBPASS", "...");
    define("DNDBNAME", "...");    
    ```

  - Create api connection file `apiconn.php` like so and put it also in e.g. the `inc/` folder on your server.

    ```php
    <?php
    define("DNTWITTER_CONSUMER_KEY",              "...");
    define("DNTWITTER_CONSUMER_SECRET",           "...");
    define("DNTWITTER_OAUTH_ACCESS_TOKEN",        "...");
    define("DNTWITTER_OAUTH_ACCESS_TOKEN_SECRET", "...");
    ```

  - Change the lines in `tweet.php` beginning with 'include_once' to the correct location of both files.