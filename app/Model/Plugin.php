<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use JsonPath\JsonObject;
use Acelle\Library\QuotaTrackerFile;
use Acelle\Library\Log as MailLog;
use Validator;
use ZipArchive;
use Illuminate\Validation\ValidationException;
use Acelle\Library\Tool;
use Composer\Autoload\ClassLoader;
use App;
use Acelle\Library\Facades\Hook;

class Plugin extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    const STORAGE_PATH = 'app/plugins'; // do not use this constant, use getStoragePath instead

    protected $fillable = ['name', 'title', 'description', 'version'];
    protected $requiredKeys = [
        'name', 'version', 'app_version'
    ];

    /**
     * Items per page.
     *
     * @var array
     */
    public static $itemsPerPage = 25;

    /**
     * Filter items.
     *
     * @return collect
     */
    public static function scopeFilter($query, $request)
    {
        $user = $request->user();

        // Keyword
        if (!empty(trim($request->keyword))) {
            foreach (explode(' ', trim($request->keyword)) as $keyword) {
                $query = $query->where(function ($q) use ($keyword) {
                    $q->orwhere('plugins.name', 'like', '%'.$keyword.'%')
                        ->orWhere('plugins.description', 'like', '%'.$keyword.'%')
                        ->orWhere('plugins.version', 'like', '%'.$keyword.'%');
                });
            }
        }

        // filters
        $filters = $request->filters;
        if (!empty($filters)) {
            if (!empty($filters['type'])) {
                $query = $query->where('plugins.type', '=', $filters['type']);
            }
        }

        return $query;
    }

    /**
     * Search items.
     *
     * @return collect
     */
    public static function scopeSearch($query, $request)
    {
        $query = self::filter($request);

        if (!empty($request->sort_order)) {
            $query = $query->orderBy($request->sort_order, $request->sort_direction);
        }

        return $query;
    }

    /**
     * Get campaign validation rules.
     */
    public function rules()
    {
        $rules = array(
            'name' => 'required',
            'version' => 'required',
        );

        return $rules;
    }

    /**
     * Get all items.
     *
     * @return collect
     */
    public static function scopeActive($query)
    {
        return $query->where('status', '=', self::STATUS_ACTIVE);
    }

    /**
     * Bootstrap any application services.
     */
    public static function boot()
    {
        parent::boot();

        // Create uid when creating list.
        static::creating(function ($item) {
            $item->uid = uniqid();
        });

        // While deleting
        static::deleting(function ($item) {
            // remove source code
            Tool::xdelete($item->getPluginPath());
        });
    }

    /**
     * Find item by uid.
     *
     * @return object
     */
    public static function findByUid($uid)
    {
        return self::where('uid', '=', $uid)->first();
    }

    /**
     * Disable verification server.
     *
     * @return array
     */
    public function disable()
    {
        $this->status = self::STATUS_INACTIVE;
        $this->save();
    }

    /**
     * Upload a plugin to tmp.
     */
    public static function upload($request)
    {
        $pluginsPath = storage_path('app/plugins');

        $rules = array(
            'file' => 'required|mimetypes:application/zip,application/octet-stream,application/x-zip-compressed,multipart/x-zip',
        );

        $validator = Validator::make($request->all(), $rules, [
            'file.mimetypes' => 'Input must be a valid .zip file',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // move file to temp place
        $tmp_path = storage_path('tmp/uploaded_plugin_'.time());
        $file_name = $request->file('file')->getClientOriginalName();
        $request->file('file')->move($tmp_path, $file_name);
        $tmp_zip = join_paths($tmp_path, $file_name);

        // read zip file check if zip archive invalid
        $zip = new ZipArchive();
        if ($zip->open($tmp_zip, ZipArchive::CREATE) !== true) {
            $validator->errors()->add('file', 'Cannot open plugin package');
            throw new ValidationException($validator);
        }

        // unzip template archive and remove zip file
        $zip->extractTo($tmp_path);
        $zip->close();
        unlink($tmp_zip);

        // read plugin file
        if (!is_file($configFile = $tmp_path.'/composer.json')) {
            throw new \Exception('Invalid plugin package. No meta data found');
        }
        
        try {
            $config = json_decode(file_get_contents($configFile), true);
        } catch (\Exception $ex) {
            throw new \Exception('Invalid plugin package. Cannot parse meta data');
        }

        $names = explode('/', $config['name']);
        // check valid plugin name
        if (count($names) != 2) {
            throw new \Exception('Invalid plugin package. No meta data found');
        }
        $pluginVendor = $names[0];
        $pluginName = $names[1];

        // chek if plugin exist
        $pluginsVendorPath = $pluginsPath . '/' . $pluginVendor;
        $pluginsSourcePath = $pluginsVendorPath . '/' . $pluginName;

        // create folders
        $paths = [
            $pluginsPath,
            $pluginsVendorPath,
            $pluginsSourcePath,
        ];
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
        }

        // check if overwrite plugin
        if ($request->overwrite) {
            $config['overwrite'] = true;
        }
        
        // throw exception if any required key is missing
        self::validateMetaData($config);

        // move from tmp to storage/app/plugins
        Tool::xdelete($pluginsSourcePath);
        if (!file_exists($pluginsSourcePath)) {
            mkdir($pluginsSourcePath, 0777, true);
        }
        Tool::xcopy($tmp_path, $pluginsSourcePath);
        Tool::xdelete($tmp_path);

        return $config['name'];
    }

    /**
     * Upload a template.
     */
    public static function uploadAndSave($request)
    {
        $pluginsPath = storage_path('app/plugins');

        $rules = array(
            'file' => 'required|mimetypes:application/zip,application/octet-stream,application/x-zip-compressed,multipart/x-zip',
        );

        $validator = Validator::make($request->all(), $rules, [
            'file.mimetypes' => 'Input must be a valid .zip file',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // move file to temp place
        $tmp_path = storage_path('tmp/uploaded_plugin_'.time());
        $file_name = $request->file('file')->getClientOriginalName();
        $request->file('file')->move($tmp_path, $file_name);
        $tmp_zip = join_paths($tmp_path, $file_name);

        // read zip file check if zip archive invalid
        $zip = new ZipArchive();
        if ($zip->open($tmp_zip, ZipArchive::CREATE) !== true) {
            $validator->errors()->add('file', 'Cannot open plugin package');
            throw new ValidationException($validator);
        }

        // unzip template archive and remove zip file
        $zip->extractTo($tmp_path);
        $zip->close();
        unlink($tmp_zip);

        // read plugin file
        if (!is_file($configFile = $tmp_path.'/composer.json')) {
            throw new \Exception('Invalid plugin package. No meta data found');
        }
        
        try {
            $config = json_decode(file_get_contents($configFile), true);
        } catch (\Exception $ex) {
            throw new \Exception('Invalid plugin package. Cannot parse meta data');
        }

        $names = explode('/', $config['name']);
        // check valid plugin name
        if (count($names) != 2) {
            throw new \Exception('Invalid plugin package. No meta data found');
        }
        $pluginVendor = $names[0];
        $pluginName = $names[1];

        // chek if plugin exist
        $pluginsVendorPath = $pluginsPath . '/' . $pluginVendor;
        $pluginsSourcePath = $pluginsVendorPath . '/' . $pluginName;

        // create folders
        $paths = [
            $pluginsPath,
            $pluginsVendorPath,
            $pluginsSourcePath,
        ];
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
        }

        // check if overwrite plugin
        if ($request->overwrite) {
            $config['overwrite'] = true;
        }
        
        // throw exception if any required key is missing
        self::validateMetaData($config);

        // new plugin
        $plugin = self::where('name', '=', $config['name'])->first();
        // ->where('type', '=', $config['type'])
        if (!$plugin) {
            $plugin = new \Acelle\Model\Plugin();
            $plugin->status = \Acelle\Model\Plugin::STATUS_ACTIVE;
        }
        $plugin->name = $config['name'];
        $plugin->title = $config['title'];
        $plugin->description = (array_key_exists('description', $config)) ? $config['description'] : null;
        $plugin->version = $config['version'];

        // remove tmp folder
        // exec("rm -r {$tmp_path}");
        Tool::xdelete($pluginsSourcePath);
        if (!file_exists($pluginsSourcePath)) {
            mkdir($pluginsSourcePath, 0777, true);
        }
        Tool::xcopy($tmp_path, $pluginsSourcePath);
        Tool::xdelete($tmp_path);

        $plugin->save();

        return $plugin;
    }

    /**
     * Get plugin path.
     *
     * @return string
     */
    public function getPluginPath()
    {
        $pluginsPath = storage_path('app/plugins');

        $names = explode('/', $this->name);

        $pluginVendor = $names[0];
        $pluginName = $names[1];

        // chek if plugin exist
        $pluginsVendorPath = $pluginsPath . '/' . $pluginVendor;
        $pluginsSourcePath = $pluginsVendorPath . '/' . $pluginName;

        return $pluginsSourcePath;
    }

    /**
     * Get plugin type select options.
     *
     * @return array
     */
    public static function typeSelectOptions()
    {
        $options = [
            ['value' => 'payment', 'text' => trans('messages.plugin.type.payment')],
            ['value' => 'sending_server', 'text' => trans('messages.plugin.type.sending_server')],
            ['value' => 'email_verification', 'text' => trans('messages.plugin.type.email_verification')],
        ];

        return $options;
    }

    /**
     * Check if plugin is installed.
     *
     * @return array
     */
    public static function isInstalled($name)
    {
        return self::where('name', '=', $name)->count();
    }

    public function isActive()
    {
        return $this->status == self::STATUS_ACTIVE;
    }

    /**
     * Get plugin by name.
     *
     * @return array
     */
    public static function getByName($name)
    {
        return self::where('name', '=', $name)->first();
    }

    public static function validateMetaData($config = [])
    {
        // Check required keys
        $requiredKeys = ['name', 'title', 'version', 'app_version']; // ['name', 'title', 'version', 'app_version'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw new \Exception("Invalid plugin package. Unknown '{$key}'");
            }
        }

        $exist = self::where('name', $config['name'])->first();
        if ($exist && !isset($config['overwrite'])) {
            if (version_compare($exist->version, $config['version'], '>=')) {
                throw new \Exception("A newer version of the {$confif['name']} plugin already exists (version : {$exist->version}), do you want to replace it?");
            } else {
                throw new \Exception("An older version of the {$confif['name']} plugin already exists (version : {$exist->version}), do you want to replace it?");
            }
        }

        // Check app version
        $currentVersion = app_version();
        $requiredVersion = $config['app_version'];
        $checked = version_compare($currentVersion, $requiredVersion, '>=');
        if (!$checked) {
            throw new \Exception("The uploaded plugin requires Acelle platform version {$requiredVersion} or higher. The current version is {$currentVersion}");
        }

        // // Check license type
        // if (empty(Setting::get('license_type'))) {
        //     throw new \Exception("The uploaded plugin requires a valid license of Acelle to proceed");
        // }

        // // Check license type
        // if (Setting::get('license_type') != 'extended') {
        //     throw new \Exception("The uploaded plugin requires an 'Extended' license of Acelle to proceed, your current license is 'Regular'");
        // }
    }

    /**
     * Get data.
     *
     * @var object | collect
     */
    public function getData()
    {
        if (!$this['data']) {
            return json_decode('{}', true);
        }

        return json_decode($this['data'], true);
    }

    /**
     * Get data.
     *
     * @var object | collect
     */
    public function updateData($data)
    {
        $data = (object) array_merge((array) $this->getData(), $data);
        $this['data'] = json_encode($data);

        $this->save();
    }

    public function activate()
    {
        $hookName = 'activate_plugin_'.$this->name;
        Hook::execute($hookName);

        $this->status = self::STATUS_ACTIVE;
        $this->save();
    }

    public function reset()
    {
        $this->status = self::STATUS_INACTIVE;
        $this->data = null;
        $this->save();
    }

    public function getStoragePath($subpath = null, $absolute = true)
    {
        if (is_null($this->name)) {
            throw new \Exception("Plugin name is null");
        }

        if (preg_match('/^[a-z0-9\-]+\/[a-z0-9\-]+$/', $this->name) == 0) {
            throw new \Exception("Plugin name must match 'author/name' pattern. Only lowercase letters, numbers and/or dash (-) are allowed for 'author' and 'name'");
        }

        $base = join_paths(self::STORAGE_PATH, $this->name);

        if ($absolute) {
            $base = storage_path($base);
        }
        
        $path = join_paths($base, $subpath);
        return $path;
    }

    public function getComposerJsonFilePath()
    {
        return $this->getStoragePath('composer.json');
    }

    public function getComposerJson()
    {
        $pluginDir = $this->getStoragePath();
        if (!file_exists($pluginDir)) {
            throw new \Exception("Plugin folder '{$pluginDir}' missing");
        }

        $composerFile = $this->getComposerJsonFilePath();

        if (!file_exists($composerFile)) {
            throw new \Exception("Cannot find {$composerFile} in plugin folder. Invalid plugin?");
        }

        try {
            $composerJson = json_decode(file_get_contents($composerFile), true);
        } catch (\Exception $ex) {
            throw new \Exception("Cannot parse {$composerFile}"); // for easy debugging
        }

        return $composerJson;
    }

    public static function autoload($includeInactivePlugin = false)
    {
        $activePlugins = self::all();
        foreach ($activePlugins as $plugin) {
            // $boot = $plugin->isActive();
            $boot = true; // need its route registration
            $plugin->load($boot);
        }
    }

    public function load($boot = true)
    {
        $composerJson = $this->getComposerJson($this->name);

        if (!array_key_exists('autoload', $composerJson)) {
            throw new \Exception("Cannot boot plugin '{$this->name}'. No 'autoload' found in composer.json");
        }

        if (!array_key_exists('psr-4', $composerJson['autoload'])) {
            throw new \Exception("Cannot boot plugin '{$this->name}'. No 'autoload.psr4' found in composer.json");
        }

        $loader = new ClassLoader();

        $psr4 = $composerJson['autoload']['psr-4'];
        foreach ($psr4 as $prefix => $path) {
            $realpath = $this->getStoragePath($path);
            $loader->addPsr4($prefix, $realpath);
        }

        $loader->register();

        if (!$boot) {
            return;
        }

        // Load and boot ServiceProvider
        $serviceProviders = [];
        if (
            array_key_exists('extra', $composerJson) &&
            array_key_exists('laravel', $composerJson['extra']) &&
            array_key_exists('providers', $composerJson['extra']['laravel'])) {
            $serviceProviders = $composerJson['extra']['laravel']['providers'];
        }

        // Register service, bootstraping
        foreach ($serviceProviders as $service) {
            App::register($service);
        }
    }

    public static function installFromDir($name)
    {
        // Instantiate the plugin object, not save yet
        $plugin = self::firstOrNew(['name' => $name]);

        // plugin.name attribute is required
        // before we can get other information
        // Validation may throw an exception
        $composer = $plugin->getComposerJson();

        if ($plugin->name != $composer['name']) {
            throw new \Exception("Plugin name in composer.json is expected to be '{$plugin->name}', found '".$composer['name']."'");
        }

        $plugin->title = $composer['title'];
        $plugin->description = array_key_exists('description', $composer) ? $composer['description'] : null;
        $plugin->version = array_key_exists('version', $composer) ? $composer['version'] : '0.0.0';
        $plugin->status = self::STATUS_INACTIVE;

        $plugin->save();

        return $plugin;
    }

    public function getIconUrl()
    {
        $possibleFileNames = ['icon.svg', 'icon.jpg', 'icon.png', 'icon.gif'];

        foreach ($possibleFileNames as $file) {
            $absPath = $this->getStoragePath($file);
            if (file_exists($absPath)) {
                $relativePath =  $this->getStoragePath($file, $absolute = false);
                return \Acelle\Helpers\generatePublicPath($relativePath);
            }
        }

        return;
    }
}
