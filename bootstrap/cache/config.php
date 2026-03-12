<?php return array (
  'broadcasting' => 
  array (
    'default' => 'log',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'host' => NULL,
          'port' => 443,
          'scheme' => 'https',
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'cluster' => NULL,
          'host' => 'api-mt1.pusher.com',
          'port' => 443,
          'scheme' => 'https',
          'encrypted' => true,
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => '12',
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => 'C:\\Users\\yousa\\aston-hill-crm\\resources\\views',
    ),
    'compiled' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\framework\\views',
  ),
  'app' => 
  array (
    'name' => 'Laravel',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://127.0.0.1:8000',
    'frontend_url' => 'http://localhost:3000',
    'asset_url' => NULL,
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => 'base64:ARFOFJfYz8mz3cp5CJOk5pbiRdeZCzL7QKJG2u0tnyg=',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => 'users',
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
    'disable_google_authentication' => false,
  ),
  'cache' => 
  array (
    'default' => 'database',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'cache',
        'lock_connection' => NULL,
        'lock_table' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\framework/cache/data',
        'lock_path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'stores' => 
        array (
          0 => 'database',
          1 => 'array',
        ),
      ),
    ),
    'prefix' => 'laravel-cache-',
    'user_prime_ttl' => 300,
    'user_extras_ttl' => 600,
  ),
  'colors' => 
  array (
    'primary' => 'indigo-600',
    'primary_hover' => 'indigo-700',
    'success' => 'green-600',
    'danger' => 'red-600',
    'warning' => 'orange-400',
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => '*',
    ),
    'allowed_origins' => 
    array (
      0 => 'http://127.0.0.1:5173',
      1 => 'http://localhost:5173',
      2 => 'http://127.0.0.1:8000',
      3 => 'http://localhost:8000',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => true,
  ),
  'database' => 
  array (
    'default' => 'sqlite',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'C:\\Users\\yousa\\aston-hill-crm\\database\\database.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => NULL,
        'journal_mode' => NULL,
        'synchronous' => NULL,
        'transaction_mode' => 'DEFERRED',
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'mariadb' => 
      array (
        'driver' => 'mariadb',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => 'localhost',
        'port' => '1433',
        'database' => 'laravel',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'laravel-database-',
        'persistent' => false,
      ),
      'default' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
      'cache' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
    ),
  ),
  'excel' => 
  array (
    'exports' => 
    array (
      'chunk_size' => 1000,
      'pre_calculate_formulas' => false,
      'strict_null_comparison' => false,
      'csv' => 
      array (
        'delimiter' => ',',
        'enclosure' => '"',
        'line_ending' => '
',
        'use_bom' => false,
        'include_separator_line' => false,
        'excel_compatibility' => false,
        'output_encoding' => '',
        'test_auto_detect' => true,
      ),
      'properties' => 
      array (
        'creator' => '',
        'lastModifiedBy' => '',
        'title' => '',
        'description' => '',
        'subject' => '',
        'keywords' => '',
        'category' => '',
        'manager' => '',
        'company' => '',
      ),
    ),
    'imports' => 
    array (
      'read_only' => true,
      'ignore_empty' => false,
      'heading_row' => 
      array (
        'formatter' => 'slug',
      ),
      'csv' => 
      array (
        'delimiter' => NULL,
        'enclosure' => '"',
        'escape_character' => '\\',
        'contiguous' => false,
        'input_encoding' => 'guess',
      ),
      'properties' => 
      array (
        'creator' => '',
        'lastModifiedBy' => '',
        'title' => '',
        'description' => '',
        'subject' => '',
        'keywords' => '',
        'category' => '',
        'manager' => '',
        'company' => '',
      ),
      'cells' => 
      array (
        'middleware' => 
        array (
        ),
      ),
    ),
    'extension_detector' => 
    array (
      'xlsx' => 'Xlsx',
      'xlsm' => 'Xlsx',
      'xltx' => 'Xlsx',
      'xltm' => 'Xlsx',
      'xls' => 'Xls',
      'xlt' => 'Xls',
      'ods' => 'Ods',
      'ots' => 'Ods',
      'slk' => 'Slk',
      'xml' => 'Xml',
      'gnumeric' => 'Gnumeric',
      'htm' => 'Html',
      'html' => 'Html',
      'csv' => 'Csv',
      'tsv' => 'Csv',
      'pdf' => 'Dompdf',
    ),
    'value_binder' => 
    array (
      'default' => 'Maatwebsite\\Excel\\DefaultValueBinder',
    ),
    'cache' => 
    array (
      'driver' => 'memory',
      'batch' => 
      array (
        'memory_limit' => 60000,
      ),
      'illuminate' => 
      array (
        'store' => NULL,
      ),
      'default_ttl' => 10800,
    ),
    'transactions' => 
    array (
      'handler' => 'db',
      'db' => 
      array (
        'connection' => NULL,
      ),
    ),
    'temporary_files' => 
    array (
      'local_path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\framework/cache/laravel-excel',
      'local_permissions' => 
      array (
      ),
      'remote_disk' => NULL,
      'remote_prefix' => NULL,
      'force_resync_remote' => NULL,
    ),
  ),
  'field_submissions' => 
  array (
    'team_roles' => 
    array (
      'manager' => 'manager',
      'team_leader' => 'team_leader',
      'sales_agent' => 'sales_agent',
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\app/private',
        'serve' => true,
        'throw' => false,
        'report' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\app/public',
        'url' => 'http://127.0.0.1:8000/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
      ),
    ),
    'links' => 
    array (
      'C:\\Users\\yousa\\aston-hill-crm\\public\\storage' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\app/public',
    ),
  ),
  'geoip' => 
  array (
    'log_failures' => true,
    'include_currency' => true,
    'service' => NULL,
    'services' => 
    array (
      'maxmind_database' => 
      array (
        'class' => 'Torann\\GeoIP\\Services\\MaxMindDatabase',
        'database_path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\app/geoip.mmdb',
        'update_url' => 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=&suffix=tar.gz',
        'locales' => 
        array (
          0 => 'en',
        ),
      ),
      'maxmind_api' => 
      array (
        'class' => 'Torann\\GeoIP\\Services\\MaxMindWebService',
        'user_id' => NULL,
        'license_key' => NULL,
        'locales' => 
        array (
          0 => 'en',
        ),
      ),
      'ipgeolocation' => 
      array (
        'class' => 'Torann\\GeoIP\\Services\\IPGeoLocation',
        'secure' => true,
        'key' => NULL,
        'continent_path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\app/continents.json',
        'lang' => 'en',
      ),
      'ipdata' => 
      array (
        'class' => 'Torann\\GeoIP\\Services\\IPData',
        'key' => NULL,
        'secure' => true,
      ),
      'ipfinder' => 
      array (
        'class' => 'Torann\\GeoIP\\Services\\IPFinder',
        'key' => NULL,
        'secure' => true,
        'locales' => 
        array (
          0 => 'en',
        ),
      ),
    ),
    'cache' => 'all',
    'cache_tags' => 
    array (
      0 => 'torann-geoip-location',
    ),
    'cache_expires' => 30,
    'default_location' => 
    array (
      'ip' => '127.0.0.0',
      'iso_code' => 'US',
      'country' => 'United States',
      'city' => 'New Haven',
      'state' => 'CT',
      'state_name' => 'Connecticut',
      'postal_code' => '06510',
      'lat' => 41.31,
      'lon' => -72.92,
      'timezone' => 'America/New_York',
      'continent' => 'NA',
      'default' => true,
      'currency' => 'USD',
    ),
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\logs/laravel.log',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\logs/laravel.log',
        'level' => 'debug',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'handler_with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'formatter' => NULL,
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
        'facility' => 8,
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\logs/laravel.log',
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'log',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'scheme' => NULL,
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '2525',
        'username' => NULL,
        'password' => NULL,
        'timeout' => NULL,
        'local_domain' => '127.0.0.1',
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
        'retry_after' => 60,
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
        'retry_after' => 60,
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@example.com',
      'name' => 'Laravel',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => 'C:\\Users\\yousa\\aston-hill-crm\\resources\\views/vendor/mail',
      ),
    ),
  ),
  'modules' => 
  array (
    'users' => 
    array (
      'model' => 'App\\Models\\User',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'name' => 
        array (
          'label' => 'User',
          'filter' => 'text',
          'sortable' => true,
        ),
        'email' => 
        array (
          'label' => 'Email',
          'filter' => 'text',
          'sortable' => true,
        ),
        'phone' => 
        array (
          'label' => 'Phone',
          'filter' => 'text',
          'sortable' => true,
        ),
        'country' => 
        array (
          'label' => 'Country',
          'filter' => 'text',
          'sortable' => true,
        ),
        'roles' => 
        array (
          'label' => 'Assigned Roles',
          'filter' => NULL,
          'sortable' => false,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'last_login_at' => 
        array (
          'label' => 'Last Login',
          'filter' => NULL,
          'sortable' => false,
        ),
        'created_at' => 
        array (
          'label' => 'Created Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'employee_number' => 
        array (
          'label' => 'Employee ID',
          'filter' => 'text',
          'sortable' => true,
        ),
        'department' => 
        array (
          'label' => 'Department',
          'filter' => 'text',
          'sortable' => true,
        ),
        'extension' => 
        array (
          'label' => 'Extension',
          'filter' => 'text',
          'sortable' => true,
        ),
        'joining_date' => 
        array (
          'label' => 'Joining Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'terminate_date' => 
        array (
          'label' => 'Terminate Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'monthly_target' => 
        array (
          'label' => 'Monthly Target',
          'filter' => NULL,
          'sortable' => true,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'name',
        2 => 'email',
        3 => 'phone',
        4 => 'country',
        5 => 'roles',
        6 => 'status',
        7 => 'monthly_target',
        8 => 'last_login_at',
        9 => 'created_at',
      ),
      'default_sort' => 
      array (
        0 => 'name',
        1 => 'asc',
      ),
    ),
    'default_columns' => 
    array (
      'admin' => 
      array (
        0 => 'id',
        1 => 'name',
        2 => 'email',
        3 => 'status',
      ),
      'manager' => 
      array (
        0 => 'name',
        1 => 'email',
      ),
      'user' => 
      array (
        0 => 'name',
      ),
    ),
    'lead_submissions' => 
    array (
      'model' => 'App\\Models\\LeadSubmission',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'submitted_at' => 
        array (
          'label' => 'Lead Creation Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'submission_type' => 
        array (
          'label' => 'Request Type',
          'filter' => NULL,
          'sortable' => false,
        ),
        'account_number' => 
        array (
          'label' => 'Account Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'company_name' => 
        array (
          'label' => 'Company Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'authorized_signatory_name' => 
        array (
          'label' => 'Authorized Signatory',
          'filter' => 'text',
          'sortable' => false,
        ),
        'email' => 
        array (
          'label' => 'Email',
          'filter' => 'text',
          'sortable' => true,
        ),
        'contact_number_gsm' => 
        array (
          'label' => 'Contact Number',
          'filter' => 'text',
          'sortable' => false,
        ),
        'alternate_contact_number' => 
        array (
          'label' => 'Alternate Contact',
          'filter' => 'text',
          'sortable' => false,
        ),
        'address' => 
        array (
          'label' => 'Address',
          'filter' => NULL,
          'sortable' => false,
        ),
        'emirate' => 
        array (
          'label' => 'Emirate',
          'filter' => 'text',
          'sortable' => false,
        ),
        'location_coordinates' => 
        array (
          'label' => 'Location Coordinates',
          'filter' => NULL,
          'sortable' => false,
        ),
        'category' => 
        array (
          'label' => 'Service Category',
          'filter' => 'select',
          'sortable' => true,
        ),
        'type' => 
        array (
          'label' => 'Service Type',
          'filter' => 'select',
          'sortable' => true,
        ),
        'product' => 
        array (
          'label' => 'Product',
          'filter' => 'text',
          'sortable' => true,
        ),
        'offer' => 
        array (
          'label' => 'Offer',
          'filter' => NULL,
          'sortable' => false,
        ),
        'mrc_aed' => 
        array (
          'label' => 'MRC (AED)',
          'filter' => NULL,
          'sortable' => true,
        ),
        'quantity' => 
        array (
          'label' => 'Qty',
          'filter' => NULL,
          'sortable' => true,
        ),
        'ae_domain' => 
        array (
          'label' => 'AE Domain',
          'filter' => NULL,
          'sortable' => false,
        ),
        'gaid' => 
        array (
          'label' => 'GAID',
          'filter' => NULL,
          'sortable' => false,
        ),
        'previous_activity' => 
        array (
          'label' => 'Old Activity',
          'filter' => 'text',
          'sortable' => false,
        ),
        'resubmission_reason' => 
        array (
          'label' => 'Resubmission Reason',
          'filter' => NULL,
          'sortable' => false,
        ),
        'remarks' => 
        array (
          'label' => 'Remarks',
          'filter' => NULL,
          'sortable' => false,
        ),
        'sales_agent' => 
        array (
          'label' => 'Sales Agent',
          'filter' => NULL,
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'sla_timer' => 
        array (
          'label' => 'SLA Timer',
          'filter' => NULL,
          'sortable' => false,
        ),
        'executive' => 
        array (
          'label' => 'Back Office Executive',
          'filter' => NULL,
          'sortable' => true,
        ),
        'status_changed_at' => 
        array (
          'label' => 'Last Updated',
          'filter' => 'date',
          'sortable' => true,
        ),
        'updated_at' => 
        array (
          'label' => 'Updated',
          'filter' => 'date',
          'sortable' => true,
        ),
        'creator' => 
        array (
          'label' => 'Created By',
          'filter' => NULL,
          'sortable' => false,
        ),
        'call_verification' => 
        array (
          'label' => 'Call Verification',
          'filter' => NULL,
          'sortable' => false,
        ),
        'pending_from_sales' => 
        array (
          'label' => 'Pending From Sales',
          'filter' => NULL,
          'sortable' => false,
        ),
        'documents_verification' => 
        array (
          'label' => 'Documents Verification',
          'filter' => NULL,
          'sortable' => false,
        ),
        'submission_date_from' => 
        array (
          'label' => 'Submission Date From',
          'filter' => 'date',
          'sortable' => false,
        ),
        'back_office_notes' => 
        array (
          'label' => 'Back Office Notes',
          'filter' => NULL,
          'sortable' => false,
        ),
        'activity' => 
        array (
          'label' => 'Activity',
          'filter' => NULL,
          'sortable' => false,
        ),
        'back_office_account' => 
        array (
          'label' => 'Back Office Account',
          'filter' => NULL,
          'sortable' => false,
        ),
        'work_order' => 
        array (
          'label' => 'Work Order',
          'filter' => NULL,
          'sortable' => false,
        ),
        'du_status' => 
        array (
          'label' => 'DU Status',
          'filter' => NULL,
          'sortable' => false,
        ),
        'completion_date' => 
        array (
          'label' => 'Completion Date',
          'filter' => 'date',
          'sortable' => false,
        ),
        'du_remarks' => 
        array (
          'label' => 'DU Remarks',
          'filter' => NULL,
          'sortable' => false,
        ),
        'additional_note' => 
        array (
          'label' => 'Additional Note',
          'filter' => NULL,
          'sortable' => false,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'submitted_at',
        2 => 'submission_type',
        3 => 'account_number',
        4 => 'company_name',
        5 => 'authorized_signatory_name',
        6 => 'email',
        7 => 'contact_number_gsm',
        8 => 'alternate_contact_number',
        9 => 'address',
        10 => 'emirate',
        11 => 'location_coordinates',
        12 => 'category',
        13 => 'type',
        14 => 'product',
        15 => 'offer',
        16 => 'mrc_aed',
        17 => 'quantity',
        18 => 'ae_domain',
        19 => 'gaid',
        20 => 'previous_activity',
        21 => 'resubmission_reason',
        22 => 'remarks',
        23 => 'manager',
        24 => 'team_leader',
        25 => 'sales_agent',
        26 => 'status',
        27 => 'executive',
        28 => 'sla_timer',
        29 => 'status_changed_at',
        30 => 'creator',
      ),
      'default_sort' => 
      array (
        0 => 'submitted_at',
        1 => 'desc',
      ),
      'sla_days' => 7,
    ),
    'field_submissions' => 
    array (
      'model' => 'App\\Models\\FieldSubmission',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'created_at' => 
        array (
          'label' => 'Submitted At',
          'filter' => 'date',
          'sortable' => true,
        ),
        'account_number' => 
        array (
          'label' => 'Account Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'company_name' => 
        array (
          'label' => 'Company Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'authorized_signatory_name' => 
        array (
          'label' => 'Authorized Signatory',
          'filter' => 'text',
          'sortable' => false,
        ),
        'contact_number' => 
        array (
          'label' => 'Contact Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'product' => 
        array (
          'label' => 'Product',
          'filter' => 'text',
          'sortable' => true,
        ),
        'alternate_number' => 
        array (
          'label' => 'Alternate Contact Number',
          'filter' => 'text',
          'sortable' => false,
        ),
        'emirates' => 
        array (
          'label' => 'Emirates',
          'filter' => 'text',
          'sortable' => true,
        ),
        'location_coordinates' => 
        array (
          'label' => 'Location Coordinates',
          'filter' => NULL,
          'sortable' => false,
        ),
        'complete_address' => 
        array (
          'label' => 'Address',
          'filter' => NULL,
          'sortable' => false,
        ),
        'additional_notes' => 
        array (
          'label' => 'Additional Notes',
          'filter' => NULL,
          'sortable' => false,
        ),
        'special_instruction' => 
        array (
          'label' => 'Special Instruction',
          'filter' => NULL,
          'sortable' => false,
        ),
        'sales_agent' => 
        array (
          'label' => 'Sales Agent',
          'filter' => NULL,
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'field_agent' => 
        array (
          'label' => 'Field Agent',
          'filter' => NULL,
          'sortable' => true,
        ),
        'field_status' => 
        array (
          'label' => 'Field Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'target_date' => 
        array (
          'label' => 'Target Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'remarks_by_field_agent' => 
        array (
          'label' => 'Field Agent Remarks',
          'filter' => NULL,
          'sortable' => false,
        ),
        'sla_timer' => 
        array (
          'label' => 'SLA Timer',
          'filter' => NULL,
          'sortable' => true,
        ),
        'sla_status' => 
        array (
          'label' => 'SLA Status',
          'filter' => NULL,
          'sortable' => true,
        ),
        'last_updated' => 
        array (
          'label' => 'Last Updated',
          'filter' => 'date',
          'sortable' => true,
        ),
        'creator' => 
        array (
          'label' => 'Created By',
          'filter' => NULL,
          'sortable' => false,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'created_at',
        2 => 'account_number',
        3 => 'company_name',
        4 => 'authorized_signatory_name',
        5 => 'contact_number',
        6 => 'product',
        7 => 'alternate_number',
        8 => 'emirates',
        9 => 'location_coordinates',
        10 => 'complete_address',
        11 => 'manager',
        12 => 'team_leader',
        13 => 'sales_agent',
        14 => 'additional_notes',
        15 => 'special_instruction',
        16 => 'field_agent',
        17 => 'field_status',
        18 => 'target_date',
        19 => 'remarks_by_field_agent',
        20 => 'sla_timer',
        21 => 'sla_status',
        22 => 'last_updated',
        23 => 'creator',
      ),
      'default_sort' => 
      array (
        0 => 'created_at',
        1 => 'desc',
      ),
    ),
    'customer_support_submissions' => 
    array (
      'model' => 'App\\Models\\CustomerSupportSubmission',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'submitted_at' => 
        array (
          'label' => 'Submission Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'ticket_number' => 
        array (
          'label' => 'Ticket ID',
          'filter' => 'text',
          'sortable' => true,
        ),
        'account_number' => 
        array (
          'label' => 'Account Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'company_name' => 
        array (
          'label' => 'Company Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'issue_category' => 
        array (
          'label' => 'Issue Category',
          'filter' => 'select',
          'sortable' => true,
        ),
        'contact_number' => 
        array (
          'label' => 'Contact Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'alternate_contact_number' => 
        array (
          'label' => 'Alternate Contact Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'issue_description' => 
        array (
          'label' => 'Issue Description',
          'filter' => NULL,
          'sortable' => false,
        ),
        'attachments' => 
        array (
          'label' => 'Attachments',
          'filter' => NULL,
          'sortable' => false,
        ),
        'creator' => 
        array (
          'label' => 'Submitted By',
          'filter' => NULL,
          'sortable' => false,
        ),
        'csr' => 
        array (
          'label' => 'CSR Name',
          'filter' => NULL,
          'sortable' => true,
        ),
        'sla_timer' => 
        array (
          'label' => 'SLA Timer',
          'filter' => NULL,
          'sortable' => false,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'sales_agent' => 
        array (
          'label' => 'Sales Agent',
          'filter' => NULL,
          'sortable' => true,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'workflow_status' => 
        array (
          'label' => 'SLA Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'completion_date' => 
        array (
          'label' => 'Completion Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'updated_at' => 
        array (
          'label' => 'Last Updated',
          'filter' => 'date',
          'sortable' => true,
        ),
        'created_at' => 
        array (
          'label' => 'Created',
          'filter' => 'date',
          'sortable' => true,
        ),
        'trouble_ticket' => 
        array (
          'label' => 'Trouble Ticket',
          'filter' => 'text',
          'sortable' => true,
        ),
        'activity' => 
        array (
          'label' => 'Activity',
          'filter' => NULL,
          'sortable' => false,
        ),
        'resolution_remarks' => 
        array (
          'label' => 'Resolution Remarks',
          'filter' => NULL,
          'sortable' => false,
        ),
        'internal_remarks' => 
        array (
          'label' => 'Internal Remarks',
          'filter' => NULL,
          'sortable' => false,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'submitted_at',
        2 => 'ticket_number',
        3 => 'account_number',
        4 => 'company_name',
        5 => 'issue_category',
        6 => 'contact_number',
        7 => 'alternate_contact_number',
        8 => 'issue_description',
        9 => 'attachments',
        10 => 'creator',
        11 => 'csr',
        12 => 'sla_timer',
        13 => 'status',
        14 => 'workflow_status',
        15 => 'completion_date',
        16 => 'updated_at',
        17 => 'trouble_ticket',
        18 => 'activity',
        19 => 'resolution_remarks',
        20 => 'internal_remarks',
        21 => 'manager',
        22 => 'team_leader',
        23 => 'sales_agent',
      ),
      'default_sort' => 
      array (
        0 => 'submitted_at',
        1 => 'desc',
      ),
    ),
    'vas_request_submissions' => 
    array (
      'model' => 'App\\Models\\VasRequestSubmission',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'created_at' => 
        array (
          'label' => 'Created',
          'filter' => 'date',
          'sortable' => true,
        ),
        'request_type' => 
        array (
          'label' => 'Request Type',
          'filter' => 'select',
          'sortable' => true,
        ),
        'account_number' => 
        array (
          'label' => 'Account Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'company_name' => 
        array (
          'label' => 'Company Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'contact_number' => 
        array (
          'label' => 'Contact Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'request_description' => 
        array (
          'label' => 'Request Description',
          'filter' => NULL,
          'sortable' => false,
        ),
        'description' => 
        array (
          'label' => 'Request Description (Legacy)',
          'filter' => NULL,
          'sortable' => false,
        ),
        'additional_notes' => 
        array (
          'label' => 'Additional Notes',
          'filter' => NULL,
          'sortable' => false,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'sales_agent' => 
        array (
          'label' => 'Sales Agent',
          'filter' => NULL,
          'sortable' => true,
        ),
        'executive' => 
        array (
          'label' => 'BO Executive',
          'filter' => NULL,
          'sortable' => true,
        ),
        'sla_timer' => 
        array (
          'label' => 'SLA Timer',
          'filter' => NULL,
          'sortable' => false,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'approved_at' => 
        array (
          'label' => 'Completion Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'creator' => 
        array (
          'label' => 'Created By',
          'filter' => NULL,
          'sortable' => false,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'created_at',
        2 => 'request_type',
        3 => 'account_number',
        4 => 'contact_number',
        5 => 'company_name',
        6 => 'request_description',
        7 => 'additional_notes',
        8 => 'manager',
        9 => 'team_leader',
        10 => 'sales_agent',
        11 => 'status',
        12 => 'executive',
        13 => 'sla_timer',
        14 => 'approved_at',
        15 => 'creator',
      ),
      'default_sort' => 
      array (
        0 => 'created_at',
        1 => 'desc',
      ),
      'sla_days' => 7,
    ),
    'special_requests' => 
    array (
      'model' => 'App\\Models\\SpecialRequest',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'created_at' => 
        array (
          'label' => 'Created',
          'filter' => 'date',
          'sortable' => true,
        ),
        'company_name' => 
        array (
          'label' => 'Company Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'account_number' => 
        array (
          'label' => 'Account Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'request_type' => 
        array (
          'label' => 'Request Type',
          'filter' => 'select',
          'sortable' => true,
        ),
        'complete_address' => 
        array (
          'label' => 'Address',
          'filter' => NULL,
          'sortable' => false,
        ),
        'special_instruction' => 
        array (
          'label' => 'Special Instruction',
          'filter' => NULL,
          'sortable' => false,
        ),
        'sales_agent' => 
        array (
          'label' => 'Sales Agent',
          'filter' => NULL,
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'creator' => 
        array (
          'label' => 'Created By',
          'filter' => NULL,
          'sortable' => false,
        ),
        'updated_at' => 
        array (
          'label' => 'Last Updated',
          'filter' => 'date',
          'sortable' => true,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'created_at',
        2 => 'company_name',
        3 => 'account_number',
        4 => 'request_type',
        5 => 'status',
        6 => 'manager',
        7 => 'team_leader',
        8 => 'sales_agent',
        9 => 'complete_address',
        10 => 'special_instruction',
        11 => 'creator',
        12 => 'updated_at',
      ),
      'default_sort' => 
      array (
        0 => 'created_at',
        1 => 'desc',
      ),
    ),
    'email_follow_ups' => 
    array (
      'model' => 'App\\Models\\EmailFollowUp',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'email_date' => 
        array (
          'label' => 'Email Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'subject' => 
        array (
          'label' => 'Subject',
          'filter' => 'text',
          'sortable' => true,
        ),
        'request_from' => 
        array (
          'label' => 'Request From',
          'filter' => 'text',
          'sortable' => true,
        ),
        'sent_to' => 
        array (
          'label' => 'Sent To',
          'filter' => 'text',
          'sortable' => true,
        ),
        'creator' => 
        array (
          'label' => 'Added By',
          'filter' => NULL,
          'sortable' => true,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'status_date' => 
        array (
          'label' => 'Status Date',
          'filter' => NULL,
          'sortable' => true,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'email_date',
        2 => 'subject',
        3 => 'request_from',
        4 => 'sent_to',
        5 => 'creator',
        6 => 'status',
        7 => 'status_date',
      ),
      'default_sort' => 
      array (
        0 => 'email_date',
        1 => 'desc',
      ),
    ),
    'employees' => 
    array (
      'model' => 'App\\Models\\User',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'employee_number' => 
        array (
          'label' => 'Employee ID',
          'filter' => 'text',
          'sortable' => true,
        ),
        'name' => 
        array (
          'label' => 'Employee Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'roles' => 
        array (
          'label' => 'Role(s)',
          'filter' => NULL,
          'sortable' => false,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'department' => 
        array (
          'label' => 'Department',
          'filter' => 'select',
          'sortable' => true,
        ),
        'email' => 
        array (
          'label' => 'Primary Email',
          'filter' => 'text',
          'sortable' => true,
        ),
        'phone' => 
        array (
          'label' => 'Contact No',
          'filter' => NULL,
          'sortable' => true,
        ),
        'cnic_number' => 
        array (
          'label' => 'GMIC No',
          'filter' => NULL,
          'sortable' => true,
        ),
        'extension' => 
        array (
          'label' => 'Extension',
          'filter' => NULL,
          'sortable' => true,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'joining_date' => 
        array (
          'label' => 'Joining Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'terminate_date' => 
        array (
          'label' => 'Terminate Date',
          'filter' => 'date',
          'sortable' => true,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'employee_number',
        2 => 'name',
        3 => 'roles',
        4 => 'team_leader',
        5 => 'manager',
        6 => 'department',
        7 => 'email',
        8 => 'phone',
        9 => 'cnic_number',
        10 => 'extension',
        11 => 'status',
        12 => 'joining_date',
        13 => 'terminate_date',
      ),
      'default_sort' => 
      array (
        0 => 'name',
        1 => 'asc',
      ),
    ),
    'cisco_extensions' => 
    array (
      'model' => 'App\\Models\\CiscoExtension',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'extension' => 
        array (
          'label' => 'Extension',
          'filter' => 'text',
          'sortable' => true,
        ),
        'landline_number' => 
        array (
          'label' => 'Landline Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'gateway' => 
        array (
          'label' => 'Gateway',
          'filter' => 'select',
          'sortable' => true,
        ),
        'username' => 
        array (
          'label' => 'User Name',
          'filter' => NULL,
          'sortable' => true,
        ),
        'password' => 
        array (
          'label' => 'Password',
          'filter' => NULL,
          'sortable' => false,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'manager' => 
        array (
          'label' => 'Manager',
          'filter' => NULL,
          'sortable' => true,
        ),
        'usage' => 
        array (
          'label' => 'Usage',
          'filter' => NULL,
          'sortable' => false,
        ),
        'assigned_to_name' => 
        array (
          'label' => 'Assigned To',
          'filter' => NULL,
          'sortable' => false,
        ),
        'comment' => 
        array (
          'label' => 'Comment',
          'filter' => NULL,
          'sortable' => false,
        ),
        'updated_at' => 
        array (
          'label' => 'Last Updated',
          'filter' => 'date',
          'sortable' => true,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'id',
        1 => 'extension',
        2 => 'landline_number',
        3 => 'gateway',
        4 => 'username',
        5 => 'password',
        6 => 'status',
        7 => 'manager',
        8 => 'team_leader',
        9 => 'assigned_to_name',
        10 => 'usage',
        11 => 'comment',
        12 => 'updated_at',
      ),
      'default_sort' => 
      array (
        0 => 'extension',
        1 => 'asc',
      ),
    ),
    'clients' => 
    array (
      'model' => 'App\\Models\\Client',
      'columns' => 
      array (
        'company_name' => 
        array (
          'label' => 'Company Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'account_number' => 
        array (
          'label' => 'Account Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'submitted_at' => 
        array (
          'label' => 'Submission Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'trade_license_issuing_authority' => 
        array (
          'label' => 'Trade License Issuing Authority',
          'filter' => NULL,
          'sortable' => false,
        ),
        'company_category' => 
        array (
          'label' => 'Company Category',
          'filter' => NULL,
          'sortable' => false,
        ),
        'trade_license_number' => 
        array (
          'label' => 'Trade License Number',
          'filter' => 'text',
          'sortable' => false,
        ),
        'trade_license_expiry_date' => 
        array (
          'label' => 'Trade License Expiry Date',
          'filter' => 'date',
          'sortable' => false,
        ),
        'establishment_card_number' => 
        array (
          'label' => 'Establishment Card Number',
          'filter' => 'text',
          'sortable' => false,
        ),
        'establishment_card_expiry_date' => 
        array (
          'label' => 'Establishment Card Expiry Date',
          'filter' => 'date',
          'sortable' => false,
        ),
        'account_taken_from' => 
        array (
          'label' => 'Account Taken From',
          'filter' => NULL,
          'sortable' => false,
        ),
        'account_mapping_date' => 
        array (
          'label' => 'Account Mapping Date',
          'filter' => 'date',
          'sortable' => false,
        ),
        'account_transfer_given_to' => 
        array (
          'label' => 'Account Transfer Given To',
          'filter' => NULL,
          'sortable' => false,
        ),
        'account_transfer_given_date' => 
        array (
          'label' => 'Account Transfer Given Date',
          'filter' => 'date',
          'sortable' => false,
        ),
        'full_address' => 
        array (
          'label' => 'Full Address',
          'filter' => NULL,
          'sortable' => false,
        ),
        'account_manager_name' => 
        array (
          'label' => 'Account Manager Name',
          'filter' => NULL,
          'sortable' => false,
        ),
        'csr_name_1' => 
        array (
          'label' => 'CSR Name 1',
          'filter' => NULL,
          'sortable' => false,
        ),
        'csr_name_2' => 
        array (
          'label' => 'CSR Name 2',
          'filter' => NULL,
          'sortable' => false,
        ),
        'csr_name_3' => 
        array (
          'label' => 'CSR Name 3',
          'filter' => NULL,
          'sortable' => false,
        ),
        'first_bill' => 
        array (
          'label' => 'First Bill',
          'filter' => NULL,
          'sortable' => false,
        ),
        'second_bill' => 
        array (
          'label' => 'Second Bill',
          'filter' => NULL,
          'sortable' => false,
        ),
        'third_bill' => 
        array (
          'label' => 'Third Bill',
          'filter' => NULL,
          'sortable' => false,
        ),
        'fourth_bill' => 
        array (
          'label' => 'Fourth Bill',
          'filter' => NULL,
          'sortable' => false,
        ),
        'additional_comment_1' => 
        array (
          'label' => 'Additional Note 1',
          'filter' => NULL,
          'sortable' => false,
        ),
        'additional_comment_2' => 
        array (
          'label' => 'Additional Note 2',
          'filter' => NULL,
          'sortable' => false,
        ),
        'submission_type' => 
        array (
          'label' => 'Submission Type',
          'filter' => 'select',
          'sortable' => true,
        ),
        'service_category' => 
        array (
          'label' => 'Service Category',
          'filter' => 'select',
          'sortable' => true,
        ),
        'manager' => 
        array (
          'label' => 'Manager Name',
          'filter' => NULL,
          'sortable' => true,
        ),
        'team_leader' => 
        array (
          'label' => 'Team Leader',
          'filter' => NULL,
          'sortable' => true,
        ),
        'sales_agent' => 
        array (
          'label' => 'Sales Agent Name',
          'filter' => NULL,
          'sortable' => true,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
        'service_type' => 
        array (
          'label' => 'Service Type',
          'filter' => 'text',
          'sortable' => true,
        ),
        'product_type' => 
        array (
          'label' => 'Product Type',
          'filter' => 'text',
          'sortable' => true,
        ),
        'address' => 
        array (
          'label' => 'Address',
          'filter' => NULL,
          'sortable' => false,
        ),
        'product_name' => 
        array (
          'label' => 'Product Name',
          'filter' => 'text',
          'sortable' => true,
        ),
        'mrc' => 
        array (
          'label' => 'MRC',
          'filter' => NULL,
          'sortable' => true,
        ),
        'quantity' => 
        array (
          'label' => 'Quantity',
          'filter' => NULL,
          'sortable' => true,
        ),
        'other' => 
        array (
          'label' => 'Other',
          'filter' => NULL,
          'sortable' => false,
        ),
        'migration_numbers' => 
        array (
          'label' => 'Migration Numbers',
          'filter' => NULL,
          'sortable' => false,
        ),
        'activity' => 
        array (
          'label' => 'Activity',
          'filter' => NULL,
          'sortable' => false,
        ),
        'wo_number' => 
        array (
          'label' => 'Work Order',
          'filter' => 'text',
          'sortable' => true,
        ),
        'work_order_status' => 
        array (
          'label' => 'Work Order Status',
          'filter' => NULL,
          'sortable' => false,
        ),
        'activation_date' => 
        array (
          'label' => 'Activation Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'contract_type' => 
        array (
          'label' => 'Contract Type',
          'filter' => 'text',
          'sortable' => true,
        ),
        'contract_end_date' => 
        array (
          'label' => 'Contract End Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'clawback_chum' => 
        array (
          'label' => 'Clawback / Chum',
          'filter' => 'select',
          'sortable' => true,
        ),
        'remarks' => 
        array (
          'label' => 'Remarks',
          'filter' => NULL,
          'sortable' => false,
        ),
        'renewal_alert' => 
        array (
          'label' => 'Renewal Alert',
          'filter' => NULL,
          'sortable' => true,
        ),
        'additional_notes' => 
        array (
          'label' => 'Additional Notes',
          'filter' => NULL,
          'sortable' => false,
        ),
        'creator' => 
        array (
          'label' => 'Created By',
          'filter' => NULL,
          'sortable' => false,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'company_name',
        1 => 'account_number',
        2 => 'submitted_at',
        3 => 'trade_license_issuing_authority',
        4 => 'company_category',
        5 => 'trade_license_number',
        6 => 'trade_license_expiry_date',
        7 => 'establishment_card_number',
        8 => 'establishment_card_expiry_date',
        9 => 'account_taken_from',
        10 => 'account_mapping_date',
        11 => 'account_transfer_given_to',
        12 => 'account_transfer_given_date',
        13 => 'full_address',
        14 => 'account_manager_name',
        15 => 'csr_name_1',
        16 => 'csr_name_2',
        17 => 'csr_name_3',
        18 => 'first_bill',
        19 => 'second_bill',
        20 => 'third_bill',
        21 => 'fourth_bill',
        22 => 'additional_comment_1',
        23 => 'additional_comment_2',
        24 => 'status',
        25 => 'creator',
        26 => 'submission_type',
        27 => 'service_category',
        28 => 'manager',
        29 => 'team_leader',
        30 => 'sales_agent',
        31 => 'service_type',
        32 => 'product_type',
        33 => 'address',
        34 => 'product_name',
        35 => 'mrc',
        36 => 'quantity',
        37 => 'other',
        38 => 'migration_numbers',
        39 => 'activity',
        40 => 'wo_number',
        41 => 'work_order_status',
        42 => 'activation_date',
        43 => 'completion_date',
        44 => 'payment_connection',
        45 => 'contract_type',
        46 => 'contract_end_date',
        47 => 'clawback_chum',
        48 => 'remarks',
        49 => 'renewal_alert',
        50 => 'additional_notes',
      ),
      'default_sort' => 
      array (
        0 => 'submitted_at',
        1 => 'desc',
      ),
    ),
    'expenses' => 
    array (
      'model' => 'App\\Models\\Expense',
      'columns' => 
      array (
        'id' => 
        array (
          'label' => 'ID',
          'filter' => NULL,
          'sortable' => true,
        ),
        'expense_date' => 
        array (
          'label' => 'Expense Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'product_category' => 
        array (
          'label' => 'Product Category',
          'filter' => 'select',
          'sortable' => true,
        ),
        'product_description' => 
        array (
          'label' => 'Product Description',
          'filter' => 'text',
          'sortable' => true,
        ),
        'invoice_number' => 
        array (
          'label' => 'Invoice Number',
          'filter' => 'text',
          'sortable' => true,
        ),
        'vat_amount' => 
        array (
          'label' => 'VAT %',
          'filter' => NULL,
          'sortable' => true,
        ),
        'amount_without_vat' => 
        array (
          'label' => 'Amount (Without VAT)',
          'filter' => NULL,
          'sortable' => true,
        ),
        'vat_amount_currency' => 
        array (
          'label' => 'VAT Amount',
          'filter' => NULL,
          'sortable' => false,
        ),
        'full_amount' => 
        array (
          'label' => 'Total Amount',
          'filter' => NULL,
          'sortable' => true,
        ),
        'added_by' => 
        array (
          'label' => 'Added By',
          'filter' => NULL,
          'sortable' => true,
        ),
        'created_at' => 
        array (
          'label' => 'Created Date',
          'filter' => 'date',
          'sortable' => true,
        ),
        'status' => 
        array (
          'label' => 'Status',
          'filter' => 'select',
          'sortable' => true,
        ),
      ),
      'default_columns' => 
      array (
        0 => 'status',
        1 => 'expense_date',
        2 => 'product_category',
        3 => 'product_description',
        4 => 'invoice_number',
        5 => 'vat_amount',
        6 => 'amount_without_vat',
        7 => 'vat_amount_currency',
        8 => 'full_amount',
        9 => 'added_by',
        10 => 'created_at',
      ),
      'default_sort' => 
      array (
        0 => 'expense_date',
        1 => 'desc',
      ),
    ),
  ),
  'permission' => 
  array (
    'models' => 
    array (
      'permission' => 'Spatie\\Permission\\Models\\Permission',
      'role' => 'App\\Models\\Role',
    ),
    'table_names' => 
    array (
      'roles' => 'roles',
      'permissions' => 'permissions',
      'model_has_permissions' => 'model_has_permissions',
      'model_has_roles' => 'model_has_roles',
      'role_has_permissions' => 'role_has_permissions',
    ),
    'column_names' => 
    array (
      'role_pivot_key' => NULL,
      'permission_pivot_key' => NULL,
      'model_morph_key' => 'model_id',
      'team_foreign_key' => 'team_id',
    ),
    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,
    'events_enabled' => false,
    'teams' => false,
    'team_resolver' => 'Spatie\\Permission\\DefaultTeamResolver',
    'use_passport_client_credentials' => false,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,
    'cache' => 
    array (
      'expiration_time' => 
      \DateInterval::__set_state(array(
         'from_string' => true,
         'date_string' => '24 hours',
      )),
      'key' => 'spatie.permission.cache',
      'store' => 'default',
    ),
  ),
  'permissions' => 
  array (
    'modules' => 
    array (
      'lead-submissions' => 'Lead Submissions',
      'field-submissions' => 'Field Submissions',
      'vas_requests' => 'VAS Requests',
      'customer_support_requests' => 'Customer Support Requests',
      'special_requests' => 'Special Requests',
      'accounts' => 'All Clients',
      'clients' => 'Clients',
      'order_status' => 'Order Status',
      'dsp_tracker' => 'DSP Tracker',
      'gsm_verifiers' => 'Verifiers Detail',
      'extensions' => 'Cisco Extensions',
      'expense_tracker' => 'Expense Tracker',
      'personal_notes' => 'Personal Notes',
      'emails_followup' => 'Email Follow-Up',
      'reports' => 'Reports',
      'users' => 'Users',
      'teams' => 'Teams',
    ),
    'actions' => 
    array (
      'list' => 'Listing',
      'view' => 'Show',
      'create' => 'Add',
      'edit' => 'Edit',
      'delete' => 'Delete',
    ),
    'canonical_actions' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
      4 => 'assign_permissions',
    ),
    'action_aliases' => 
    array (
      'create' => 
      array (
        0 => 'create',
        1 => 'add',
      ),
      'read' => 
      array (
        0 => 'read',
        1 => 'list',
        2 => 'view',
      ),
      'view' => 
      array (
        0 => 'view',
        1 => 'list',
        2 => 'read',
      ),
      'update' => 
      array (
        0 => 'update',
        1 => 'edit',
      ),
      'edit' => 
      array (
        0 => 'edit',
        1 => 'update',
      ),
      'delete' => 
      array (
        0 => 'delete',
      ),
      'assign_permissions' => 
      array (
        0 => 'assign_permissions',
        1 => 'manage_permissions',
      ),
    ),
    'format' => '{module}.{action}',
    'structure' => 
    array (
      'lead-submissions' => 
      array (
        'label' => 'Lead Submissions',
        'icon' => 'submissions',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Lead Submissions Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Lead Submissions',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Lead Submission',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Lead Submission',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Lead Submission',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'assign_bo_executive',
            'label' => 'Assign Back Office Executive',
            'priority' => 'low',
          ),
          6 => 
          array (
            'key' => 'resubmit_lead',
            'label' => 'Resubmit Lead',
            'priority' => 'low',
          ),
        ),
      ),
      'field-submissions' => 
      array (
        'label' => 'Field Submissions',
        'icon' => 'field_head',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Field Submissions Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Field Submissions',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Field Submission',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Field Submission',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Field Submission',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'assign_field_agent',
            'label' => 'Assign Field Agent',
            'priority' => 'low',
          ),
          6 => 
          array (
            'key' => 'change_meeting_status',
            'label' => 'Change Meeting Status',
            'priority' => 'low',
          ),
          7 => 
          array (
            'key' => 'upload_field_proof',
            'label' => 'Upload Field Proof',
            'priority' => 'low',
          ),
        ),
      ),
      'vas_requests' => 
      array (
        'label' => 'VAS Requests',
        'icon' => 'vas_requests',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'VAS Requests Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View VAS Requests',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create VAS Request',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit VAS Request',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete VAS Request',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'process_vas_requests',
            'label' => 'Process VAS Requests',
            'priority' => 'low',
          ),
          6 => 
          array (
            'key' => 'change_du_status',
            'label' => 'Change DU Status',
            'priority' => 'low',
          ),
          7 => 
          array (
            'key' => 'add_remarks',
            'label' => 'Add Remarks',
            'priority' => 'medium',
          ),
          8 => 
          array (
            'key' => 'assign_bo_executive',
            'label' => 'Assign Back Office Executive',
            'priority' => 'low',
          ),
        ),
      ),
      'customer_support_requests' => 
      array (
        'label' => 'Customer Support Requests',
        'icon' => 'customer_support',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Customer Support Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Customer Support Requests',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Customer Support Request',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Customer Support Request',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Customer Support Request',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'assign_csr',
            'label' => 'Assign CSR',
            'priority' => 'low',
          ),
          6 => 
          array (
            'key' => 'change_ticket_status',
            'label' => 'Change Ticket Status',
            'priority' => 'low',
          ),
          7 => 
          array (
            'key' => 'add_resolution_remarks',
            'label' => 'Add Resolution Remarks',
            'priority' => 'medium',
          ),
          8 => 
          array (
            'key' => 'export_tickets',
            'label' => 'Export Tickets',
            'priority' => 'low',
          ),
        ),
      ),
      'special_requests' => 
      array (
        'label' => 'Special Requests',
        'icon' => 'submissions',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Special Requests Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Special Requests',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Special Request',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Special Request',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Special Request',
            'priority' => 'high',
          ),
        ),
      ),
      'accounts' => 
      array (
        'label' => 'All Clients',
        'icon' => 'clients',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'All Clients Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View All Clients',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Client',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Client',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Client',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'search_clients',
            'label' => 'Search Clients',
            'priority' => 'low',
          ),
          6 => 
          array (
            'key' => 'add_edit_products',
            'label' => 'Add/Edit Products & Services',
            'priority' => 'medium',
          ),
          7 => 
          array (
            'key' => 'export_client_data',
            'label' => 'Export Client Data',
            'priority' => 'low',
          ),
        ),
      ),
      'clients' => 
      array (
        'label' => 'Clients',
        'icon' => 'clients',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Clients Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Client',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Client',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Client',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Client',
            'priority' => 'high',
          ),
        ),
      ),
      'order_status' => 
      array (
        'label' => 'Order Status',
        'icon' => 'order_status',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Order Status Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Order Status',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'search_by_activity',
            'label' => 'Search by Activity',
            'priority' => 'low',
          ),
          3 => 
          array (
            'key' => 'search_by_account_number',
            'label' => 'Search by Account Number',
            'priority' => 'low',
          ),
          4 => 
          array (
            'key' => 'search_by_work_order',
            'label' => 'Search by Work Order',
            'priority' => 'low',
          ),
        ),
      ),
      'dsp_tracker' => 
      array (
        'label' => 'DSP Tracker',
        'icon' => 'dsp_tracker',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'DSP Tracker Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View DSP Tracker',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'upload_csv',
            'label' => 'Upload CSV',
            'priority' => 'low',
          ),
          3 => 
          array (
            'key' => 'delete_existing_csv',
            'label' => 'Delete Existing CSV',
            'priority' => 'high',
          ),
          4 => 
          array (
            'key' => 'search_dsp_status',
            'label' => 'Search DSP Status',
            'priority' => 'low',
          ),
          5 => 
          array (
            'key' => 'export_dsp_data',
            'label' => 'Export DSP Data',
            'priority' => 'low',
          ),
        ),
      ),
      'gsm_verifiers' => 
      array (
        'label' => 'Verifiers Detail',
        'icon' => 'verifiers',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Verifiers Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Verifier',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Verifier',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'add_verifier',
            'label' => 'Add Verifier',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Verifier',
            'priority' => 'medium',
          ),
          5 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Verifier',
            'priority' => 'high',
          ),
        ),
      ),
      'extensions' => 
      array (
        'label' => 'Cisco Extensions',
        'icon' => 'extensions',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Extensions Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Extension',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Extension',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Extension',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Extension',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'assign_extension',
            'label' => 'Assign Extension',
            'priority' => 'low',
          ),
          6 => 
          array (
            'key' => 'bulk_upload_extensions',
            'label' => 'Bulk Upload Extensions',
            'priority' => 'low',
          ),
          7 => 
          array (
            'key' => 'bulk_download_extensions',
            'label' => 'Bulk Download Extensions',
            'priority' => 'low',
          ),
        ),
      ),
      'expense_tracker' => 
      array (
        'label' => 'Expense Tracker',
        'icon' => 'expense_tracker',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Expense Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Expense',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Expense',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Expense',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Expense',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'export_expenses',
            'label' => 'Export Expenses',
            'priority' => 'low',
          ),
        ),
      ),
      'personal_notes' => 
      array (
        'label' => 'Personal Notes',
        'icon' => 'personal_notes',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Notes Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Note',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Note',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Note',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Note',
            'priority' => 'high',
          ),
        ),
      ),
      'emails_followup' => 
      array (
        'label' => 'Email Follow-Up',
        'icon' => 'email_followup',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Email Follow-Up Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Email Follow-Up',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Email Follow-Up',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Email Follow-Up',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Email Follow-Up',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'export_email_data',
            'label' => 'Export Email Follow-Up Data',
            'priority' => 'low',
          ),
        ),
      ),
      'reports' => 
      array (
        'label' => 'Reports',
        'icon' => 'reports',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Reports Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Reports',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'view_sla_reports',
            'label' => 'View SLA Reports',
            'priority' => 'low',
          ),
          3 => 
          array (
            'key' => 'view_vas_reports',
            'label' => 'View VAS Reports',
            'priority' => 'low',
          ),
          4 => 
          array (
            'key' => 'export_reports',
            'label' => 'Export Reports',
            'priority' => 'low',
          ),
        ),
      ),
      'users' => 
      array (
        'label' => 'Users',
        'icon' => 'employees',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Users Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View User',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create User',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit User',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete User',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'assign_extensions',
            'label' => 'Assign Extensions',
            'priority' => 'low',
          ),
          6 => 
          array (
            'key' => 'bulk_upload_employees',
            'label' => 'Bulk Upload Users',
            'priority' => 'low',
          ),
        ),
      ),
      'teams' => 
      array (
        'label' => 'Teams',
        'icon' => 'team',
        'permissions' => 
        array (
          0 => 
          array (
            'key' => 'list',
            'label' => 'Teams Listing',
            'priority' => 'low',
          ),
          1 => 
          array (
            'key' => 'view',
            'label' => 'View Team',
            'priority' => 'low',
          ),
          2 => 
          array (
            'key' => 'create',
            'label' => 'Create Team',
            'priority' => 'medium',
          ),
          3 => 
          array (
            'key' => 'edit',
            'label' => 'Edit Team',
            'priority' => 'medium',
          ),
          4 => 
          array (
            'key' => 'delete',
            'label' => 'Delete Team',
            'priority' => 'high',
          ),
          5 => 
          array (
            'key' => 'manage_members',
            'label' => 'Manage Team Members',
            'priority' => 'low',
          ),
        ),
      ),
    ),
  ),
  'queue' => 
  array (
    'default' => 'database',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => NULL,
        'after_commit' => false,
      ),
      'deferred' => 
      array (
        'driver' => 'deferred',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'connections' => 
        array (
          0 => 'database',
          1 => 'deferred',
        ),
      ),
      'background' => 
      array (
        'driver' => 'background',
      ),
    ),
    'batching' => 
    array (
      'database' => 'sqlite',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'sqlite',
      'table' => 'failed_jobs',
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost:5173',
      1 => '127.0.0.1:5173',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'authenticate_session' => 'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
      'encrypt_cookies' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
      'validate_csrf_token' => 'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'key' => NULL,
    ),
    'resend' => 
    array (
      'key' => NULL,
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => NULL,
        'channel' => NULL,
      ),
    ),
  ),
  'session' => 
  array (
    'driver' => 'file',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => 'C:\\Users\\yousa\\aston-hill-crm\\storage\\framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'laravel-session',
    'path' => '/',
    'domain' => NULL,
    'secure' => false,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'datatables' => 
  array (
    'search' => 
    array (
      'smart' => true,
      'multi_term' => true,
      'case_insensitive' => true,
      'use_wildcards' => false,
      'starts_with' => false,
    ),
    'index_column' => 'DT_RowIndex',
    'engines' => 
    array (
      'eloquent' => 'Yajra\\DataTables\\EloquentDataTable',
      'query' => 'Yajra\\DataTables\\QueryDataTable',
      'collection' => 'Yajra\\DataTables\\CollectionDataTable',
      'resource' => 'Yajra\\DataTables\\ApiResourceDataTable',
    ),
    'builders' => 
    array (
    ),
    'nulls_last_sql' => ':column :direction NULLS LAST',
    'error' => NULL,
    'columns' => 
    array (
      'excess' => 
      array (
        0 => 'rn',
        1 => 'row_num',
      ),
      'escape' => '*',
      'raw' => 
      array (
        0 => 'action',
      ),
      'blacklist' => 
      array (
        0 => 'password',
        1 => 'remember_token',
      ),
      'whitelist' => '*',
    ),
    'json' => 
    array (
      'header' => 
      array (
      ),
      'options' => 0,
    ),
    'callback' => 
    array (
      0 => '$',
      1 => '$.',
      2 => 'function',
    ),
  ),
  'google2fa' => 
  array (
    'enabled' => true,
    'lifetime' => 0,
    'keep_alive' => true,
    'auth' => 'auth',
    'guard' => '',
    'session_var' => 'google2fa',
    'otp_input' => 'one_time_password',
    'window' => 1,
    'forbid_old_passwords' => false,
    'otp_secret_column' => 'google2fa_secret',
    'view' => 'google2fa.index',
    'error_messages' => 
    array (
      'wrong_otp' => 'The \'One Time Password\' typed was wrong.',
      'cannot_be_empty' => 'One Time Password cannot be empty.',
      'unknown' => 'An unknown error has occurred. Please try again.',
    ),
    'throw_exceptions' => true,
    'qrcode_image_backend' => 'svg',
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
  ),
);
