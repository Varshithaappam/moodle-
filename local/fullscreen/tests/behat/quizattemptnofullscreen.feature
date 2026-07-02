@theme @local @local_fullscreen @uon
Feature: Fullscreen button does not affect quiz attempts.
  In order to take a quiz
  As a user (e.g. student)
  I need to be able to see the navigation on a quiz attempt page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | country |
      | student1 | Sam       | Student  | student1@example.com | GB      |
    And the following "courses" exist:
      | fullname | shortname | category |
      | C1       | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    |

  @javascript
  Scenario: Attempt a quiz with fullscreen turned on
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
      | Test questions   | truefalse   | TF3   | Third question  |
      | Test questions   | truefalse   | TF4   | Fourth question |
      | Test questions   | truefalse   | TF5   | Fifth question  |
      | Test questions   | truefalse   | TF6   | Sixth question  |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 1    |
      | TF3      | 2    |
      | TF4      | 3    |
      | TF5      | 4    |
      | TF6      | 4    |
    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And ".local-fullscreen" "css_element" should exist in the "#topofscroll" "css_element"
    When I click on ".local-fullscreen" "css_element" in the "#topofscroll" "css_element"
    Then ".fullscreenmode" "css_element" should exist in the "body" "css_element"
    And I press "Attempt quiz"
    Then I should see "Finish attempt ..." in the "Quiz navigation" "block"
