Feature: Editor restore document

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Restoring a previous version returns the updated history
    Given I have updated the file content
    When I restore version 1 of the file
    Then the history should be retrieved successfully

  Scenario: Restoring when no prior versions exist returns the current history without error
    When I restore version 1 of the file
    Then the history should be retrieved successfully

  Scenario: Restoring a version of a non-existent file returns an error
    When I restore version 1 of a file that does not exist
    Then the history request should fail
