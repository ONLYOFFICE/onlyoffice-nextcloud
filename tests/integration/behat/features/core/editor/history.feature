Feature: Editor document history

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Requesting history for a file returns at least the current version
    When I request the history for the file
    Then the history should be retrieved successfully
    And the history should contain at least 1 entry

  Scenario: Each history entry contains the required fields
    When I request the history for the file
    Then the history should be retrieved successfully
    And each history entry should have a key, version, and created timestamp

  Scenario: Requesting history for a non-existent file returns an error
    When I request the history for a file that does not exist
    Then the history request should fail
