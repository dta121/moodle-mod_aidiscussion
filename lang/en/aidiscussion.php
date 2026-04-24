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
 * Language strings for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



$string['addinitialresponse'] = 'Add your initial response';
$string['aidiscussion:addinstance'] = 'Add a new AI discussion activity';
$string['aidiscussion:grade'] = 'Grade AI discussion';
$string['aidiscussion:manageai'] = 'Manage AI behaviour in AI discussion';
$string['aidiscussion:post'] = 'Post in AI discussion';
$string['aidiscussion:replypeer'] = 'Reply to peers in AI discussion';
$string['aidiscussion:view'] = 'View AI discussion';
$string['aidisplayname'] = 'AI display name';
$string['aienabled'] = 'Enable AI replies and AI grading';
$string['aifacilitator'] = 'AI facilitator';
$string['aigradingfeedbacksummary'] = 'AI-assisted score: {$a->score} / {$a->grademax}.';
$string['aiinteractioncomponent'] = 'AI interaction';
$string['aipolicyaccepted'] = 'AI policy accepted. You can now post in this activity.';
$string['aipolicyrequiredtopost'] = 'You must accept the site AI usage policy before your posts can be sent to {$a}.';
$string['aireplydelayminutes'] = 'AI reply delay (minutes)';
$string['aireplypending'] = 'AI reply pending';
$string['aisettings'] = 'AI settings';
$string['aiweight'] = 'AI interaction weight (%)';
$string['allowpeerreplies'] = 'Allow student peer replies';
$string['allowprivateai'] = 'Allow teacher to switch AI interactions to private';
$string['alreadypostedinitial'] = 'You have already submitted your initial response for this activity.';
$string['cannotposthere'] = 'You cannot post here: {$a}';
$string['chooseprovider'] = 'Choose a configured provider';
$string['configuration'] = 'Configuration summary';
$string['criterionname'] = 'Criterion';
$string['criterionprogress'] = 'Criterion progress';
$string['currentgrade'] = 'Current grade';
$string['defaultaidisplayname'] = 'Default AI display name';
$string['defaultaidisplaynamedesc'] = 'The display name shown for AI replies in new activities.';
$string['defaultaienabled'] = 'Enable AI by default';
$string['defaultaireplydelayminutes'] = 'Default AI reply delay (minutes)';
$string['defaultaireplydelayminutesdesc'] = 'How many minutes to wait before the queued AI reply becomes eligible to run. Actual delivery also depends on cron frequency.';
$string['defaultallowprivateai'] = 'Allow private AI mode by default';
$string['defaultgradeprovider'] = 'Default grading AI provider';
$string['defaultgradeproviderdesc'] = 'The default provider plugin for grading. Leave unset to let activities fall back to the discussion provider.';
$string['defaultgradinginstructions'] = 'Default AI grading instructions';
$string['defaultgradinginstructionsvalue'] = 'Score the initial post, AI interaction, and peer replies separately. Produce criterion-level reasoning, written feedback, an overall summary, and any heuristic integrity concerns.';
$string['defaultintegrityflagsenabled'] = 'Enable integrity flags by default';
$string['defaultmaxairepliesperstudent'] = 'Default maximum AI replies per student';
$string['defaultminsubstantivewords'] = 'Default minimum substantive words';
$string['defaultpostbeforeview'] = 'Require posting before view by default';
$string['defaultpseudonymiseusers'] = 'Pseudonymise users by default';
$string['defaultpublicaireplies'] = 'Public AI replies by default';
$string['defaultreplyprovider'] = 'Default discussion AI provider';
$string['defaultreplyproviderdesc'] = 'The default provider plugin for discussion replies.';
$string['defaultreplytopeerreplies'] = 'Allow AI to reply to peer replies by default';
$string['defaultrequiredpeerreplies'] = 'Default required peer replies';
$string['defaultrequireinitialpost'] = 'Require initial response before peer replies by default';
$string['defaultresponseinstructions'] = 'Default AI response instructions';
$string['defaultresponseinstructionsvalue'] = 'Respond in a professional but personal tone. Ask one useful follow-up question when it advances the discussion. Skip trivial acknowledgements and reward substantive thinking.';
$string['defaultresponsetone'] = 'Default AI tone';
$string['defaultsdesc'] = 'These values are used as defaults when teachers create new AI discussion activities.';
$string['defaultsheading'] = 'Default activity behaviour';
$string['defaultshowrubricbeforeposting'] = 'Show rubric before posting by default';
$string['delayminutesvalue'] = '{$a} minute(s)';
$string['discussionoverview'] = 'Discussion overview';
$string['discussionposts'] = 'Discussion posts';
$string['discussionrules'] = 'Discussion rules';
$string['editactivitysettings'] = 'Edit activity settings';
$string['erraidisplayname'] = 'Enter an AI display name when AI is enabled.';
$string['erraireplydelayminutes'] = 'AI reply delay must be 0 or greater.';
$string['errmaxaireplies'] = 'Maximum AI replies per student must be at least 1.';
$string['errminwords'] = 'Minimum substantive words must be at least 1.';
$string['errproviderinvalid'] = 'Choose a valid configured AI provider.';
$string['errproviderrequired'] = 'Choose a discussion AI provider when AI is enabled.';
$string['errrequiredcontent'] = 'Write something before posting.';
$string['errrequiredpeers'] = 'Required peer replies cannot be negative.';
$string['errrequiredprompt'] = 'A teacher prompt is required.';
$string['errrubriccriterionformat'] = 'Rubric criterion line {$a} must use: Criterion name | max score | description';
$string['errrubriccriterionname'] = 'Rubric criterion line {$a} is missing the criterion name.';
$string['errrubriccriterionscore'] = 'Rubric criterion line {$a} must have a positive numeric max score.';
$string['errweightsmustsum'] = 'The three grading weights must sum to 100.';
$string['feedbackdetails'] = 'Feedback details';
$string['gradeprovider'] = 'Grading AI provider';
$string['grading'] = 'Grading';
$string['gradingcomponent'] = 'Grading component';
$string['gradinginstructions'] = 'AI grading instructions';
$string['heuristicfeedbackai'] = 'Substantive replies to {$a->name}: {$a->count}.';
$string['heuristicfeedbackinitial'] = 'Initial response word count: {$a->wordcount}.';
$string['heuristicfeedbackoverall'] = 'Initial response posted: {$a->initial}. AI replies submitted: {$a->ai}. Peer replies submitted: {$a->peer} of {$a->requiredpeer}.';
$string['heuristicfeedbackpeer'] = 'Substantive peer replies: {$a->count} of {$a->required}.';
$string['heuristicfeedbacksummary'] = 'Current heuristic score: {$a->score} / {$a->grademax}.';
$string['initialpostrequiredbeforepeerreply'] = 'Submit your own initial response before replying to peers.';
$string['initialresponsecomponent'] = 'Initial response';
$string['initialweight'] = 'Initial post weight (%)';
$string['integrityduplicatecontent'] = 'Some of this learner’s posts reuse identical text.';
$string['integrityflags'] = 'Integrity flags';
$string['integrityflagsenabled'] = 'Enable integrity heuristic flags';
$string['integrityrepeatedlowsubstance'] = 'This learner has multiple low-substance replies.';
$string['invalidparentpost'] = 'The reply target could not be found.';
$string['maxairepliesperstudent'] = 'Maximum AI replies per student';
$string['maxscore'] = 'Max score';
$string['minsubstantivewords'] = 'Minimum words before AI may reply';
$string['modulename'] = 'AI discussion';
$string['modulenameplural'] = 'AI discussions';
$string['newresponseheading'] = 'New response';
$string['noinstances'] = 'No AI discussion activities exist in this course yet.';
$string['nopostsyet'] = 'No posts are visible yet.';
$string['notapplicable'] = 'Not applicable';
$string['notes'] = 'Notes';
$string['onlythreadownercanreplyai'] = 'Only the learner in this AI thread can reply here.';
$string['openactivity'] = 'Open activity';
$string['openresponsetester'] = 'Open Response Tester';
$string['peerrepliesdisabled'] = 'Peer replies are disabled in this activity.';
$string['peerreplycomponent'] = 'Peer replies';
$string['peerreplynotallowed'] = 'You do not have permission to reply to peer posts in this activity.';
$string['peerweight'] = 'Peer reply weight (%)';
$string['pluginadministration'] = 'AI discussion administration';
$string['pluginname'] = 'AI discussion';
$string['pluginprovideranthropic'] = 'Anthropic Claude';
$string['pluginprovideranthropic_desc'] = 'Plugin-managed Anthropic provider using the native Messages API.';
$string['pluginproviderapikey'] = 'API key / bearer token';
$string['pluginproviderapikeydesc_optional'] = 'Optional. Leave blank only if the endpoint does not require authentication.';
$string['pluginproviderapikeydesc_required'] = 'Required when this provider is enabled.';
$string['pluginproviderbaseurl'] = 'API base URL';
$string['pluginprovidercustom'] = 'Custom (OpenAI-compatible)';
$string['pluginprovidercustom_desc'] = 'Plugin-managed provider for any OpenAI-compatible endpoint, including self-hosted gateways and additional hosted vendors.';
$string['pluginproviderdeepseek'] = 'DeepSeek';
$string['pluginproviderdeepseek_desc'] = 'Plugin-managed DeepSeek provider using its OpenAI-compatible endpoint.';
$string['pluginproviderenabled'] = 'Enable this provider';
$string['pluginprovidergemini'] = 'Google Gemini';
$string['pluginprovidergemini_desc'] = 'Plugin-managed Gemini provider using Google’s OpenAI-compatible endpoint.';
$string['pluginprovidermaxtokens'] = 'Max response length (tokens)';
$string['pluginproviderminimax'] = 'MiniMax';
$string['pluginproviderminimax_desc'] = 'Plugin-managed MiniMax provider using its OpenAI-compatible endpoint.';
$string['pluginprovidermistral'] = 'Mistral AI';
$string['pluginprovidermistral_desc'] = 'Plugin-managed Mistral provider using its OpenAI-compatible chat completions endpoint.';
$string['pluginprovidermodel'] = 'Model name';
$string['pluginproviderollama'] = 'Ollama (Local)';
$string['pluginproviderollama_desc'] = 'Plugin-managed local Ollama endpoint using its OpenAI-compatible API. API key is optional.';
$string['pluginprovideropenai'] = 'OpenAI';
$string['pluginprovideropenai_desc'] = 'Plugin-managed OpenAI provider using the standard chat completions API.';
$string['pluginprovideropenrouter'] = 'OpenRouter';
$string['pluginprovideropenrouter_desc'] = 'Plugin-managed OpenRouter provider using its OpenAI-compatible endpoint.';
$string['pluginproviderscategory'] = 'Plugin-managed providers';
$string['pluginprovidersheading'] = 'Plugin-managed providers';
$string['pluginprovidersheadingdesc'] = 'Enable and configure providers that are managed directly by AI discussion rather than by Moodle core.';
$string['pluginprovidertemperature'] = 'Temperature';
$string['pluginproviderxai'] = 'xAI (Grok)';
$string['pluginproviderxai_desc'] = 'Plugin-managed xAI Grok provider. Grok and xAI refer to the same provider family here.';
$string['postbeforeview'] = 'Require students to post before seeing peer replies';
$string['postbeforeviewlocked'] = 'Peer discussion stays hidden until you submit your initial response.';
$string['postingnotallowed'] = 'You do not have permission to post in this activity.';
$string['previewgrade'] = 'Preview grade';
$string['previewresults'] = 'Preview results';
$string['privatebranch'] = 'Private AI thread';
$string['privatebranchrestricted'] = 'This private AI thread is only visible to the learner and teaching staff assigned to it.';
$string['progressitem'] = 'Progress item';
$string['prompt'] = 'Teacher prompt';
$string['providerheading'] = 'AI provider sources';
$string['providerheadingdesc'] = 'Choose whether activities can use Moodle core AI providers, plugin-managed providers, or both.';
$string['providernotconfigured'] = 'AI is enabled, but no discussion provider is selected.';
$string['providersourceboth'] = 'Moodle core and plugin-managed providers';
$string['providersourcecore'] = 'Moodle core providers only';
$string['providersourcelabelcore'] = 'Moodle core';
$string['providersourcelabelplugin'] = 'Plugin';
$string['providersourcemode'] = 'Provider source mode';
$string['providersourcemodedesc'] = 'Controls which providers appear in the activity provider selectors.';
$string['providersourceplugin'] = 'Plugin-managed providers only';
$string['pseudonymiseusers'] = 'Pseudonymise learners for AI requests';
$string['publicaireplies'] = 'Post AI replies publicly by default';
$string['replyheading'] = 'Reply';
$string['replylink'] = 'Reply';
$string['replymessage'] = 'Reply';
$string['replyprovider'] = 'Discussion AI provider';
$string['replysaved'] = 'Your reply was posted.';
$string['replytopeerreplies'] = 'Allow AI to reply to peer replies';
$string['requiredpeerreplies'] = 'Required peer replies';
$string['requireinitialpost'] = 'Require an initial response before peer replies count';
$string['respondingto'] = 'Responding to';
$string['responseinstructions'] = 'AI response instructions';
$string['responsesaved'] = 'Your response was posted.';
$string['responsetester'] = 'Response Tester';
$string['responsetesterdesc'] = 'Preview grading against the currently saved activity settings and rubric. Save this form first if you want to test new changes.';
$string['responsetesterpagedesc'] = 'Paste sample learner responses below to preview the current rubric-based grade. This does not create posts or update the gradebook.';
$string['responsetestersavefirst'] = 'Save this activity first to enable Response Tester.';
$string['responsetesterusescurrentsettings'] = 'Response Tester uses the currently saved activity settings and rubrics. Save your changes first if you want to test a new configuration.';
$string['responsetone'] = 'AI tone';
$string['rubricbuilderdesc'] = 'Use one criterion per line in the format: Criterion name | max score | description';
$string['rubriccriteriafor'] = '{$a} rubric criteria';
$string['rubriccriteriaformat'] = 'Rubric criteria format';
$string['rubriccriteriaformat_help'] = 'Enter one criterion per line using this format: `Criterion name | max score | description`. Example: `Addresses the prompt | 4 | Directly answers the teacher prompt with a clear response.`';
$string['rubricfeedbackdeveloping'] = 'Current activity evidence supports this area, though there is still room to deepen it.';
$string['rubricfeedbacklimited'] = 'Current activity evidence is still limited in this area.';
$string['rubricfeedbackpartial'] = 'Current activity evidence only partially supports this area so far.';
$string['rubricfeedbackstrong'] = 'Current activity evidence strongly supports this area.';
$string['rubricinstructionsfor'] = '{$a} rubric instructions';
$string['rubricoverview'] = 'Rubric overview';
$string['rubricsheader'] = 'Rubric builder';
$string['rubricweightedscorevalue'] = 'Current weighted contribution: {$a}';
$string['sampleairesponses'] = 'Sample replies to AI';
$string['sampleairesponses_help'] = 'Enter one sample student reply to the AI per block. Separate multiple replies with a blank line.';
$string['sampleinitialresponse'] = 'Sample initial response';
$string['samplepeerresponses'] = 'Sample peer replies';
$string['samplepeerresponses_help'] = 'Enter one sample student reply to a peer per block. Separate multiple replies with a blank line.';
$string['settingsactivitydefaults'] = 'Activity defaults';
$string['settingsactivitydefaultsdesc'] = 'Core defaults used when teachers create new AI discussion activities.';
$string['settingsgradingprivacy'] = 'Grading and privacy';
$string['settingsgradingprivacydesc'] = 'Default grading guidance, rubric visibility, learner pseudonymisation, and integrity heuristics.';
$string['settingsinteractiondefaults'] = 'Discussion AI';
$string['settingsinteractiondefaultsdesc'] = 'Default reply visibility, pacing, and tone for AI-facilitated discussion.';
$string['settingsprovidersources'] = 'Provider sources';
$string['settingsprovidersourcesdesc'] = 'Choose which provider sources are available and which providers new activities should use by default.';
$string['showrubricbeforeposting'] = 'Show rubric to students before posting';
$string['submitreply'] = 'Post reply';
$string['submitresponse'] = 'Post response';
$string['taskprocessaireply'] = 'Process queued AI discussion reply';
$string['taskprocessgrading'] = 'Process queued AI grade recalculation';
$string['teacher'] = 'Teacher';
$string['teacherexample'] = 'Teacher example response';
$string['teacherexample_help'] = 'Add the kind of response you would personally give to the prompt. The AI uses this as private guidance for tone, depth, framing, and reasoning. It should influence the AI response, but it should not be copied or shown to students.';
$string['teacherexampleheader'] = 'How would you respond?';
$string['unknownauthor'] = 'Unknown author';
$string['weight'] = 'Weight';
$string['yourprogress'] = 'Your progress';
$string['yourresponse'] = 'Your response';
