# Domain specific Sprinkles loader for [UserFrosting 4](https://www.userfrosting.com)

This Sprinkle provides some "runtime" overloading for other Sprinkles. 
> This version requires UserFrosting 4.3 and up. 

# Why would you use such a thing?
- You have a personal "core" Sprinkle ("portal") for your base logic
- You have multiple Domains/URLs (portal.customer1.com, portal.customer2.net, portal.example.org, ...)
- All Domains should use the same base logic but with different configurations  
(other Layouts, other page titles, different databases, ...)

So you have two options:  
- Use a separate UserFrosting installation for each domain
- Use this sprinkle and "share" one UserFrosting installation 

# Contributing
Feel free to open an issue or submit a pull request.

# Versions and UserFrosting support
| UserFrosting Version | DomainSpecificSprinklesLoader Version |
|----------------------|-----------------------|
|       < 4.3.x        |       No Support      |
|         4.3.x        |         1.0.0         |

# Installation
Edit UserFrosting `app/sprinkles.json` file and add the following to the `require` list : `"ahaenggli/UF_DomainSpecificSprinklesLoader": "^1.0.0"`. 
Also add `DomainSpecificSprinklesLoader` to the `base` list. For example:

```
{
    "require": {
        "ahaenggli/UF_DomainSpecificSprinklesLoader": "^1.0.0"
    },
    "base": [
        "core",
        "account",
        "admin",
        "...", --> your base sprinkle
        "DomainSpecificSprinklesLoader"
    ]
}
```

Run `composer update` then `php bakery bake` to install the sprinkle.  
Now you can edit the necessary configurations in your base Sprinkle and add your additional Sprinkles to load on "runtime".  
Important: Each additional Sprinkle needs a `sprinkles.json` within.  
Optional: You can add a `/config/.env` in each additional Sprinkle if you wanna change some environment variables.

# How to use?
Edit your base sprinkle `config/default.php` and add the following variables: 
```php
'customUfInit' => ['whitelist'=>['portal.customer2.net', 'portal.example.org', ...] /* use your own domains here */,
                   'prefix_sprinkle_dir' => '' /* optional */],                           
```

`'whitelist'` is an array of your domains. Just these are checked whether there exists a Sprinkle or not.  
Tip: you can use 'whitelist'=>['*'] to allow every domain. 
`'prefix_sprinkle_dir'` is optional. You can add a prefix for your Sprinkles.  

Now you can add additional sprinkles like: `app/sprinkles/portal_customer2_net` and `app/sprinkles/portal_example_org`

## Usage example
- base logic is in sprinkle `awesome`
- you have 3 domains: portal.customer1.com, portal.customer2.net, portal.example.org
- you wanna prefix `something_` (because of alpha sort in your folder...)  

Your `app/sprinkles.json` looks like:
```
{
    "require": {
        "ahaenggli/UF_DomainSpecificSprinklesLoader": "^1.0.0"
    },
    "base": [
        "core",
        "account",
        "admin",
        "awesome", 
        "DomainSpecificSprinklesLoader"
    ]
}
```
(Run `composer update` and `php bakery bake`)  
  
Your `awesome` Sprinkle `config/default.php`:  
```php
'customUfInit' => ['whitelist'=>['portal.customer1.net', 'portal.customer2.net', 'portal.example.org'],
                   'prefix_sprinkle_dir' => 'something_'],                           
```

Your `app/sprinkles/` folder contains:
- account
- admin
- awesome
- core
- DomainSpecificSprinklesLoader
- something_portal_customer1_net
- something_portal_customer2_net
- something_portal_example_org

Your `app/sprinkles/something_portal_customer1_net/sprinkles.json' looks like:
```
{
    "base": [
        "awesome",
        "something_portal_customer1_net"
    ]
}
```

Your `app/sprinkles/something_portal_customer2_net/sprinkles.json' looks like:
```
{
    "base": [
        "awesome",
        "something_portal_customer2_net"
    ]
}
```

Your `app/sprinkles/something_portal_example_org/sprinkles.json' looks like:
```
{
    "base": [
        "awesome",
        "something_portal_example_org"
    ]
}
```
## How does it work?
If the page is 'something_portal_example_org':  
- `app/sprinkles.json` is loaded
- Sprinkle `awesome` (ServiceProvider) is loaded
- Sprinkle `DomainSpecificSprinklesLoader` (ServiceProvider) is loaded  
  - `app/sprinkles/something_portal_example_org/sprinkles.json` is loaded  
    (now .env for each entry is loaded, then each Sprinkle)  
    - `app/sprinkles/awesome/config/.env` is loaded  
    - `app/sprinkles/something_portal_example_org/config/.env` is loaded  
    - `$container['config']` is reinitialized
    - Sprinkle `awesome` (ServiceProvider) is loaded again (so first ServiceProvider-init of it is "overridden") 
    - Sprinkle `something_portal_example_org` (ServiceProvider) is loaded 
 