<?php declare(strict_types=1);
namespace mod_groupquiz;
use PHPUnit\Framework\TestCase;
if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', '');
}

final class GroupQuizQuestionTest extends TestCase
{
    private static $groupquiz;

    public function setUp(): void 
    {
        $rtqqid = 1;
        $points = 10;
        $question = "test question";

        self::$groupquiz = new groupquiz_question ($rtqqid, $points, $question);
    }

    /**
     * @covers \groupquiz_question
     */
    public function testCanCreateValidGroupQuizQuestion(): void
    {
    //     # Set some values that will be valid for the groupquiz_question constructor
    //     $rtqqid = 1;
    //     $points = 5;
    //     $question = null;

    //     # Assert that a groupquiz_question is created and valid
    //     $groupquiz = new groupquiz_question ($rtqqid, $points, $question);
        $this->assertInstanceOf(
            groupquiz_question::class,
            self::$groupquiz
        );
    }

    /**
     * @covers \groupquiz_question
     */
    public function testCangetId(): void 
    {
        #create groupquiz and 
        $result = self::$groupquiz->getId();
        $expected = 1;

        $this->assertTrue(
            $expected == $result
        );

    }
    /**
     * @covers \groupquiz_question
     */
    public function testCangetPoints(): void 
    {
        #create groupquiz and 
        $result = self::$groupquiz->getPoints();
        $expected = 10;

        $this->assertTrue(
            $expected == $result
        );

    }
    /**
     * @covers \groupquiz_question
     */
    public function testCangetQuestion(): void 
    {
        #create groupquiz and 
        $result = self::$groupquiz->getQuestion();
        $expected = "test question";

        $this->assertTrue(
            $expected == $result
        );

    }

    /**
     * @covers \groupquiz_question
     */
    public function testCanSetAndGetSlot(): void 
    {
        $set = self::$groupquiz->set_slot(8);
        $result = self::$groupquiz->get_slot();
        $expected = 8;
        $this->assertTrue(
            $expected == $result
        );
    }
}