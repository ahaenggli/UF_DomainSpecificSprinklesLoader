<?php

namespace UserFrosting\Sprinkle\DomainSpecificSprinklesLoader\ServicesProvider;

use Dotenv\Dotenv;
use UserFrosting\Config\ConfigPathBuilder;
use UserFrosting\Support\Repository\Loader\ArrayFileLoader;
use UserFrosting\Support\Repository\Repository;

/**
 * DomainSpecificSprinklesLoader services provider.
 *
 * Registers services for DomainSpecificSprinklesLoader
 *
 * @author ahaenggli (https://github.com/ahaenggli)
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
            // init some vars...             
            $whitelist = $customUfInit['whitelist']; 
            $sprinkle_dir_prefix = $customUfInit['prefix_sprinkle_dir'];
            $__extraSprinkleJsonFile =  null; // optional additional json file for additional sprinkles                                    
            $reInitAllSprinkles = false;      // flag if all Sprinkles should be reinitialized         
            $host = $_SERVER['HTTP_HOST'];    // get host

            // allow only letters, numbers and dots
            $host = preg_replace("/[^A-Za-z0-9\.]/", '', $host);
            
            // is host in our whitelist?
            if(in_array($host,$whitelist) || $whitelist === ['*'])
            {   
                // replace dots with _
                $host = str_replace('.', '_', $host);

                // build potential path to additional sprinkles.json
                $possiblyExistingSprinkleFile = \UserFrosting\SPRINKLES_DIR.\UserFrosting\DS.$sprinkle_dir_prefix.$host.\UserFrosting\DS.'sprinkles.json';
                
                // there is an additional sprinkle.json 
                if(file_exists($possiblyExistingSprinkleFile)) $__extraSprinkleJsonFile = json_decode(file_get_contents($possiblyExistingSprinkleFile))->base; 
            } 

            // get all Sprinkle names
            $sprinkles = $container->sprinkleManager->getSprinkleNames();           

            // merge extra Sprinkles 
            if($__extraSprinkleJsonFile !== NULL){
                $sprinkles = array_unique(array_merge($sprinkles, $__extraSprinkleJsonFile));
                $reInitAllSprinkles = true; //new Sprinkles could have additionals /config/*.php 
            } 
                   
            /* overload custom .env */
            foreach($sprinkles as $nr => $k) {
                $possiblyExistingEnvFile = \UserFrosting\SPRINKLES_DIR.\UserFrosting\DS.$k.\UserFrosting\DS.'config'.\UserFrosting\DS;
                if(is_dir($possiblyExistingEnvFile) && file_exists($possiblyExistingEnvFile.'.env')) 
                {                            
                    $dotenv = Dotenv::create($possiblyExistingEnvFile, '.env');
                    $dotenv->overload();
                    $reInitAllSprinkles = true; // maybe other DB_NAME set, different email credentials, and so on
                }
                // delete this Sprinkle from the array - (or else ->infinite loop)
                if($k == 'DomainSpecificSprinklesLoader') unset($sprinkles[$nr]);
            }
            
            // something changed -> reinitialize all sprinkles
            if($reInitAllSprinkles){
               
                // unset, so it can be created again
                unset($container['config']);   
                
                 /* clear locator-cache: else resources such as templates would not be located */
                 $uri = 'config://';
                 $key = $uri.'@'.(int) true.(int) true;
                 $class = new \ReflectionClass("UserFrosting\UniformResourceLocator\ResourceLocator");
                 $property = $class->getProperty("cache");
                 $property->setAccessible(true);    
                 $property->setValue($container->locator, null);

                 // reload all Sprinkles
                $container->sprinkleManager = new \UserFrosting\System\Sprinkle\SprinkleManager($container);
                $container->sprinkleManager->init($sprinkles);
                $container->sprinkleManager->addResources();
                $container->sprinkleManager->registerAllServices();                
            }
        }            
    }
}
