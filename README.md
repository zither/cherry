# Cherry

An ActivityPub-based microblog.

**WIP**

## Requirements

- PHP 7.4+
    - ext-curl
    - ext-json
    - ext-pdo
    - ext-pdo_mysql
    - ext-mbstring
- MySQL 5.6+

## Installation

Clone the latest version from master branch:

    $ git clone --depth 1 https://github.com/zither/cherry.git 
    
Change to cherry directory:

    $ cd cherry
    
Use composer to install dependencies:

    $ composer install --no-dev
    
Set directory permission:

    $ sudo chmod -R 755 storage    
    
Create configuration file:

    $ cp configs/configs_example.php configs/configs.php
    
Change configs in `configs.php` and set the root path of web server to `cherry/public`. Then create the database and import tables from `data/cherry.sql`.

Set Nginx rewrite rules:

    Server {
    
        # ......
        # ......
        
        location / {
            try_files $uri $uri/ /index.php/$uri$is_args$query_string;
        }

        location ~ \.php {
            fastcgi_pass  127.0.01:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_split_path_info ^(.+?\.php)(/.*)$;
            set $path_info $fastcgi_path_info;
            fastcgi_param PATH_INFO $path_info;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }

Visit setup URL in web browser:

    https://your-domain/init

Start task runner:

    $ bin/run_tasks.sh
