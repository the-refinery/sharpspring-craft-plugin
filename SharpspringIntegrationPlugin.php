<?php
/**
 * SharpSpring Integration plugin for Craft CMS
 *
 * A SharpSpring integration plugin.
 *
 * --snip--
 * Craft plugins are very much like little applications in and of themselves. We’ve made it as simple as we can,
 * but the training wheels are off. A little prior knowledge is going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL, as well as some semi-
 * advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 * --snip--
 *
 * @author    The Refinery
 * @copyright Copyright (c) 2019 The Refinery
 * @link      https://the-refinery.io
 * @package   SharpspringIntegration
 * @since     3.0
 */

namespace sharpspring;
require 'vendor/autoload.php';

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
    $event->rules['sharpspringintegration/pushAsync'] = ['template' => 'cocktails/_edit'];
    $event->rules['cocktails/<widgetId:\d+>'] = 'sharpspringIntegration/pushAsync';
});

class SharpspringIntegrationPlugin extends BasePlugin
{
    /**
     * Called after the plugin class is instantiated; do any one-time initialization here such as hooks and events:
     *
     * \Craft::$app->on('entries.saveEntry', function(Event $event) {
     *    // ...
     * });
     *
     * or loading any third party Composer packages via:
     *
     * require_once __DIR__ . '/vendor/autoload.php';
     *
     * @return mixed
     */
    public function init()
    {
        parent::init();

        \Craft::$app->sharpspringIntegration_mappingConfig->setup();

        foreach (glob(\Craft::$app->path->getConfigPath()."sharpspringintegration/*_extension.php") as $filename)
        {
            SharpspringIntegrationPlugin::log(
                "Including SharpSpring Integration Extension file: ".$filename,
                LogLevel::Info,
                false
            );
            include $filename;
        }

        foreach (get_declared_classes() as $className) {
            if (in_array('sharpSpring\SharpSpringIntegration\Interfaces\EventInterface', class_implements($className))) {
                $r = new \ReflectionClass($className);
                $staticInstance = $r->newInstanceWithoutConstructor();
                $eventHandle = $staticInstance->getEventHandle();

                \Craft::$app->on(
                    $eventHandle,
                    function(Event $event) use ($className) {
                        $instance = new $className();
                        $instance->handleEvent($event);
                    }
                );
            }
        }
    }

    /**
     * Returns the user-facing name.
     *
     * @return mixed
     */
    public function getName()
    {
         return Craft::t('SharpSpring Integration');
    }

    /**
     * Plugins can have descriptions of themselves displayed on the Plugins page by adding a getDescription() method
     * on the primary plugin class:
     *
     * @return mixed
     */
    public function getDescription()
    {
        return Craft::t('A SharpSpring integration plugin.');
    }

    /**
     * Plugins can have links to their documentation on the Plugins page by adding a getDocumentationUrl() method on
     * the primary plugin class:
     *
     * @return string
     */
    public function getDocumentationUrl()
    {
        return 'https://github.com/https://github.com/the-refinery/sharpspringintegration/blob/craft-3/README.md';
    }

    /**
     * Plugins can now take part in Craft’s update notifications, and display release notes on the Updates page, by
     * providing a JSON feed that describes new releases, and adding a getReleaseFeedUrl() method on the primary
     * plugin class.
     *
     * @return string
     */
    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/https://github.com/the-refinery/sharpspringintegration/craft-3/CHANGELOG.md';
    }

    /**
     * Returns the version number.
     *
     * @return string
     */
    public function getVersion()
    {
        return '3.0';
    }

    public function getSchemaVersion()
    {
        return '3.0';
    }

    /**
     * Returns the developer’s name.
     *
     * @return string
     */
    public function getDeveloper()
    {
        return 'The Refinery';
    }

    /**
     * Returns the developer’s website URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'https://the-refinery.io';
    }

    /**
     * Returns whether the plugin should get its own tab in the CP header.
     *
     * @return bool
     */
    public function hasCpSection()
    {
        return false;
    }

    /**
     * Called right before your plugin’s row gets stored in the plugins database table, and tables have been created
     * for it based on its records.
     */
    public function onBeforeInstall()
    {
    }

    /**
     * Called right after your plugin’s row has been stored in the plugins database table, and tables have been
     * created for it based on its records.
     */
    public function onAfterInstall()
    {
    }

    /**
     * Called right before your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onBeforeUninstall()
    {
    }

    /**
     * Called right after your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onAfterUninstall()
    {
    }

    /**
     * Defines the attributes that model your plugin’s available settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'someSetting' => array(AttributeType::String, 'label' => 'Some Setting', 'default' => ''),
        );
    }

    /**
     * Returns the HTML that displays your plugin’s settings.
     *
     * @return mixed
     */
    public function getSettingsHtml()
    {
       return \Craft::$app->view->renderTemplate('sharpspringintegration/SharpspringIntegration_Settings', array(
           'settings' => $this->getSettings()
       ));
    }

    /**
     * If you need to do any processing on your settings’ post data before they’re saved to the database, you can
     * do it with the prepSettings() method:
     *
     * @param mixed $settings  The plugin's settings
     *
     * @return mixed
     */
    public function prepSettings($settings)
    {
        // Modify $settings here...

        return $settings;
    }

}
