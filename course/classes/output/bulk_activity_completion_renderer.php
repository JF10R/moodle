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

use core_completion\manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/renderer.php');

/**
 * Main renderer for the bulk activity completion stuff.
 *
 * @package core_course
 * @copyright 2017 Adrian Greeve
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_course_bulk_activity_completion_renderer extends plugin_renderer_base {

    /**
     * @deprecated since Moodle 4.0
     */
    public function navigation() {
        throw new coding_exception(__FUNCTION__ . '() has been removed.');
    }

    /**
     * Render the bulk completion tab.
     *
     * @param Array|stdClass $data the context data to pass to the template.
     * @return bool|string
     */
    public function bulkcompletion($data) {
        return parent::render_from_template('core_course/bulkactivitycompletion', $data);
    }

    /**
     * Render the default completion tab.
     *
     * @param array|stdClass $data the context data to pass to the template.
     * @param array $modules The modules that have been sent through the form.
     * @param array $renderedforms Rendered HTML for the requested module forms, keyed by module id.
     * @param array $unavailablemodules Module ids that could not provide a form.
     * @return bool|string
     */
    public function defaultcompletion($data, $modules, array $renderedforms, array $unavailablemodules) {
        $course = get_course($data->courseid);
        $selectedids = array_map('intval', array_keys($modules));
        foreach ($data->modules as $module) {
            // If the user can manage this module, then the activity completion form needs to be returned too, without the
            // cancel button (so only "Save changes" button is displayed).
            if ($module->canmanage) {
                $moduleid = (int)$module->id;
                $isloaded = in_array($moduleid, $selectedids, true);
                $module->modulecollapsed = !$isloaded;
                $module->requiresreload = !$isloaded;

                if ($isloaded) {
                    if (!empty($unavailablemodules[$moduleid])) {
                        $module->formhtml = $this->output->notification(
                            get_string('incompatibleplugin', 'completion'),
                            \core\output\notification::NOTIFY_INFO,
                            false
                        );
                    } else {
                        $module->formhtml = $renderedforms[$moduleid] ?? '';
                    }
                } else {
                    $params = ['id' => $course->id, 'modids' => []];
                    foreach ($selectedids as $selectedid) {
                        $params['modids'][$selectedid] = $selectedid;
                    }
                    $params['modids'][$moduleid] = $moduleid;
                    $module->reloadurl = (new moodle_url('/course/defaultcompletion.php', $params))->out(false);
                    $module->formhtml = '';
                }
            }
        }
        $data->issite = $course->id == SITEID;

        return parent::render_from_template('core_course/defaultactivitycompletion', $data);
    }

    /**
     * Renders the form for bulk editing activities completion
     *
     * @param moodleform $form
     * @param array $activities
     * @return string
     */
    public function edit_bulk_completion($form, $activities) {
        ob_start();
        $form->display();
        $formhtml = ob_get_contents();
        ob_end_clean();

        $data = (object)[
            'form' => $formhtml,
            'activities' => array_values($activities),
            'activitiescount' => count($activities),
        ];
        return parent::render_from_template('core_course/editbulkactivitycompletion', $data);
    }

    /**
     * Renders the form for editing default completion
     *
     * @param moodleform $form
     * @param array $modules
     * @return string
     * @deprecated since Moodle 4.3 MDL-78528
     * @todo MDL-78711 This will be deleted in Moodle 4.7
     */
    public function edit_default_completion($form, $modules) {
        debugging('edit_default_completion() is deprecated and will be removed.', DEBUG_DEVELOPER);

        ob_start();
        $form->display();
        $formhtml = ob_get_contents();
        ob_end_clean();

        $data = (object)[
            'form' => $formhtml,
            'modules' => array_values($modules),
            'modulescount' => count($modules),
        ];
        return parent::render_from_template('core_course/editdefaultcompletion', $data);
    }

    /**
     * Renders the course completion action bar.
     *
     * @param \core_course\output\completion_action_bar $actionbar
     * @return string The HTML output
     */
    public function render_course_completion_action_bar(\core_course\output\completion_action_bar $actionbar): string {
        $data = $actionbar->export_for_template($this->output);
        return $this->output->render_from_template('core_course/completion_action_bar', $data);
    }
}
