@mod @mod_groupquiz @javascript
Feature: Run a group quiz
  In order to assess a group of students together
  As a teacher
  I need to build a group quiz that a group can attempt and submit

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
    And the following "groupings" exist:
      | name       | course | idnumber |
      | Grouping 1 | C1     | GG1      |
    And the following "grouping groups" exist:
      | grouping | group |
      | GG1      | GA    |
    And the following "group members" exist:
      | user     | group |
      | student1 | GA    |
    And the following "activities" exist:
      | activity  | name         | course | grouping |
      | groupquiz | Group quiz 1 | C1     | GG1      |
    # Creating the activity makes its "Default for" category, which the Edit quiz page opens on.
    And the following "questions" exist:
      | questioncategory         | qtype     | name | questiontext     |
      | Default for Group quiz 1 | truefalse | TF1  | The sky is blue. |

  Scenario: A teacher creates a group quiz through the activity form
    Given I log in as "teacher1"
    When I add a groupquiz activity to course "Course 1" section "1" and I fill the form with:
      | Name        | Group quiz 2 |
      | id_grouping | Grouping 1   |
    And I am on the "Group quiz 2" "groupquiz activity" page
    Then I should see "There are no questions added to this quiz."
    And "Edit quiz" "button" should exist

  Scenario: A teacher adds a question from the question bank
    Given I am on the "Group quiz 1" "groupquiz activity" page logged in as "teacher1"
    When I press "Edit quiz"
    And I click on "Select" "checkbox" in the "TF1" "table_row"
    And I press "Add Questions to Group Quiz"
    Then I should see "Added 1 question(s) to the group quiz."
    And I am on the "Group quiz 1" "groupquiz activity" page
    And "Preview quiz" "button" should exist

  Scenario: A student in the grouping attempts and submits the quiz for their group
    Given I am on the "Group quiz 1" "groupquiz activity" page logged in as "teacher1"
    And I press "Edit quiz"
    And I click on "Select" "checkbox" in the "TF1" "table_row"
    And I press "Add Questions to Group Quiz"
    And I log out
    And I am on the "Group quiz 1" "groupquiz activity" page logged in as "student1"
    And I should see "Press Start to begin your group's quiz attempt."
    When I press "Start"
    And I should see "The sky is blue."
    And I set the field "True" to "1"
    And I press "Save question"
    # Saving goes through a plain XHR that Behat does not wait on. The page polls the attempt and
    # shows who answered last once the save has landed.
    And I should see "Student One" in the "#q1_username" "css_element"
    And I press "Submit Quiz"
    Then I should see "Overall Grade: 100.00"
