<?php
    require_once('runnable.interface.php');

    class RocketSled
    {
        private static $scan             = NULL;
        private static $instance         = NULL;
        private static $runnable         = NULL;
        private static $autoload         = NULL;
        private static $packages         = NULL;

        private function __construct()
        {
            self::$runnable  = self::defaultRunnable();
            self::$autoload  = self::defaultAutoload();
            self::$scan      = array('.');
            spl_autoload_register(self::$autoload);
        }
        
        public static function run()
        {
            self::instance();
            $runnable = self::$runnable;
            $runnable_object = $runnable();
            $runnable_object->run();
        }
        
        public static function defaultRunnable()
        {
            return function()
            {
                global $argv;
                /**
                * Get the class to run whether we're on the command line or in
                * the browser
                */
                if(isset($argv))
                    $runnable_class = isset($argv[1]) ? $argv[1]:require_once('runnable.default.php');
                else
                    $runnable_class = isset($_GET['r']) ? $_GET['r']:require_once('runnable.default.php');
            
                //Make sure no-one's trying to haxor us by running a class that's not runnable
                $refl = new ReflectionClass($runnable_class);
                
                if(!$refl->implementsInterface('RocketSled\\Runnable'))
                    die('Running a class that does not implement interface Runnable is not allowed');
            
                $runnable = new $runnable_class();
                return $runnable;
            };
        }

        public static function defaultAutoload()
        {
            return function($class)
            {
                $namespaced = explode('\\',$class);
                
                if(count($namespaced) > 1)
                {
                    $class_part = strtolower(preg_replace('/^_/','',preg_replace('/([A-Z])/','_\1',array_pop($namespaced)))).'.class.php';
                    //this is a bit of an edge case, but quite often directories will have version information appended to them
                    //so we match according to a pattern. Because we might get a false positive, we just err on the side of
                    //caution and require all the classes. The overhead of including one or two classes we didn't intend to
                    //is pretty minimal and it shouldn't happen too often (unless of course you have some bizarre directory naming
                    //convention, in which case you should probably just install your code in RocketSled::lib_dir() and create a 
                    //completely separate autoload method for it.
                    
                    foreach(RocketSled::scan() as $dir)
                    {
                        $fnames = glob($dir.'/'.implode('/',$namespaced).'*/'.$class_part);
                        
                        foreach($fnames as $fname)
                            if(file_exists($fname))
                                require_once($fname);
                    }
                }
                
                else
                {
                    $classes = RocketSled::filteredPackages(function($fname) use ($class)
                    {
                        $ending = '.class.php';
            
                        if(RocketSled::endsWith($fname,$ending))
                        {
                            if(str_replace(' ','',ucwords(str_replace('_',' ',str_replace($ending,'',basename($fname))))) === $class)
                                require_once($fname);
                        }
                    });
                }
            };
        }

        public static function instance()
        {
            if(self::$instance === NULL)
                self::$instance = new RocketSled();
            
            return self::$instance;
        }

        /**
        * Pass in an array of directories to scan
        */
        public static function scan($dirs = NULL)
        {
            self::instance();
            if($dirs !== NULL)
            {
                self::$scan = array_filter(array_unique($dirs),function($path)
                {
                    return realpath($path);
                });
            }

            else
                return self::$scan;
        }

        public static function autoload(Closure $autoload = NULL)
        {
            self::instance();
            
            if(is_object($autoload))
            {
                if(self::$autoload !== NULL)
                    spl_autoload_unregister(self::$autoload);

                self::$autoload = $autoload;
                spl_autoload_register(self::$autoload);
            }

            else
                return self::$autoload;
        }

        public static function runnable(Closure $runnable = NULL)
        {
            self::instance();
            
            if(is_object($runnable))
                self::$runnable = $runnable;
            else
                return self::$runnable;
        }
            
        public static function filteredPackages($callback)
        {
            return array_filter(self::packages(),$callback);
        }
    
        private static function packages()
        {
            if(self::$packages === NULL)
            {
                self::$packages = array();

                foreach(self::$scan as $dir)
                    self::$packages = array_merge(self::$packages,self::directoryList($dir));
            }

            return self::$packages;
        }
    
        /**
        * Courtesy of donovan dot pp at gmail dot com on http://au2.php.net/scandir
        */
        private static function directoryList($dir)
        {
           $path = '';
           $stack[] = $dir;
           
           while ($stack)
           {
               $thisdir = array_pop($stack);
               
               if($dircont = scandir($thisdir))
               {
                   $i=0;
                   
                   while(isset($dircont[$i]))
                   {
                       if($dircont[$i] !== '.' && $dircont[$i] !== '..')
                       {
                           $current_file = "{$thisdir}/{$dircont[$i]}";
                           
                           if (is_file($current_file))
                               $path[] = "{$thisdir}/{$dircont[$i]}";
                           else if(is_dir($current_file))
                           {
                               $path[] = "{$thisdir}/{$dircont[$i]}";
                               $stack[] = $current_file;
                           }
                       }
                       
                       $i++;
                   }
               }
           }
           
           return $path;
        }
        
        public static function endsWith($str,$test)
        {
            return (substr($str, -strlen($test)) == $test);
        }
    }
