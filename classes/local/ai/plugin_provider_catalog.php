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

/**
 * Catalog of plugin-managed provider presets.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_provider_catalog {
    /**
     * Return plugin-managed provider definitions.
     *
     * @return array
     */
    public static function get_definitions(): array {
        return [
            'aidiscussion_openai' => [
                'label' => get_string('pluginprovideropenai', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'https://api.openai.com/v1',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginprovideropenai_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_anthropic' => [
                'label' => get_string('pluginprovideranthropic', 'mod_aidiscussion'),
                'transport' => 'anthropic',
                'defaultbaseurl' => 'https://api.anthropic.com',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginprovideranthropic_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_deepseek' => [
                'label' => get_string('pluginproviderdeepseek', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'https://api.deepseek.com',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginproviderdeepseek_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_gemini' => [
                'label' => get_string('pluginprovidergemini', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginprovidergemini_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_ollama' => [
                'label' => get_string('pluginproviderollama', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'http://localhost:11434/v1',
                'defaultmodel' => '',
                'requiresapikey' => false,
                'description' => get_string('pluginproviderollama_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_minimax' => [
                'label' => get_string('pluginproviderminimax', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'https://api.minimax.io/v1',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginproviderminimax_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_mistral' => [
                'label' => get_string('pluginprovidermistral', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'https://api.mistral.ai/v1',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginprovidermistral_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_xai' => [
                'label' => get_string('pluginproviderxai', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'https://api.x.ai/v1',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginproviderxai_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_openrouter' => [
                'label' => get_string('pluginprovideropenrouter', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => 'https://openrouter.ai/api/v1',
                'defaultmodel' => '',
                'requiresapikey' => true,
                'description' => get_string('pluginprovideropenrouter_desc', 'mod_aidiscussion'),
            ],
            'aidiscussion_custom' => [
                'label' => get_string('pluginprovidercustom', 'mod_aidiscussion'),
                'transport' => 'openai',
                'defaultbaseurl' => '',
                'defaultmodel' => '',
                'requiresapikey' => false,
                'description' => get_string('pluginprovidercustom_desc', 'mod_aidiscussion'),
            ],
        ];
    }
}
