Feature: Editor get users

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Users with emails appear in the list
    Given another user with an email exists
    When I request the user list for the file
    Then the response should contain that user

  Scenario: The current user is not in the list
    Given another user with an email exists
    When I request the user list for the file
    Then the response should not contain the current user

  Scenario: Users without an email are excluded
    Given another user without an email exists
    When I request the user list for the file
    Then the response should not contain that user

  Scenario: Searching by name narrows results
    Given a user with display name "Alice Tester" and an email exists
    And a user with display name "Bob Tester" and an email exists
    When I search for "Alice" in the user list for the file
    Then the response should contain a user named "Alice Tester"
    And the response should not contain a user named "Bob Tester"
