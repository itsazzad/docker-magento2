<?php

/**
 * Class Builder2
 *
 * Builds files from given configuration and source templates.
 *
 * This extends from the original Builder in the `docker-magento` repository.
 */
class Builder2
{
    const DEFAULT_CONFIG_FILE = __DIR__ . DIRECTORY_SEPARATOR . "config.json";
    const DEFAULT_TEMPLATE_DIR = __DIR__ . DIRECTORY_SEPARATOR . "src/";
    const DEFAULT_DESTINATION_DIR = __DIR__ . DIRECTORY_SEPARATOR . "php/";
    const DEFAULT_EXECUTABLE_PERMISSIONS = 0755;
    const DEFAULT_VERBOSE_LEVEL = 1;
    
    /**
     * Build targets and their configuration.
     *
     * @var array
     */
    protected $build_config = [];
    
    /**
     * Directory to load template files from.
     *
     * @var string
     */
    protected $template_dir;
    
    /**
     * Destination directory for generated files.
     *
     * @var string
     */
    protected $destination_dir;
    
    /**
     * File permissions for executable files.
     *
     * @var int
     */
    protected $executable_permissions;
    
    /**
     * Verbosity level.
     *
     * @var int
     */
    protected $verbose_level;
    
    public function __construct($options = [])
    {
        $this->template_dir = $options["template_dir"] ?? static::DEFAULT_TEMPLATE_DIR;
        $this->destination_dir = $options["destination_dir"] ?? static::DEFAULT_DESTINATION_DIR;
        $this->executable_permissions = $options["executable_file_permissions"] ?? static::DEFAULT_EXECUTABLE_PERMISSIONS;
        $this->verbose_level = $options["verbose"] ?? static::DEFAULT_VERBOSE_LEVEL;
        
        $this->loadConfig($options["config_file"] ?? static::DEFAULT_CONFIG_FILE);
    }
    
    /**
     * Build the files described in the loaded config.
     */
    public function run()
    {
        foreach ($this->build_config as $name => $config) {
            $this->verbose(sprintf("Building '%s'...", $name), 1);
            foreach ($config["files"] as $file_name => $variables) {
                $destination_file = $this->getDestinationFile($file_name, $config);
                $contents = "";
                
                if ($template_file = $this->getTemplateFile($file_name, $config)) {
                    // Merge global variables to the template variables
                    $variables["version"] = $config["version"];
                    $variables["flavour"] = $config["flavour"];
                    $variables["imageSpecificPhpExtensions"] = $config["phpExtensions"];
                    $variables["xdebugVersion"] = $config["xdebugVersion"];

                    // Determine whether we should load with the template renderer, or whether we should straight up
                    // just load the file from disk.
                    if ($variables['_disable_variables'] ?? false) {
                        $contents = file_get_contents($template_file);
                    } else {
                        $contents = $this->renderTemplate($template_file, $variables);
                    }
                    
                    $contents = str_replace('{{generated_by_builder}}', 'This file is automatically generated. Do not edit directly.', $contents);
                }
                
                $this->verbose(sprintf("\tWriting '%s'...", $destination_file), 2);
                $this->writeFile($destination_file, $contents);
                
                if ($variables["executable"] ?? false) {
                    $this->verbose(sprintf("\tUpdating permissions on '%s' to '%o'...", $destination_file, $this->executable_permissions), 2);
                    $this->setFilePermissions($destination_file, $this->executable_permissions);
                }
            }
        }
    }
    
    /**
     * Load the build configuration from the given file.
     *
     * @param string $file
     *
     * @return $this
     * @throws Exception
     */
    protected function loadConfig($file)
    {
        $config = json_decode(file_get_contents($file), true);
        
        if (!is_array($config)) {
            throw new Exception(sprintf("Invalid configuration in %s!", $file));
        }
        
        $this->build_config = $config;
        
        return $this;
    }
    
    /**
     * Return the template file name for the given file.
     *
     * @param string $filename
     * @param array  $config
     *
     * @return null|string
     */
    protected function getTemplateFile($filename, $config)
    {
        $potential_file_names = [
            sprintf("%s-%s-%s", $filename, $config["version"], $config["flavour"]),
            sprintf("%s-%s", $filename, $config["version"]),
            sprintf("%s-%s", $filename, $config["flavour"]),
            $filename,
        ];
        
        foreach ($potential_file_names as $potential_file_name) {
            $path = $this->template_dir . DIRECTORY_SEPARATOR . $potential_file_name;
            
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Get the destination for the given file.
     *
     * @param string $file_name
     * @param array  $config
     *
     * @return string
     */
    protected function getDestinationFile($file_name, $config)
    {
        return implode(DIRECTORY_SEPARATOR, [
            $this->destination_dir,
            $config["version"] . '-' . $config["flavour"],
            $file_name,
        ]);
    }
    
    /**
     * Render the given template file using the provided variables and return the resulting output.
     *
     * @param string $template_file
     * @param array  $variables
     *
     * @return string
     */
    protected function renderTemplate($template_file, $variables)
    {
        extract($variables, EXTR_OVERWRITE);
        ob_start();
        
        include $template_file;
        
        $output = ob_get_clean();
        
        return $output ?: "";
    }
    
    /**
     * Write the contents to the given file.
     *
     * @param string $file_name
     * @param string $contents
     *
     * @return $this
     * @throws Exception
     */
    protected function writeFile($file_name, $contents)
    {
        $directory = dirname($file_name);
        
        // If the directory doesn't created then try to create the directory.
        if (!is_dir($directory)) {
            // Create the directory, preventing race conditions if another process creates the directory for us.
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new Exception(sprintf("Unable to create directory %s!", $directory));
            }
        }
        
        if (file_put_contents($file_name, $contents) === false) {
            throw new Exception(sprintf("Failed to write %s!", $file_name));
        }
        
        return $this;
    }
    
    /**
     * Update the permissions on the given file.
     *
     * @param string $file_name
     * @param int    $permissions
     *
     * @return $this
     */
    protected function setFilePermissions($file_name, $permissions = 0644)
    {
        chmod($file_name, $permissions);
        
        return $this;
    }
    
    /**
     * Print an informational message to the command line.
     *
     * @param string $message
     * @param int    $level
     * @param bool   $newline
     *
     * @return $this
     */
    protected function verbose($message, $level = 1, $newline = true)
    {
        if ($level <= $this->verbose_level) {
            printf("%s%s", $message, $newline ? PHP_EOL : "");
        }
        
        return $this;
    }
}

/**
 * __MAIN__
 */

$args = getopt("hvq");
$options = [];

if (isset($args["h"])) {
    echo <<<USAGE
Usage: php builder.php [options]

Options:
    -h  Print out this help message.
    -v  Enable verbose output. Can be used multiple times to increase verbosity level.
    -q  Silence informational messages.
USAGE;
    exit;
}

if (isset($args["q"])) {
    $options["verbose"] = 0;
} else if (isset($args["v"])) {
    $options["verbose"] = count($args["v"]);
}

$builder = new Builder2($options);
$builder->run();