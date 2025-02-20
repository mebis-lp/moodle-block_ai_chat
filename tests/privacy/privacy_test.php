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
 * Tests Privacy Implementation
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Tobias Garske, Christian Kupfer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_ai_chat\privacy;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use core_privacy\tests\request\approved_contextlist;
use block_ai_chat\privacy\provider;
use stdClass;

/**
 * Tests Privacy Implementation
 * @package    block_mbsteachshare
 * @category   test
 * @group      block_mbsteachshare
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_test extends provider_testcase {

    /** @var stdClass $datagenerator Test data generator. */
    private $datagenerator;

    /** @var stdClass $aichatgenerator Plugin data generator for block_mbsteachshare. */
    private $aichatgenerator;

    /** @var stdClass $teacher1 Object containing teacher (main test user). */
    private $teacher1;

    /** @var stdClass $teacher2 Object containing teacher. */
    private $teacher2;

    /** @var stdClass $aichatblock Object containing published template
     * (main test template). */
    private $aichatblock;

    /** @var stdClass $template2 Object containing published template. */
    private $template2;

    /** @var stdClass $template3 Object containing published template. */
    private $template3;

    /**
     * Setup the testcase.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;

        $this->datagenerator = $this->getDataGenerator();
        $this->aichatgenerator = $this->datagenerator->get_plugin_generator('block_ai_chat');


        $this->teacher1 = $this->datagenerator->create_user(['lastname' => 'teacher1']);
        $this->teacher2 = $this->datagenerator->create_user(['lastname' => 'teacher2']);

        $this->aichatgenerator->create_personas($this->teacher1->id);
        $this->aichatgenerator->create_personas($this->teacher2->id);

        // Teacher1 creating templates and courses.
        $origcourse1 = $this->datagenerator->create_course();
        $context1 = \context_course::instance($origcourse1->id);
        $this->aichatblock = $this->datagenerator->create_block('block_ai_chat', ['parentcontextid' => $context1->id]);
        $this->setUser($this->teacher1);

    }

    /**
     * Test for provider::get_contexts_for_userid() return system context and course contexts connected to
     * templates for which user data is stored.
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_user_id(): void {

        // Context list of teacher1 should consist of system context and course contexts for template1 and template2.
        $contextlistteacher1 = provider::get_contexts_for_userid($this->teacher1->id);
        $this->assertEquals(0, $contextlistteacher1->count());

    }

    /**
     * Test for provider::get_users_in_context().
     * @covers ::test_get_users_in_context
     */
    public function test_get_users_in_context(): void {
        global $DB;

        // Checking on users in the systemcontext (ratings, comments).
        $userlistsystem = new userlist(\context_system::instance(), 'block_ai_chat');
        provider::get_users_in_context($userlistsystem);
        // Teacher1 and teacher2 should be found.
        $this->assertEquals(0, $userlistsystem->count());
    }

    /**
     * Test for provider::export_user_data().
     * @covers ::export_user_data
     */
    public function test_export_user_data(): void {
        global $DB;

        $this->setUser($this->teacher1);

        $contextlist = provider::get_contexts_for_userid($this->teacher1->id);
        $contextlist = new approved_contextlist($this->teacher1, 'block_ai_chat', $contextlist->get_contextids());

        provider::export_user_data($contextlist);

        $systemcontext = \context_system::instance();
        $writer = writer::with_context($systemcontext);

        $writer->get_data([]);


    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {

        global $DB;

        provider::delete_data_for_all_users_in_context(\context_block::instance($this->aichatblock->id));

    }

    /**
     * Test for provider::delete_data_for_users().
     * @covers ::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $approveduserids = [$this->teacher1->id, $this->teacher2->id];

        // Check if deletion method performs properly when no proper context given to userlist.
        $usercontext = \context_user::instance($this->teacher1->id);
        $approvedlistwrong = new approved_userlist($usercontext, 'block_mbsteachshare', $approveduserids);
        ob_start();
        provider::delete_data_for_users($approvedlistwrong);
        $output = ob_get_clean();
//        $this->assertStringContainsString('System context or course context expected.', $output);

        $systemcontext = \context_system::instance();

        // Before deletion there should be comments and ratings for specified users.

        foreach ($approveduserids as $approveduserid) {
            $this->assertNotEmpty($DB->get_records('comments',
                [
                    'contextid' => $systemcontext->id,
                    'component' => 'block_mbsteachshare',
                    'commentarea' => 'template',
                    'userid' => $approveduserid,
                ]
            ));
            $this->assertNotEmpty($DB->get_records('rating',
                [
                    'contextid' => $systemcontext->id,
                    'component' => 'block_mbsteachshare',
                    'ratingarea' => 'template',
                    'userid' => $approveduserid,
                ]
            ));
        }

        // Approved user list with system context for deleting comments and ratings.
        $approvedlistsystem = new approved_userlist($systemcontext, 'block_mbsteachshare', $approveduserids);
        // Perform deletion on system context (ratings, comments).
        provider::delete_data_for_users($approvedlistsystem);

        // After deletion ratings and comments should be gone for specified users.

        foreach ($approveduserids as $approveduserid) {
            $this->assertEmpty($DB->get_records('comments',
                [
                    'contextid' => $systemcontext->id,
                    'component' => 'block_mbsteachshare',
                    'commentarea' => 'template',
                    'userid' => $approveduserid,
                ]
            ));
            $this->assertEmpty($DB->get_records('rating',
                [
                    'contextid' => $systemcontext->id,
                    'component' => 'block_mbsteachshare',
                    'ratingarea' => 'template',
                    'userid' => $approveduserid,
                ]
            ));
        }

    }

    /**
     * Test for provider::delete_data_for_user().
     * @covers ::test_delete_data_for_user.
     */
    public function test_delete_data_for_user(): void {
        global $DB, $USER;

        $param = [$this->teacher1->id];

        // Check existing data for teacher1.
        $this->assertTrue($DB->record_exists_select('block_ai_chat_personas', 'userid = ?', $param));

        // Delete teacher1's data for all the contexts where any data of his is stored.
        $usercontexts = provider::get_contexts_for_userid($this->teacher1->id);
        $contextlist = new approved_contextlist($this->teacher1, 'block_mbsteachshare', $usercontexts->get_contextids());
        provider::delete_data_for_user($contextlist);

        $param = [$this->teacher1->id];
        $this->assertFalse($DB->record_exists_select('block_ai_chat_personas', 'userid = ?', $param));

        // Teacher2's entries should still be there.
        $param = [$this->teacher2->id];
        $this->assertTrue($DB->record_exists_select('block_ai_chat_personas', 'userid = ?', $param));
    }

}
