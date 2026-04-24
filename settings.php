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

/**
 * Site-level defaults for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_aidiscussion\local\ai\plugin_provider_catalog;
use mod_aidiscussion\local\ai\provider_registry;

if ($hassiteconfig) {
    $disabled = $module->is_enabled() === false;
    $categoryname = 'modsettingsaidiscussioncat';
    $plugincategoryname = 'modsettingsaidiscussionpluginproviders';

    $provideroptions = provider_registry::get_generate_text_options();

    $addpageintro = static function(admin_settingpage $page, string $settingid, string $description): void {
        $page->add(new admin_setting_heading($settingid, '', $description));
    };

    $addpluginprovidersettings = static function(admin_settingpage $page, string $providerid, array $definition): void {
        $page->add(new admin_setting_configcheckbox(
            'aidiscussion/' . $providerid . '_enabled',
            get_string('pluginproviderenabled', 'mod_aidiscussion'),
            '',
            0
        ));

        $apikeydesc = !empty($definition['requiresapikey']) ?
            get_string('pluginproviderapikeydesc_required', 'mod_aidiscussion') :
            get_string('pluginproviderapikeydesc_optional', 'mod_aidiscussion');
        $page->add(new admin_setting_configpasswordunmask(
            'aidiscussion/' . $providerid . '_apikey',
            get_string('pluginproviderapikey', 'mod_aidiscussion'),
            $apikeydesc,
            '',
            PARAM_RAW_TRIMMED
        ));

        $page->add(new admin_setting_configtext(
            'aidiscussion/' . $providerid . '_baseurl',
            get_string('pluginproviderbaseurl', 'mod_aidiscussion'),
            '',
            $definition['defaultbaseurl'],
            PARAM_URL
        ));

        $page->add(new admin_setting_configtext(
            'aidiscussion/' . $providerid . '_model',
            get_string('pluginprovidermodel', 'mod_aidiscussion'),
            '',
            $definition['defaultmodel'],
            PARAM_TEXT
        ));

        $page->add(new admin_setting_configtext(
            'aidiscussion/' . $providerid . '_temperature',
            get_string('pluginprovidertemperature', 'mod_aidiscussion'),
            '',
            '0.7',
            PARAM_FLOAT
        ));

        $page->add(new admin_setting_configtext(
            'aidiscussion/' . $providerid . '_maxtokens',
            get_string('pluginprovidermaxtokens', 'mod_aidiscussion'),
            '',
            600,
            PARAM_INT
        ));
    };

    $ADMIN->add('modsettings', new admin_category(
        $categoryname,
        get_string('pluginname', 'mod_aidiscussion'),
        $disabled
    ));

    $activitypage = new admin_settingpage(
        $section,
        get_string('settingsactivitydefaults', 'mod_aidiscussion'),
        'moodle/site:config',
        $disabled
    );
    if ($ADMIN->fulltree) {
        $addpageintro(
            $activitypage,
            'aidiscussion/settingsactivitydefaultsintro',
            get_string('settingsactivitydefaultsdesc', 'mod_aidiscussion')
        );

        $activitypage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultaienabled',
            get_string('defaultaienabled', 'mod_aidiscussion'),
            '',
            1
        ));

        $activitypage->add(new admin_setting_configtext(
            'aidiscussion/defaultaidisplayname',
            get_string('defaultaidisplayname', 'mod_aidiscussion'),
            get_string('defaultaidisplaynamedesc', 'mod_aidiscussion'),
            'AI facilitator',
            PARAM_TEXT
        ));

        $activitypage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultpostbeforeview',
            get_string('defaultpostbeforeview', 'mod_aidiscussion'),
            '',
            1
        ));

        $activitypage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultrequireinitialpost',
            get_string('defaultrequireinitialpost', 'mod_aidiscussion'),
            '',
            1
        ));

        $activitypage->add(new admin_setting_configtext(
            'aidiscussion/defaultrequiredpeerreplies',
            get_string('defaultrequiredpeerreplies', 'mod_aidiscussion'),
            '',
            2,
            PARAM_INT
        ));
    }
    $ADMIN->add($categoryname, $activitypage);

    $interactionpage = new admin_settingpage(
        'modsettingsaidiscussioninteraction',
        get_string('settingsinteractiondefaults', 'mod_aidiscussion'),
        'moodle/site:config',
        $disabled
    );
    if ($ADMIN->fulltree) {
        $addpageintro(
            $interactionpage,
            'aidiscussion/settingsinteractiondefaultsintro',
            get_string('settingsinteractiondefaultsdesc', 'mod_aidiscussion')
        );

        $interactionpage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultpublicaireplies',
            get_string('defaultpublicaireplies', 'mod_aidiscussion'),
            '',
            1
        ));

        $interactionpage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultallowprivateai',
            get_string('defaultallowprivateai', 'mod_aidiscussion'),
            '',
            1
        ));

        $interactionpage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultreplytopeerreplies',
            get_string('defaultreplytopeerreplies', 'mod_aidiscussion'),
            '',
            1
        ));

        $interactionpage->add(new admin_setting_configtext(
            'aidiscussion/defaultminsubstantivewords',
            get_string('defaultminsubstantivewords', 'mod_aidiscussion'),
            '',
            20,
            PARAM_INT
        ));

        $interactionpage->add(new admin_setting_configtext(
            'aidiscussion/defaultmaxairepliesperstudent',
            get_string('defaultmaxairepliesperstudent', 'mod_aidiscussion'),
            '',
            3,
            PARAM_INT
        ));

        $interactionpage->add(new admin_setting_configtext(
            'aidiscussion/defaultaireplydelayminutes',
            get_string('defaultaireplydelayminutes', 'mod_aidiscussion'),
            get_string('defaultaireplydelayminutesdesc', 'mod_aidiscussion'),
            0,
            PARAM_INT
        ));

        $interactionpage->add(new admin_setting_configtext(
            'aidiscussion/defaultresponsetone',
            get_string('defaultresponsetone', 'mod_aidiscussion'),
            '',
            'Professional, warm, and engaging',
            PARAM_TEXT
        ));

        $interactionpage->add(new admin_setting_configtextarea(
            'aidiscussion/defaultresponseinstructions',
            get_string('defaultresponseinstructions', 'mod_aidiscussion'),
            '',
            get_string('defaultresponseinstructionsvalue', 'mod_aidiscussion'),
            PARAM_RAW_TRIMMED
        ));
    }
    $ADMIN->add($categoryname, $interactionpage);

    $gradingpage = new admin_settingpage(
        'modsettingsaidiscussiongrading',
        get_string('settingsgradingprivacy', 'mod_aidiscussion'),
        'moodle/site:config',
        $disabled
    );
    if ($ADMIN->fulltree) {
        $addpageintro(
            $gradingpage,
            'aidiscussion/settingsgradingprivacyintro',
            get_string('settingsgradingprivacydesc', 'mod_aidiscussion')
        );

        $gradingpage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultshowrubricbeforeposting',
            get_string('defaultshowrubricbeforeposting', 'mod_aidiscussion'),
            '',
            1
        ));

        $gradingpage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultpseudonymiseusers',
            get_string('defaultpseudonymiseusers', 'mod_aidiscussion'),
            '',
            1
        ));

        $gradingpage->add(new admin_setting_configcheckbox(
            'aidiscussion/defaultintegrityflagsenabled',
            get_string('defaultintegrityflagsenabled', 'mod_aidiscussion'),
            '',
            1
        ));

        $gradingpage->add(new admin_setting_configtextarea(
            'aidiscussion/defaultgradinginstructions',
            get_string('defaultgradinginstructions', 'mod_aidiscussion'),
            '',
            get_string('defaultgradinginstructionsvalue', 'mod_aidiscussion'),
            PARAM_RAW_TRIMMED
        ));
    }
    $ADMIN->add($categoryname, $gradingpage);

    $providerspage = new admin_settingpage(
        'modsettingsaidiscussionproviders',
        get_string('settingsprovidersources', 'mod_aidiscussion'),
        'moodle/site:config',
        $disabled
    );
    if ($ADMIN->fulltree) {
        $addpageintro(
            $providerspage,
            'aidiscussion/settingsprovidersourcesintro',
            get_string('settingsprovidersourcesdesc', 'mod_aidiscussion')
        );

        $providerspage->add(new admin_setting_configselect(
            'aidiscussion/providersourcemode',
            get_string('providersourcemode', 'mod_aidiscussion'),
            get_string('providersourcemodedesc', 'mod_aidiscussion'),
            provider_registry::SOURCE_BOTH,
            provider_registry::get_provider_source_options()
        ));

        $providerspage->add(new admin_setting_configselect(
            'aidiscussion/defaultreplyprovider',
            get_string('defaultreplyprovider', 'mod_aidiscussion'),
            get_string('defaultreplyproviderdesc', 'mod_aidiscussion'),
            '',
            $provideroptions
        ));

        $providerspage->add(new admin_setting_configselect(
            'aidiscussion/defaultgradeprovider',
            get_string('defaultgradeprovider', 'mod_aidiscussion'),
            get_string('defaultgradeproviderdesc', 'mod_aidiscussion'),
            '',
            $provideroptions
        ));
    }
    $ADMIN->add($categoryname, $providerspage);

    $ADMIN->add($categoryname, new admin_category(
        $plugincategoryname,
        get_string('pluginproviderscategory', 'mod_aidiscussion'),
        $disabled
    ));

    foreach (plugin_provider_catalog::get_definitions() as $providerid => $definition) {
        $providerpage = new admin_settingpage(
            'modsettingsaidiscussionprovider_' . $providerid,
            $definition['label'],
            'moodle/site:config',
            $disabled
        );

        if ($ADMIN->fulltree) {
            $addpageintro(
                $providerpage,
                'aidiscussion/' . $providerid . '_intro',
                $definition['description']
            );
            $addpluginprovidersettings($providerpage, $providerid, $definition);
        }

        $ADMIN->add($plugincategoryname, $providerpage);
    }

    $settings = null;
}
