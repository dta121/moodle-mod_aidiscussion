<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_aidiscussion\local\ai;

use core_ai\aiactions\generate_text;
use core_ai\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads configured Moodle AI provider instances for this activity.
 */
class provider_registry {
    /** @var string */
    public const SOURCE_CORE = 'core';

    /** @var string */
    public const SOURCE_PLUGIN = 'plugin';

    /** @var string */
    public const SOURCE_BOTH = 'both';

    /**
     * Return configured provider instances that support text generation.
     *
     * @return array
     */
    public static function get_generate_text_options(): array {
        $options = [
            '' => get_string('chooseprovider', 'mod_aidiscussion'),
        ];

        $sourcemode = self::get_provider_source_mode();

        if (in_array($sourcemode, [self::SOURCE_CORE, self::SOURCE_BOTH], true)) {
            $options += self::get_core_provider_options();
        }

        if (in_array($sourcemode, [self::SOURCE_PLUGIN, self::SOURCE_BOTH], true)) {
            $options += self::get_plugin_provider_options();
        }

        $placeholder = $options[''];
        unset($options['']);
        natcasesort($options);

        return ['' => $placeholder] + $options;
    }

    /**
     * Return valid source mode options.
     *
     * @return array
     */
    public static function get_provider_source_options(): array {
        return [
            self::SOURCE_BOTH => get_string('providersourceboth', 'mod_aidiscussion'),
            self::SOURCE_CORE => get_string('providersourcecore', 'mod_aidiscussion'),
            self::SOURCE_PLUGIN => get_string('providersourceplugin', 'mod_aidiscussion'),
        ];
    }

    /**
     * Return the configured provider source mode.
     *
     * @return string
     */
    public static function get_provider_source_mode(): string {
        $mode = trim((string)get_config('aidiscussion', 'providersourcemode'));
        if (!isset(self::get_provider_source_options()[$mode])) {
            $mode = self::SOURCE_BOTH;
        }

        return $mode;
    }

    /**
     * Return whether the provider id is a plugin-managed provider.
     *
     * @param string $providerid
     * @return bool
     */
    public static function is_plugin_provider(string $providerid): bool {
        return isset(plugin_provider_catalog::get_definitions()[trim($providerid)]);
    }

    /**
     * Return whether the provider id is a Moodle core AI provider.
     *
     * @param string $providerid
     * @return bool
     */
    public static function is_core_provider(string $providerid): bool {
        $providerid = trim($providerid);
        if (preg_match('/^aiprovider_[a-z0-9_]+$/', $providerid) !== 1) {
            return false;
        }

        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('aiprovider');
        return isset($plugins[substr($providerid, strlen('aiprovider_'))]);
    }

    /**
     * Return whether the provider id is known to the plugin.
     *
     * @param string $providerid
     * @return bool
     */
    public static function is_known_provider(string $providerid): bool {
        return self::is_core_provider($providerid) || self::is_plugin_provider($providerid);
    }

    /**
     * Return plugin provider definitions keyed by id.
     *
     * @return array
     */
    public static function get_plugin_provider_definitions(): array {
        return plugin_provider_catalog::get_definitions();
    }

    /**
     * Return the plugin-managed provider config.
     *
     * @param string $providerid
     * @return array
     */
    public static function get_plugin_provider_config(string $providerid): array {
        $providerid = trim($providerid);
        $definitions = plugin_provider_catalog::get_definitions();
        if (!isset($definitions[$providerid])) {
            throw new \coding_exception('Unknown plugin-managed provider: ' . $providerid);
        }

        $definition = $definitions[$providerid];
        $config = get_config('aidiscussion');
        $enabled = !empty($config->{$providerid . '_enabled'});
        $baseurl = trim((string)($config->{$providerid . '_baseurl'} ?? $definition['defaultbaseurl']));
        $model = trim((string)($config->{$providerid . '_model'} ?? $definition['defaultmodel'] ?? ''));
        $temperature = (float)($config->{$providerid . '_temperature'} ?? 0.7);
        $maxtokens = max(1, (int)($config->{$providerid . '_maxtokens'} ?? 600));
        $apikey = (string)($config->{$providerid . '_apikey'} ?? '');

        return [
            'id' => $providerid,
            'label' => $definition['label'],
            'transport' => $definition['transport'],
            'enabled' => (bool)$enabled,
            'requiresapikey' => !empty($definition['requiresapikey']),
            'baseurl' => $baseurl,
            'model' => $model,
            'temperature' => $temperature,
            'maxtokens' => $maxtokens,
            'apikey' => $apikey,
            'description' => $definition['description'],
        ];
    }

    /**
     * Return whether the plugin-managed provider has enough config to run.
     *
     * @param string $providerid
     * @return bool
     */
    public static function is_plugin_provider_configured(string $providerid): bool {
        $config = self::get_plugin_provider_config($providerid);
        if (!$config['enabled']) {
            return false;
        }

        if ($config['baseurl'] === '' || $config['model'] === '') {
            return false;
        }

        if (!empty($config['requiresapikey']) && trim((string)$config['apikey']) === '') {
            return false;
        }

        return true;
    }

    /**
     * Resolve a provider name by component.
     *
     * @param string $providername
     * @return string
     */
    public static function get_provider_name(string $providername): string {
        $providername = trim($providername);
        if ($providername === '') {
            return get_string('chooseprovider', 'mod_aidiscussion');
        }

        if (self::is_plugin_provider($providername)) {
            $definitions = plugin_provider_catalog::get_definitions();
            return $definitions[$providername]['label'] . ' [' . get_string('providersourcelabelplugin', 'mod_aidiscussion') . ']';
        }

        return self::get_core_provider_label($providername);
    }

    /**
     * Return configured Moodle core provider options.
     *
     * @return array
     */
    private static function get_core_provider_options(): array {
        $options = [];

        if (!class_exists(manager::class) || !class_exists(generate_text::class)) {
            return $options;
        }

        $providersbyaction = manager::get_providers_for_actions([generate_text::class], true);
        $providers = $providersbyaction[generate_text::class] ?? [];

        foreach ($providers as $provider) {
            $component = $provider->get_name();
            $options[$component] = self::get_core_provider_label($component);
        }

        return $options;
    }

    /**
     * Return configured plugin-managed provider options.
     *
     * @return array
     */
    private static function get_plugin_provider_options(): array {
        $options = [];

        foreach (array_keys(plugin_provider_catalog::get_definitions()) as $providerid) {
            if (!self::is_plugin_provider_configured($providerid)) {
                continue;
            }
            $options[$providerid] = self::get_provider_name($providerid);
        }

        return $options;
    }

    /**
     * Build a human readable label for a core provider component.
     *
     * @param string $component
     * @return string
     */
    private static function get_core_provider_label(string $component): string {
        $label = $component;
        $stringmanager = get_string_manager();
        if ($stringmanager->string_exists('pluginname', $component)) {
            $label = get_string('pluginname', $component);
        }

        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('aiprovider');
        if (isset($plugins[substr($component, strlen('aiprovider_'))])) {
            return $label . ' [' . get_string('providersourcelabelcore', 'mod_aidiscussion') . ']';
        }

        return $label;
    }
}
