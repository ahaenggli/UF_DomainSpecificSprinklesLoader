<?php

namespace UserFrosting\Sprinkle\DomainSpecificSprinklesLoader\ServicesProvider;

use Dotenv\Dotenv;
use UserFrosting\Config\ConfigPathBuilder;
use UserFrosting\Support\Repository\Loader\ArrayFileLoader;
use UserFrosting\Support\Repository\Repository;

/**
 * Registers services for DomainSpecificSprinklesLoader
 */
class ServicesProvider
{
    /**
     * Register DomainSpecificSprinklesLoader services.
     *
     * @param Container $container A DI container implementing ArrayAccess and container-interop.
     */
    public function register($container)
    {
        /* customUfInit */ 
        $customUfInit = $container->config['customUfInit'];

        if($customUfInit !== null && is_array($customUfInit))
        {
            // load from existing config
            $whitelist = $customUfInit['whitelist'];
            $sprinkle_dir_prefix = $customUfInit['prefix_sprinkle_dir'];

            // optional additional json file for additional sprinkles
            $__extraSprinkleJsonFile =  null;

            // get host
            $host = $_SERVER['HTTP_HOST'];

            // allow only letters, numbers and dots
            $host = preg_replace("/[^A-Za-z0-9\.]/", '', $host);
            
            // is host in our whitelist?
            if(in_array($host,$whitelist) || $whitelist === ['*'])
            {   
                $host = preg_replace("/[^A-Za-z0-9\.]/", '', $host);
                $host = str_replace('.', '_', $host);

                $possiblyExistingSprinkleFile = \UserFrosting\SPRINKLES_DIR.\UserFrosting\DS.$sprinkle_dir_prefix.$host.\UserFrosting\DS.'sprinkles.json';
                
                // there is an additional sprinkle.json 
                if(file_exists($possiblyExistingSprinkleFile)) $__extraSprinkleJsonFile = $possiblyExistingSprinkleFile;        
            } 

            // load data from additional sprinkle file
            if($__extraSprinkleJsonFile !== null){
                $additionalSprinkles = json_decode(file_get_contents($__extraSprinkleJsonFile))->base;

                /* custom .env */
                foreach($additionalSprinkles as $addSprinkle){
                    $possiblyExistingEnvFile = \UserFrosting\SPRINKLES_DIR.\UserFrosting\DS.$sprinkle_dir_prefix.$host.\UserFrosting\DS.'config'.\UserFrosting\DS;
                    if(is_dir($possiblyExistingEnvFile) && file_exists($possiblyExistingEnvFile.'.env')) 
                    {                            
                        $dotenv = Dotenv::create($possiblyExistingEnvFile, '.env');
                        $dotenv->overload();
                    }
                }

                foreach($additionalSprinkles as $addSprinkle){
                    // existing instance is overridden
                    // if(!$container->sprinkleManager->isAvailable($addSprinkle)){
                        $container->sprinkleManager->init([$addSprinkle]);
                        $container->sprinkleManager->addSprinkleResources($addSprinkle);
                        $container->sprinkleManager->registerServices($addSprinkle);                    
                    // }
                }
            }
        }

            /* override existing config */
            unset($container['config']);        
            $container['config'] = function ($c) {

                /* clear locator-cache */
                $uri = 'config://';
                $key = $uri.'@'.(int) true.(int) true;
                $class = new \ReflectionClass("UserFrosting\UniformResourceLocator\ResourceLocator");
                $property = $class->getProperty("cache");
                $property->setAccessible(true);    
                $property->setValue($c->locator, null);
                
                // Get configuration mode from environment
                $mode = getenv('UF_MODE') ?: '';

                // Construct and load config repository
                $builder = new ConfigPathBuilder($c->locator, 'config://');
                $loader = new ArrayFileLoader($builder->buildPaths($mode));
                $config = new Repository($loader->load());

                // Construct base url from components, if not explicitly specified
                if (!isset($config['site.uri.public'])) {
                    $uri = $c->request->getUri();

                    // Slim\Http\Uri likes to add trailing slashes when the path is empty, so this fixes that.
                    $config['site.uri.public'] = trim($uri->getBaseUrl(), '/');
                }

                // Hacky fix to prevent sessions from being hit too much: ignore CSRF middleware for requests for raw assets ;-)
                // See https://github.com/laravel/framework/issues/8172#issuecomment-99112012 for more information on why it's bad to hit Laravel sessions multiple times in rapid succession.
                $csrfBlacklist = $config['csrf.blacklist'];
                $csrfBlacklist['^/' . $config['assets.raw.path']] = [
                    'GET',
                ];

                $config->set('csrf.blacklist', $csrfBlacklist);

                return $config;
            };
    }
}